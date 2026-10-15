<?php

declare(strict_types=1);

namespace App\Core\Application\Services;

use App\Enums\StatutMessageContact;
use App\Enums\StatutSignalementContenu;
use App\Enums\StatutVerification;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\ContentReport;
use App\Models\CounterExpertise;
use App\Models\Course;
use App\Models\KycSubmission;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedFact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Les chiffres du tableau de bord du personnel.
 *
 * ## Ce que cet écran doit répondre, dans cet ordre
 *
 * La première question de quelqu'un qui ouvre un back-office n'est pas
 * « combien d'inscrits ? » mais **« qu'est-ce qui m'attend ? »**. Les compteurs
 * de volume flattent ; ils ne disent pas quoi faire.
 *
 * L'agrégat est donc scindé en deux : ce qui EXIGE UNE DÉCISION HUMAINE d'un
 * côté, l'activité de l'autre. Le premier bloc peut valoir zéro, et c'est une
 * bonne nouvelle qu'il faut afficher comme telle plutôt que de masquer le bloc.
 *
 * ## Pourquoi un service et non des requêtes dans le composant
 *
 * Une dizaine de comptages, dont plusieurs portent une règle métier — ce qui
 * compte comme « en attente », ce qu'est une analyse interrompue. Les laisser
 * dans le composant reviendrait à disperser ces règles dans une vue.
 *
 * ## La mise en cache, et sa limite
 *
 * Une minute. Assez pour qu'un rafraîchissement ne relance pas dix comptages,
 * trop court pour qu'un modérateur croie avoir traité une file qui reste
 * pleine à l'écran. Les chiffres d'ATTENTE ne sont volontairement PAS mis en
 * cache : ce sont ceux sur lesquels on agit.
 */
final class TableauDeBordPersonnel
{
    private const CLEF_CACHE = 'staff.tableau-de-bord.volumes';

    private const TTL_SECONDES = 60;

    /**
     * Ce qui attend une décision humaine. Jamais mis en cache.
     *
     * @return array{arbitrages: int, signalements: int, messages: int, interrompues: int, kyc: int, contestations: int}
     */
    public function aTraiter(): array
    {
        return [
            /*
             | « En arbitrage » n'est pas un échec : les moteurs se contredisent
             | et un humain tranche. C'est la file la plus importante du poste,
             | parce qu'elle bloque des rapports déjà payés par quelqu'un.
             */
            'arbitrages' => Verification::query()
                ->where('status', StatutVerification::RevueHumaine->value)
                ->count(),

            'signalements' => ContentReport::query()
                ->where('status', StatutSignalementContenu::EnAttente->value)
                ->count(),

            /*
             | « Nouveau » ET « en cours » : un message pris en charge mais non
             | terminé attend toujours quelqu'un. Ne compter que les nouveaux
             | ferait disparaître de l'écran ce qu'on a commencé sans finir.
             */
            'messages' => ContactMessage::query()
                ->whereIn('status', [
                    StatutMessageContact::Nouveau->value,
                    StatutMessageContact::EnCours->value,
                ])
                ->count(),

            'interrompues' => Verification::query()
                ->where('status', StatutVerification::Echouee->value)
                ->count(),

            /*
             | Les dossiers d'identité en attente. Ils n'existaient pas avant le
             | 1er septembre 2026 : la vérification était un drapeau posé à la
             | main. Une file de pièces d'identité qui dort est doublement
             | mauvaise — la personne attend, et nous détenons ses scans.
             */
            'kyc' => KycSubmission::query()->enAttente()->count(),

            /*
             | Les verdicts contestés. C'est le retour le plus précieux qu'une
             | plateforme de fact-checking reçoive, et le plus facile à
             | enterrer : il n'a pas d'échéance, et personne ne se plaint si on
             | l'oublie — sauf la personne qui avait raison.
             */
            'contestations' => CounterExpertise::query()->enAttente()->count(),
        ];
    }

    /**
     * L'état du laboratoire, en direct.
     *
     * @return array{en_cours: int, en_file: int, reprises: int}
     */
    public function laboratoire(): array
    {
        return [
            'en_cours' => Verification::query()
                ->where('status', StatutVerification::EnCours->value)
                ->count(),

            'en_file' => Verification::query()
                ->where('status', StatutVerification::EnFile->value)
                ->count(),

            'reprises' => Verification::query()
                ->where('status', StatutVerification::EnAttenteDeReprise->value)
                ->count(),
        ];
    }

    /**
     * Les volumes du prototype : citoyens, abonnés, signalements, cours.
     *
     * @return array{citoyens: int, abonnes: int, signalements: int, cours: int, faits: int}
     */
    public function volumes(): array
    {
        return Cache::remember(self::CLEF_CACHE, self::TTL_SECONDES, static fn (): array => [
            'citoyens' => User::role(UserRole::Citoyen->value)->count(),

            /*
             | Les abonnés CONFIRMÉS et non désabonnés. Compter les autres
             | gonflerait le chiffre d'adresses qui ne recevront jamais rien —
             | et le premier envoi le démentirait publiquement.
             */
            'abonnes' => NewsletterSubscriber::query()
                ->whereNotNull('confirmed_at')
                ->whereNull('unsubscribed_at')
                ->count(),

            'signalements' => ContentReport::query()->count(),

            'cours' => Course::query()->where('is_published', true)->count(),

            'faits' => VerifiedFact::query()->where('is_published', true)->count(),
        ]);
    }

    /** Les dernières vérifications, pour la colonne d'activité. */
    public function dernieresVerifications(int $combien = 6): Collection
    {
        return Verification::query()
            ->latest('created_at')
            ->limit($combien)
            ->get(['uuid', 'type', 'status', 'claim', 'created_at']);
    }

    /** Les derniers faits publiés. */
    public function derniersFaits(int $combien = 5): Collection
    {
        return VerifiedFact::query()
            ->where('is_published', true)
            ->latest('created_at')
            ->limit($combien)
            ->get(['uuid', 'title', 'verdict', 'created_at']);
    }

    /**
     * Ce qui ne va pas dans la configuration, et que personne ne verra sinon.
     *
     * Un back-office qui n'affiche pas l'état de sa propre installation laisse
     * découvrir les pannes par les utilisateurs. Les deux points ci-dessous se
     * sont réellement produits en mise en ligne.
     *
     * @return list<array{niveau: string, texte: string}>
     */
    public function avertissements(): array
    {
        $alertes = [];

        if ((bool) config('factcheck.fake_adapters', true)) {
            $alertes[] = [
                'niveau' => 'grave',
                'texte' => 'Les moteurs d\'analyse tournent en mode SIMULÉ : '
                    .'les verdicts rendus ne sont pas des vérifications. '
                    .'Posez FAKE_ADAPTERS=false et configurez les clés.',
            ];
        }

        if (config('app.debug') === true && app()->isProduction()) {
            $alertes[] = [
                'niveau' => 'grave',
                'texte' => 'APP_DEBUG est actif en production : chaque visiteur '
                    .'voit les traces d\'erreur, les chemins du serveur et les requêtes SQL.',
            ];
        }

        $hote = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($hote === 'localhost' || $hote === null) {
            $alertes[] = [
                'niveau' => 'attention',
                'texte' => 'APP_URL n\'est pas renseignée : les liens des courriels '
                    .'pointeront vers localhost, et le serveur s\'annoncera sous ce nom en SMTP.',
            ];
        }

        return $alertes;
    }
}
