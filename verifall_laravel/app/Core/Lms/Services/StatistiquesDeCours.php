<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Core\Lms\Notifications\RelanceDeCours;
use App\Enums\StatutInscription;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

/**
 * Ce qu'un cours produit réellement, et à qui il faut écrire.
 *
 * ## Trois chiffres, et pourquoi ceux-là
 *
 * « Citoyens inscrits » dit l'audience. « Certificats délivrés » dit combien
 * sont allés au bout. **La progression moyenne dit ce qui se passe entre les
 * deux** — et c'est le seul des trois sur lequel on peut agir. Un cours à
 * quatre-vingts inscrits, trois certificats et 22 % de progression moyenne n'a
 * pas un problème d'audience : il a un problème au troisième module.
 *
 * ## La moyenne écarte ceux qui n'ont jamais commencé
 *
 * Un inscrit à 0 % qui n'a jamais ouvert la première leçon n'a pas abandonné le
 * cours : il ne l'a pas commencé. Les compter tirerait la moyenne vers le bas
 * sans rien apprendre du contenu — et masquerait précisément le décrochage
 * qu'on cherche.
 *
 * Les deux chiffres sont rendus séparément : la moyenne des ACTIFS, et combien
 * n'ont jamais commencé. Le second est une information en soi — s'il est élevé,
 * le problème est à l'inscription, pas dans le cours.
 */
final class StatistiquesDeCours
{
    /**
     * @return array{
     *     inscrits: int,
     *     certificats: int,
     *     progression_moyenne: int,
     *     jamais_commences: int,
     *     termines: int,
     * }
     */
    public function pour(Course $cours): array
    {
        $inscriptions = Enrollment::query()->where('course_id', $cours->getKey());

        $inscrits = (clone $inscriptions)->count();
        $jamaisCommences = (clone $inscriptions)->where('progress', '<=', 0)->count();

        $moyenne = (clone $inscriptions)->where('progress', '>', 0)->avg('progress');

        return [
            'inscrits' => $inscrits,
            'certificats' => $cours->certificates()->whereNull('revoked_at')->count(),
            'progression_moyenne' => (int) round((float) ($moyenne ?? 0)),
            'jamais_commences' => $jamaisCommences,
            'termines' => (clone $inscriptions)->whereNotNull('completed_at')->count(),
        ];
    }

    /**
     * Les inscrits, les MOINS avancés d'abord.
     *
     * C'est l'ordre utile : cet écran sert à relancer, et on relance ceux qui
     * décrochent. Trier par date d'inscription mettrait les anciens en tête,
     * qui sont le plus souvent ceux qui ont fini.
     *
     * @return LengthAwarePaginator<int, Enrollment>
     */
    public function inscrits(Course $cours, int $parPage = 20, string $recherche = '', string $etat = ''): LengthAwarePaginator
    {
        $motif = '%'.addcslashes(trim($recherche), '%_\\').'%';

        return Enrollment::query()
            ->with('user:id,uuid,name,email')
            ->where('course_id', $cours->getKey())
            ->when(trim($recherche) !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', $motif)->orWhere('email', 'like', $motif)))
            ->when($etat === 'termine', fn ($q) => $q->where('status', StatutInscription::Termine))
            ->when($etat === 'en_cours', fn ($q) => $q->where('status', '!=', StatutInscription::Termine)->where('progress', '>', 0))
            ->when($etat === 'jamais', fn ($q) => $q->where('progress', 0))
            ->orderBy('progress')
            ->orderBy('created_at')
            ->paginate($parPage);
    }

    /**
     * Relance un inscrit qui n'a pas terminé.
     *
     * ## Deux refus, et ils comptent
     *
     * On ne relance PAS quelqu'un qui a terminé : lui écrire « poursuivez votre
     * cours » après son certificat est le genre de message qui fait se
     * désabonner de tout.
     *
     * On ne relance pas deux fois en moins de {@see self::REPOS_JOURS} jours.
     * Une relance est un service ; deux relances en trois jours sont du
     * harcèlement, et c'est l'écran qui doit l'empêcher — pas la discipline de
     * la personne qui clique.
     *
     * @throws RuntimeException
     */
    public function relancer(Enrollment $inscription, User $auteur): void
    {
        if ($inscription->completed_at !== null) {
            throw new RuntimeException(
                'Cette personne a terminé le cours. La relancer serait un message de trop.'
            );
        }

        $destinataire = $inscription->user;

        if ($destinataire === null) {
            throw new RuntimeException('Ce compte n’existe plus.');
        }

        $derniere = $inscription->last_reminder_at;

        if ($derniere !== null && $derniere->gt(now()->subDays(self::REPOS_JOURS))) {
            throw new RuntimeException(sprintf(
                'Déjà relancé %s. On attend %d jours entre deux rappels.',
                $derniere->diffForHumans(),
                self::REPOS_JOURS,
            ));
        }

        $destinataire->notify(new RelanceDeCours($inscription, $auteur->name));

        $inscription->forceFill(['last_reminder_at' => now()])->save();
    }

    /**
     * Sept jours entre deux relances.
     *
     * Assez pour qu'une semaine chargée passe sans qu'on soit relancé deux
     * fois ; assez court pour qu'un cours abandonné se rattrape avant d'être
     * oublié.
     */
    public const REPOS_JOURS = 7;
}
