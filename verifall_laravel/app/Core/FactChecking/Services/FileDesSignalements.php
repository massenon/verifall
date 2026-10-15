<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Identity\Services\AuditLogService;
use App\Enums\StatutVerification;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerifiedFact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * La file des vérifications citoyennes, côté personnel.
 *
 * ## L'écran qui manquait, et ce qu'il débloque
 *
 * `signalement.publish` et `signalement.delete` étaient déclarées depuis
 * l'origine et employées nulle part : aucune page ne listait les vérifications.
 * Le tableau de bord en montrait les six dernières, et son lien « Tout voir »
 * menait à la base de connaissances — un autre écran, un autre objet.
 *
 * ## « Publier » verse le fait dans la base de connaissances
 *
 * Ce n'est pas une publication au sens éditorial : c'est le versement d'une
 * vérification tranchée dans le fonds que les analyses suivantes consultent
 * AVANT d'appeler un moteur. L'empreinte de contenu voyage avec elle, et c'est
 * elle qui fait le travail : la même intox soumise par quelqu'un d'autre trouve
 * la réponse sans repasser par l'IA — donc sans coûter d'unité, et en répondant
 * instantanément.
 *
 * Une vérification déjà versée ne l'est pas deux fois. Le contrôle porte sur
 * l'EMPREINTE et non sur l'identifiant : deux personnes soumettant le même
 * message produisent deux vérifications, et le fonds n'a besoin que d'une
 * entrée.
 *
 * ## Le fait est versé en BROUILLON
 *
 * `is_published` reste faux. Verser n'est pas publier : l'entrée rejoint la base
 * de connaissances où quelqu'un la relit, la corrige et la publie depuis l'écran
 * prévu pour cela. Sauter cette étape ferait d'un verdict automatique une
 * référence opposable, ce que la plateforme refuse partout ailleurs.
 */
final class FileDesSignalements
{
    public function __construct(
        private readonly AuditLogService $journal,
        private readonly GestionDesFaitsVerifies $faits,
    ) {}

    /**
     * La file, filtrée.
     *
     * @param  string  $statut  valeur d'un StatutVerification, ou ''
     * @param  string  $assignation  '' | 'non-assignees' | 'a-moi'
     * @return LengthAwarePaginator<int, Verification>
     */
    public function file(
        User $membre,
        string $recherche = '',
        string $statut = '',
        string $assignation = '',
        int $parPage = 20,
    ): LengthAwarePaginator {
        return Verification::query()
            ->with(['user:id,uuid,name,email', 'assignee:id,uuid,name', 'report:id,verification_id,verdict,confidence'])
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $terme = '%'.$recherche.'%';
                $q->where(fn (Builder $sous) => $sous
                    ->where('claim', 'like', $terme)
                    ->orWhere('context', 'like', $terme));
            })
            ->when($statut !== '', fn (Builder $q) => $q->where('status', $statut))
            ->when($assignation === 'non-assignees', fn (Builder $q) => $q->whereNull('assigned_to'))
            ->when($assignation === 'a-moi', fn (Builder $q) => $q->where('assigned_to', $membre->id))
            /*
             | Les plus ANCIENNES en tête, à l'inverse de presque toutes les
             | listes du back-office. Cet écran sert à vider une file : trier par
             | date décroissante enterrerait exactement ce qui attend depuis le
             | plus longtemps.
             */
            ->orderBy('created_at')
            ->paginate($parPage);
    }

    /**
     * @return array{total: int, en_attente: int, non_assignees: int, revue_humaine: int}
     */
    public function indicateurs(): array
    {
        return [
            'total' => (int) Verification::query()->count(),
            'en_attente' => (int) Verification::query()->whereIn('status', [
                StatutVerification::EnFile->value,
                StatutVerification::EnCours->value,
                StatutVerification::EnAttenteDeReprise->value,
            ])->count(),
            // Le chiffre qui commande : une vérification que personne n'a prise
            // n'avance pas, quel que soit son statut technique.
            'non_assignees' => (int) Verification::query()->whereNull('assigned_to')->count(),
            'revue_humaine' => (int) Verification::query()
                ->where('status', StatutVerification::RevueHumaine->value)
                ->count(),
        ];
    }

    /**
     * Verse une vérification tranchée dans la base de connaissances.
     *
     * @throws RuntimeException si la vérification n'a pas de verdict, ou est déjà versée
     */
    public function publier(Verification $verification, User $auteur): VerifiedFact
    {
        $rapport = $verification->report;

        /*
         | Un rapport suffit : la colonne `verdict` est NOT NULL et castée en
         | enum, donc un rapport EST un verdict. Tester les deux ajoutait une
         | garde morte — l'analyse statique l'a signalée.
         */
        if ($rapport === null) {
            throw new RuntimeException(
                'Cette vérification n’a pas encore de verdict : il n’y a rien à verser.',
            );
        }

        $empreinte = (string) $verification->content_fingerprint;

        if ($empreinte !== '' && VerifiedFact::query()->where('content_fingerprint', $empreinte)->exists()) {
            throw new RuntimeException(
                'Ce contenu figure déjà dans la base de connaissances.',
            );
        }

        $fait = DB::transaction(function () use ($verification, $rapport, $auteur, $empreinte): VerifiedFact {
            return VerifiedFact::create([
                'uuid' => (string) Str::uuid(),
                'title' => Str::limit((string) $verification->claim, 190),
                'summary' => (string) ($rapport->summary ?? ''),
                'verdict' => $rapport->verdict->value,
                /*
                 | Les mots-clés viennent du service qui alimente déjà la base de
                 | connaissances à la main : ils sont normalisés comme les
                 | affirmations, et c'est cette normalisation partagée qui fait
                 | que le rapprochement fonctionne. En écrire une seconde ici
                 | produirait des entrées que les recherches existantes ne
                 | trouveraient pas.
                 */
                'keywords' => $this->faits->motsClesSuggeres((string) $verification->claim),
                /*
                 | L'empreinte est le cœur du versement : c'est elle que les
                 | analyses suivantes consultent avant d'appeler un moteur. Sans
                 | elle, l'entrée est un texte de plus dans une base que personne
                 | ne relit.
                 */
                'content_fingerprint' => $empreinte !== '' ? $empreinte : null,
                // Brouillon : verser n'est pas publier. Quelqu'un relit.
                'is_published' => false,
                'created_by' => $auteur->id,
            ]);
        });

        $this->journal->record(
            action: 'verification.publish',
            module: 'factchecking',
            acteur: $auteur,
            cible: $verification,
            apres: ['fait_verifie' => $fait->uuid, 'verdict' => $fait->verdict],
            cibleLibelle: Str::limit((string) $verification->claim, 80),
        );

        return $fait;
    }

    /**
     * Retire une vérification de la file — suppression DOUCE.
     *
     * La ligne disparaît de toutes les listes, y compris de l'espace citoyen,
     * et reste récupérable. Le motif n'est pas stocké sur la table : il vit au
     * journal d'audit, qui est fait pour ça, et deux vérités sur le même geste
     * finiraient par diverger.
     */
    public function supprimer(Verification $verification, User $auteur, string $motif): void
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new RuntimeException(
                'Retirer la vérification de quelqu’un demande un motif : c’est ce qui rend le geste contestable.',
            );
        }

        $this->journal->record(
            action: 'verification.delete',
            module: 'factchecking',
            acteur: $auteur,
            cible: $verification,
            avant: ['status' => $verification->status, 'claim' => Str::limit((string) $verification->claim, 190)],
            apres: ['motif' => $motif],
            cibleLibelle: Str::limit((string) $verification->claim, 80),
        );

        // Journalisé AVANT : après la suppression, la cible n'est plus
        // retrouvable par une requête ordinaire, et le journal perdrait son lien.
        $verification->delete();
    }
}
