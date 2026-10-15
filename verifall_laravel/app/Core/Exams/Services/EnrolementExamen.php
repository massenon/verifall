<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotExamens;
use App\Core\Application\Contracts\DepotInscriptionsExamen;
use App\Core\Exams\Exceptions\ExamenIndisponible;
use App\Enums\StatutInscriptionExamen;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\User;

/**
 * L'enrôlement : inscrire, convoquer, arbitrer un report, émarger.
 *
 * Trois précautions valent d'être dites :
 *
 *   · INSCRIRE EST IDEMPOTENT. Un double-clic sur « Inscrire » ne crée pas deux
 *     lignes — la contrainte d'unicité l'interdit — mais il ne doit pas non
 *     plus jeter une erreur SQL au visage de l'utilisateur. Le service rend
 *     l'inscription existante.
 *   · CONVOQUER NE SE REJOUE PAS. Regénérer le code d'une convocation déjà
 *     partie invaliderait celui que le candidat a imprimé.
 *   · LES COMPTEURS DE SESSION SONT RECALCULÉS à chaque écriture, jamais
 *     incrémentés : le nombre de places restantes se lit dessus.
 */
final class EnrolementExamen
{
    /** Sans O/0, I/1, S/5 : un code se recopie à la main devant un surveillant. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    public function __construct(
        private readonly DepotInscriptionsExamen $inscriptions,
        private readonly DepotExamens $examens,
    ) {}

    public function inscrire(ExamSession $session, User $candidat): ExamRegistration
    {
        $existante = $this->inscriptions->pour($candidat, $session);

        if ($existante instanceof ExamRegistration) {
            return $existante;
        }

        if (! $session->status->accepteDesInscriptions()) {
            throw new ExamenIndisponible(
                'La session est '.$session->status->libelle().' : elle n\'accepte plus d\'inscription.'
            );
        }

        if ($session->estComplete()) {
            throw new ExamenIndisponible('Cette session est complète.');
        }

        $inscription = $this->inscriptions->creer($session, $candidat, [
            'status' => StatutInscriptionExamen::Inscrit->value,
        ]);

        $this->examens->recalculerLesCompteursDeSession($session);

        return $inscription;
    }

    /**
     * Émet la convocation — une seule fois.
     */
    public function envoyerLaConvocation(ExamRegistration $inscription): ExamRegistration
    {
        if ($inscription->convocationEnvoyee()) {
            return $inscription;
        }

        return $this->inscriptions->mettreAJour($inscription, [
            'convocation_code' => $this->codeUnique(),
            'convocation_sent_at' => now(),
        ]);
    }

    public function confirmer(ExamRegistration $inscription): ExamRegistration
    {
        return $this->inscriptions->mettreAJour($inscription, [
            'status' => StatutInscriptionExamen::Confirme->value,
        ]);
    }

    public function demanderUnReport(ExamRegistration $inscription, ?string $motif = null): ExamRegistration
    {
        return $this->inscriptions->mettreAJour($inscription, [
            'status' => StatutInscriptionExamen::ReportDemande->value,
            'deferral_requested_at' => now(),
            'deferral_reason' => $motif,
        ]);
    }

    /**
     * Accorde le report : le candidat change de session.
     *
     * La convocation est REMISE À ZÉRO. Laisser l'ancienne en place enverrait
     * quelqu'un à la bonne heure le mauvais jour, avec un code qui ne
     * correspond plus à rien.
     */
    public function arbitrerLeReport(ExamRegistration $inscription, ExamSession $nouvelle): ExamRegistration
    {
        // Relue AVANT le déplacement : après, la relation pointerait sur la
        // session d'accueil et l'ancienne garderait un compteur trop élevé.
        $ancienne = $inscription->loadMissing('session')->session;

        if ($inscription->exam_session_id === $nouvelle->id) {
            throw new ExamenIndisponible('Le report doit désigner une autre session.');
        }

        if (! $nouvelle->status->accepteDesInscriptions()) {
            throw new ExamenIndisponible(
                'La session d\'accueil est '.$nouvelle->status->libelle().'.'
            );
        }

        if ($nouvelle->estComplete()) {
            throw new ExamenIndisponible('La session d\'accueil est complète.');
        }

        if ($this->inscriptions->pour($inscription->loadMissing('user')->user, $nouvelle) instanceof ExamRegistration) {
            throw new ExamenIndisponible('Ce candidat est déjà inscrit à la session d\'accueil.');
        }

        $reportee = $this->inscriptions->mettreAJour($inscription, [
            'exam_session_id' => $nouvelle->id,
            'status' => StatutInscriptionExamen::Inscrit->value,
            'convocation_code' => null,
            'convocation_sent_at' => null,
            'deferral_requested_at' => null,
            'deferral_reason' => null,
        ]);

        // Les deux sessions changent de compte : celle qu'on quitte libère une
        // place, celle qu'on rejoint en perd une.
        $this->examens->recalculerLesCompteursDeSession($nouvelle);

        if ($ancienne instanceof ExamSession && $ancienne->id !== $nouvelle->id) {
            $this->examens->recalculerLesCompteursDeSession($ancienne);
        }

        return $reportee;
    }

    public function annuler(ExamRegistration $inscription): ExamRegistration
    {
        $annulee = $this->inscriptions->mettreAJour($inscription, [
            'status' => StatutInscriptionExamen::Annule->value,
        ]);

        $session = $annulee->loadMissing('session')->session;

        if ($session instanceof ExamSession) {
            $this->examens->recalculerLesCompteursDeSession($session);
        }

        return $annulee;
    }

    /**
     * Émargement — la seule source du taux de présence.
     */
    public function emarger(ExamRegistration $inscription): ExamRegistration
    {
        if ($inscription->status === StatutInscriptionExamen::Annule) {
            throw new ExamenIndisponible('Cette inscription est annulée : personne n\'est attendu.');
        }

        $emargee = $inscription->aEteVu()
            ? $inscription
            : $this->inscriptions->mettreAJour($inscription, [
                'attended_at' => now(),
                'status' => StatutInscriptionExamen::Confirme->value,
            ]);

        $session = $emargee->loadMissing('session')->session;

        if ($session instanceof ExamSession) {
            $this->examens->recalculerLesCompteursDeSession($session);
        }

        return $emargee;
    }

    private function codeUnique(): string
    {
        do {
            $code = 'CONV-'.$this->bloc(4).'-'.$this->bloc(4);
        } while (! $this->inscriptions->codeConvocationLibre($code));

        return $code;
    }

    private function bloc(int $longueur): string
    {
        $sortie = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $longueur; $i++) {
            // random_int et non rand() : un code de convocation devinable
            // laisserait entrer quelqu'un qui n'a jamais été convoqué.
            $sortie .= self::ALPHABET[random_int(0, $max)];
        }

        return $sortie;
    }
}
