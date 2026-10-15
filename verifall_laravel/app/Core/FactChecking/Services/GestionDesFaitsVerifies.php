<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Application\Contracts\DepotFaitsVerifies;
use App\Core\Identity\Services\AuditLogService;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Models\VerifiedFact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Écrit dans la base des faits tranchés par l'équipe.
 *
 * C'est le seul endroit du produit où un humain décide d'un verdict. Tout ce
 * qui est écrit ici court-circuite douze moteurs et sort en rapport à 100 % de
 * confiance : la rigueur est proportionnelle.
 *
 * Le formulaire du prototype demande quatre choses — l'affirmation, le verdict,
 * les sources, l'analyse. Il en manque trois pour que le mécanisme fonctionne,
 * et ce service les fournit sans en encombrer la personne qui saisit :
 *
 *   · L'EMPREINTE, calculée par le même normaliseur qu'à la soumission. Sans
 *     elle la correspondance exacte ne tombe jamais, et le fait n'est retrouvé
 *     que par approximation — ou pas du tout.
 *   · Les MOTS-CLÉS, proposés depuis l'affirmation. C'est le champ qui décide
 *     si le fait sera retrouvé : le laisser vide, c'est remplir une base que
 *     personne ne consultera. Modifiables, car un humain sait qu'une rumeur
 *     circule aussi sous d'autres mots.
 *   · La PUBLICATION, qui reste un geste distinct. Enregistrer un brouillon et
 *     engager la plateforme ne sont pas la même décision.
 */
final class GestionDesFaitsVerifies
{
    /**
     * Mots trop courants pour distinguer une rumeur d'une autre.
     *
     * « Le gouvernement annonce » les contient tous : les garder gonflerait le
     * recouvrement entre deux affirmations sans rapport, et servirait le
     * verdict de l'une pour l'autre — la pire erreur possible ici.
     */
    private const MOTS_VIDES = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'au', 'aux',
        'et', 'ou', 'que', 'qui', 'quoi', 'dont', 'ne', 'pas', 'plus',
        'ce', 'cet', 'cette', 'ces', 'son', 'sa', 'ses', 'leur', 'leurs',
        'est', 'sont', 'etre', 'ete', 'avoir', 'a', 'ont', 'sera', 'seront',
        'pour', 'par', 'sur', 'dans', 'avec', 'sans', 'entre', 'vers',
        'il', 'elle', 'ils', 'elles', 'on', 'nous', 'vous', 'je', 'tu',
        'en', 'y', 'se', 'sy', 'lui', 'tout', 'tous', 'toute', 'toutes',
    ];

    /** En dessous, un mot ne distingue rien. */
    private const LONGUEUR_MINIMALE = 4;

    /** Au-delà, la liste cesse d'être un faisceau et devient du bruit. */
    private const MOTS_CLES_MAXIMUM = 12;

    public function __construct(
        private readonly DepotFaitsVerifies $depot,
        private readonly EmpreinteContenu $empreintes,
        private readonly AuditLogService $audit,
        private readonly JournalDesCorrections $corrections,
    ) {}

    /**
     * @param  list<array{url: string, name: string}>  $sources
     */
    public function creer(
        User $auteur,
        string $affirmation,
        VerdictFactCheck $verdict,
        string $analyse,
        array $sources = [],
        string $motsCles = '',
        bool $publier = false,
    ): VerifiedFact {
        $fait = $this->depot->creer([
            'uuid' => (string) Str::uuid(),
            'title' => trim($affirmation),
            'summary' => $this->assainir($analyse),
            'verdict' => $verdict->value,
            'keywords' => $this->motsClesRetenus($motsCles, $affirmation),
            'content_fingerprint' => $this->empreintes->pour($affirmation),
            'sources' => $this->sourcesRetenues($sources),
            'is_published' => $publier,
            'created_by' => $auteur->id,
        ]);

        $this->audit->record(
            AuditLogService::CREATION, 'knowledge_base', $auteur,
            cible: $fait,
            apres: ['verdict' => $verdict->value, 'publie' => $publier],
            cibleLibelle: 'Fait vérifié : '.$fait->title,
        );

        return $fait;
    }

    /**
     * @param  list<array{url: string, name: string}>  $sources
     * @param  string  $raisonDeLaCorrection  Obligatoire dès que le verdict change
     */
    public function mettreAJour(
        User $auteur,
        VerifiedFact $fait,
        string $affirmation,
        VerdictFactCheck $verdict,
        string $analyse,
        array $sources = [],
        string $motsCles = '',
        string $raisonDeLaCorrection = '',
    ): VerifiedFact {
        $ancienVerdict = $fait->verdict;

        /*
         | La correction et le nouveau verdict sont écrits ENSEMBLE, ou pas du
         | tout. Si la raison manque, `consignerSiLeVerdictAChange()` lève, la
         | transaction est annulée, et le verdict reste celui d'avant.
         |
         | C'est ce qui rend le journal impossible à contourner : il n'existe
         | aucun chemin où le verdict change et où l'entrée manque.
         */
        $fait = DB::transaction(function () use (
            $fait, $affirmation, $verdict, $analyse, $sources, $motsCles,
            $ancienVerdict, $raisonDeLaCorrection, $auteur,
        ): VerifiedFact {
            $fait = $this->depot->mettreAJour($fait, [
                'title' => trim($affirmation),
                'summary' => $this->assainir($analyse),
                'verdict' => $verdict->value,
                'keywords' => $this->motsClesRetenus($motsCles, $affirmation),
                // Recalculée : sans cela, corriger une coquille dans l'affirmation
                // laisserait le fait indexé sous son ancienne formulation.
                'content_fingerprint' => $this->empreintes->pour($affirmation),
                'sources' => $this->sourcesRetenues($sources),
            ]);

            $this->corrections->consignerSiLeVerdictAChange(
                $fait,
                $fait->title,
                $ancienVerdict,
                $verdict,
                $raisonDeLaCorrection,
                $auteur,
            );

            return $fait;
        });

        /*
         | Un verdict qui change est l'événement le plus lourd de cette table :
         | des rapports déjà rendus s'appuyaient sur l'ancien. Le journal doit
         | le dire explicitement, pas le noyer dans « mise à jour ».
         */
        $this->audit->record(
            AuditLogService::MODIFICATION, 'knowledge_base', $auteur,
            cible: $fait,
            avant: ['verdict' => $ancienVerdict->value],
            apres: ['verdict' => $verdict->value],
            cibleLibelle: $ancienVerdict !== $verdict
                ? sprintf('Verdict %s → %s : %s', $ancienVerdict->libelle(), $verdict->libelle(), $fait->title)
                : 'Fait vérifié : '.$fait->title,
        );

        return $fait;
    }

    /**
     * Publie ou dépublie.
     *
     * Publier, c'est décider que ce verdict sortira désormais à la place d'une
     * analyse — sans qu'aucun moteur ne soit consulté. C'est le geste le plus
     * engageant du back-office, et il est journalisé comme tel.
     */
    public function basculerLaPublication(User $auteur, VerifiedFact $fait): VerifiedFact
    {
        $publie = ! $fait->is_published;

        $fait = $this->depot->mettreAJour($fait, ['is_published' => $publie]);

        $this->audit->record(
            AuditLogService::PUBLICATION, 'knowledge_base', $auteur,
            cible: $fait,
            apres: ['publie' => $publie],
            cibleLibelle: ($publie ? 'Publication' : 'Dépublication').' : '.$fait->title,
        );

        return $fait;
    }

    public function supprimer(User $auteur, VerifiedFact $fait): void
    {
        // Journalisé AVANT : après suppression, il ne reste plus rien à citer.
        $this->audit->record(
            AuditLogService::SUPPRESSION, 'knowledge_base', $auteur,
            cible: $fait,
            avant: ['verdict' => $fait->verdict->value, 'publie' => $fait->is_published],
            cibleLibelle: 'Fait vérifié supprimé : '.$fait->title,
        );

        $this->depot->supprimer($fait);
    }

    /**
     * Mots-clés proposés depuis l'affirmation.
     *
     * Exposé pour que l'écran puisse les afficher pendant la saisie : voir ce
     * que la plateforme retiendra est la seule façon de corriger un
     * rapprochement avant qu'il ne serve.
     */
    public function motsClesSuggeres(string $affirmation): string
    {
        $normalise = $this->empreintes->normaliser($affirmation);

        $mots = array_filter(
            explode(' ', $normalise),
            static fn (string $mot): bool => mb_strlen($mot) >= self::LONGUEUR_MINIMALE
                && ! in_array($mot, self::MOTS_VIDES, true),
        );

        return implode(' ', array_slice(array_values(array_unique($mots)), 0, self::MOTS_CLES_MAXIMUM));
    }

    /**
     * Ce que l'équipe a écrit, ou à défaut ce que la machine propose.
     *
     * Jamais vide : un fait sans mots-clés ne serait retrouvé que par son
     * titre, donc presque jamais.
     */
    private function motsClesRetenus(string $saisis, string $affirmation): string
    {
        $saisis = trim($saisis);

        return $saisis !== '' ? $saisis : $this->motsClesSuggeres($affirmation);
    }

    /**
     * Sources normalisées : ce qui n'a pas d'URL n'est pas une source.
     *
     * @param  list<array{url?: string, name?: string}>  $sources
     * @return list<array{url: string, name: string}>
     */
    private function sourcesRetenues(array $sources): array
    {
        $propres = [];

        foreach ($sources as $source) {
            $url = trim((string) ($source['url'] ?? ''));

            if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $propres[] = ['url' => $url, 'name' => trim((string) ($source['name'] ?? ''))];
        }

        return $propres;
    }

    /**
     * Purifie l'analyse rédigée dans Quill.
     *
     * Avant persistance, pas seulement à l'affichage : une seule vue qui
     * oublierait la purification suffirait à exécuter ce qui a été stocké, et
     * cette table est justement celle dont le contenu est resservi partout.
     */
    private function assainir(string $html): string
    {
        return trim((string) Purifier::clean($html, 'default'));
    }
}
