<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Identity\Services\ExportDonneesService;
use App\Core\Identity\Services\OtpService;
use App\Core\Identity\Services\PreferencesService;
use App\Core\Identity\Services\ProfilService;
use App\Core\Identity\Services\SuppressionCompteService;
use App\Enums\OtpPurpose;
use App\Enums\PreferenceNotification;
use App\Http\Api\V1\Resources\MoiResource;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Jobs\PreparerExportDonnees;
use App\Mail\OtpCodeMail;
use App\Mail\SuppressionProgrammeeMail;
use App\Models\DataExport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Mon profil, mes réglages, mes données, mon compte.
 *
 * Tout passe par les services d'`Identity` déjà employés par les écrans
 * « Profil » et « Réglages » : la mise à jour du profil est journalisée en
 * audit, la lettre d'information reste un CONSENTEMENT horodaté et non une
 * préférence d'affichage, l'export part en file d'attente. Refaire l'un des
 * trois ici aurait signifié, tôt ou tard, un consentement retiré sur mobile
 * que le registre RGPD ignorerait.
 */
final class MonCompteController
{
    /** `GET /me` */
    public function afficher(Request $requete): JsonResponse
    {
        return Enveloppe::donnee(MoiResource::make($this->utilisateur($requete)));
    }

    /**
     * `PATCH /me`
     *
     * L'ADRESSE E-MAIL N'EST PAS MODIFIABLE ICI, délibérément. La changer
     * exige de prouver la maîtrise de la NOUVELLE boîte par un code — sans
     * quoi une faute de frappe rend le compte irrécupérable, et un écran
     * laissé ouvert permet de le détourner. L'écran mobile « Profil » ne
     * propose pas ce parcours ; le contrat ne l'ouvre donc pas non plus.
     */
    public function mettreAJour(Request $requete, ProfilService $profils): JsonResponse
    {
        $donnees = $requete->validate([
            'prenom' => ['sometimes', 'string', 'min:2', 'max:60'],
            'nom' => ['sometimes', 'string', 'min:2', 'max:60'],
            'telephone' => ['sometimes', 'nullable', 'string', 'min:8', 'max:20'],
            'profession' => ['sometimes', 'nullable', 'string', 'max:80'],
            'ville' => ['sometimes', 'nullable', 'string', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:280'],
        ]);

        $moi = $this->utilisateur($requete);

        /*
         | PATCH veut dire « ne change QUE ce que j'envoie ». Les champs absents
         | reprennent la valeur actuelle : passer `null` à `ProfilService`
         | effacerait le prénom de quelqu'un qui n'a modifié que sa ville — et
         | `name`, qui en est dérivé, deviendrait une chaîne vide.
         */
        $utilisateur = $profils->mettreAJour($moi, [
            'first_name' => $donnees['prenom'] ?? $moi->first_name,
            'last_name' => $donnees['nom'] ?? $moi->last_name,
            'phone' => array_key_exists('telephone', $donnees) ? $donnees['telephone'] : $moi->phone,
            'profession' => array_key_exists('profession', $donnees) ? $donnees['profession'] : $moi->profession,
            'city' => array_key_exists('ville', $donnees) ? $donnees['ville'] : $moi->city,
            'bio' => array_key_exists('bio', $donnees) ? $donnees['bio'] : $moi->bio,
        ]);

        return Enveloppe::donnee(MoiResource::make($utilisateur));
    }

    /**
     * `DELETE /me` — en DEUX temps, sur un seul point d'entrée.
     *
     * POURQUOI DEUX TEMPS. SECURITE_RGPD exige mot de passe ET code envoyé par
     * courriel. Le mot de passe prouve qu'on connaît le compte, le code qu'on
     * tient la boîte : un téléphone volé déverrouillé passe le premier, jamais
     * les deux. Effacer un compte est irréversible passé le délai de grâce ;
     * c'est le geste qui mérite le plus de friction de toute l'application.
     *
     * POURQUOI UN SEUL POINT D'ENTRÉE. Le simulateur mobile ne déclare que
     * `DELETE /api/v1/me`. Ouvrir une route « demander un code de suppression »
     * ajouterait une surface que l'écran n'appelle pas. Sans `code`, l'appel
     * envoie le code et répond « étape suivante » ; avec `code`, il exécute.
     *
     * TOUS LES JETONS SONT RÉVOQUÉS, y compris celui qui a servi à appeler :
     * garder ouvert un accès à un compte qu'on vient de fermer entretiendrait
     * exactement l'ambiguïté que la fermeture sert à lever.
     */
    public function supprimer(
        Request $requete,
        OtpService $otp,
        SuppressionCompteService $suppressions,
    ): JsonResponse {
        $donnees = $requete->validate([
            'confirmation' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string'],
            'code' => ['nullable', 'digits:6'],
        ]);

        $utilisateur = $this->utilisateur($requete);

        // Le geste du prototype : taper le mot exact. Il prouve l'intention,
        // jamais l'identité — d'où le mot de passe juste après.
        if (! $suppressions->confirmationValide($donnees['confirmation'])) {
            return Enveloppe::erreur(CodeErreur::Validation, null, [
                'confirmation' => [__('compte.suppression_mot_exact')],
            ]);
        }

        if (! $suppressions->motDePasseCorrect($utilisateur, $donnees['mot_de_passe'])) {
            return Enveloppe::erreur(CodeErreur::Validation, null, [
                'mot_de_passe' => ['Ce mot de passe est incorrect.'],
            ]);
        }

        if (($donnees['code'] ?? null) === null) {
            $code = $otp->issue($utilisateur->email, OtpPurpose::SuppressionCompte, $requete->ip());
            Mail::to($utilisateur->email)->queue(new OtpCodeMail($code, OtpPurpose::SuppressionCompte));

            return Enveloppe::donnee(['etape' => 'code_envoye'], statut: 202);
        }

        $resultat = $otp->verify($utilisateur->email, OtpPurpose::SuppressionCompte, (string) $donnees['code']);

        if (! $resultat->estValide()) {
            return Enveloppe::erreur(CodeErreur::Validation, $resultat->message(), [
                'code' => [$resultat->message()],
            ]);
        }

        $suppressions->programmer($utilisateur);

        // Le courriel part AVANT la révocation : il porte le lien signé de
        // rétractation, seule voie de retour une fois le compte fermé.
        Mail::to($utilisateur->email)->queue(new SuppressionProgrammeeMail($utilisateur));

        $utilisateur->tokens()->delete();

        return Enveloppe::donnee([
            'etape' => 'suppression_programmee',
            'suppression_demandee_le' => $utilisateur->deletion_requested_at?->toIso8601String(),
        ]);
    }

    /** `GET /me/settings` */
    public function reglages(Request $requete, PreferencesService $preferences): JsonResponse
    {
        $utilisateur = $this->utilisateur($requete);

        return Enveloppe::donnee([
            'notifications' => collect(PreferenceNotification::cases())
                ->mapWithKeys(fn (PreferenceNotification $canal): array => [
                    $canal->value => [
                        'libelle' => $canal->libelle(),
                        'active' => $preferences->estActive($utilisateur, $canal),
                    ],
                ])->all(),
            // Séparée des préférences, et ce n'est pas cosmétique : c'est un
            // consentement marketing, horodaté et versionné dans `consents`.
            'newsletter' => $preferences->newsletterActive($utilisateur),
        ]);
    }

    /**
     * `PATCH /me/settings`
     *
     * Chaque canal est envoyé avec sa valeur voulue, jamais « inverse-le » :
     * une bascule perd sa cible quand la requête est rejouée après une
     * coupure réseau, et l'utilisateur retrouve le réglage inverse de ce qu'il
     * a demandé.
     */
    public function enregistrerLesReglages(Request $requete, PreferencesService $preferences): JsonResponse
    {
        $canaux = implode(',', array_map(
            static fn (PreferenceNotification $canal): string => $canal->value,
            PreferenceNotification::cases(),
        ));

        $donnees = $requete->validate([
            'notifications' => ['sometimes', 'array'],
            'notifications.*' => ['boolean'],
            'newsletter' => ['sometimes', 'boolean'],
        ]);

        $utilisateur = $this->utilisateur($requete);

        foreach ((array) ($donnees['notifications'] ?? []) as $cle => $active) {
            $canal = PreferenceNotification::tryFrom((string) $cle);

            // Une clé inconnue vient forcément d'un client modifié : on ne
            // devine pas ce qu'elle voulait dire, et on ne l'accepte pas non
            // plus en silence.
            if ($canal === null) {
                return Enveloppe::erreur(CodeErreur::Validation, null, [
                    'notifications' => ['Canal inconnu : '.$cle.'. Canaux valides : '.$canaux.'.'],
                ]);
            }

            $preferences->definir($utilisateur, $canal, (bool) $active);
        }

        if (array_key_exists('newsletter', $donnees)) {
            $preferences->definirNewsletter(
                $utilisateur,
                (bool) $donnees['newsletter'],
                $requete->ip(),
                $requete->userAgent(),
            );
        }

        return $this->reglages($requete, $preferences);
    }

    /**
     * `POST /me/export` — portabilité RGPD.
     *
     * L'archive n'est JAMAIS renvoyée dans la réponse : elle contient tout ce
     * que la plateforme détient d'une personne, et un corps HTTP se retrouve
     * dans les journaux d'un mandataire. Elle est construite en tâche de fond
     * et servie par route signée, à durée limitée.
     */
    public function exporterMesDonnees(Request $requete, ExportDonneesService $exports): JsonResponse
    {
        $utilisateur = $this->utilisateur($requete);

        $recente = DataExport::where('user_id', $utilisateur->id)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->exists();

        if ($recente) {
            // Chaque archive est une copie complète de données personnelles :
            // en produire à la chaîne multiplie les objets à protéger.
            return Enveloppe::erreur(
                CodeErreur::Conflit,
                'Une demande est déjà en cours. Patientez quelques minutes.',
            );
        }

        $export = $exports->demander($utilisateur);
        PreparerExportDonnees::dispatch($export->id);

        return Enveloppe::donnee([
            'uuid' => $export->uuid,
            'message' => "Préparation lancée. Vous recevrez un courriel dès que l'archive sera prête.",
        ], statut: 202);
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
