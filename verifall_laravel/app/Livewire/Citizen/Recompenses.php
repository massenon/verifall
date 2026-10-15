<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Application\Contracts\XpLogRepositoryInterface;
use App\Core\Gamification\Services\AttributionDesBadges;
use App\Core\Gamification\Services\NiveauCitoyen;
use App\Core\Gamification\Services\SerieDeVigilance;
use App\Enums\MotifGainXp;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Certificats & Badges » — transposition de compte-user/recompenses.html.
 *
 * Les badges VERROUILLÉS sont affichés eux aussi, avec leur condition : voir ce
 * qui reste à atteindre est ce qui donne envie de continuer. Un badge invisible
 * tant qu'il n'est pas gagné ne motive personne.
 */
#[Layout('components.layouts.citizen', ['title' => "Certificats & Badges · Vérif'All"])]
final class Recompenses extends Component
{
    /**
     * Les badges sont recalculés À L'OUVERTURE de la page.
     *
     * Sans cela, l'écran affichait « 0 / 8 » et présentait comme verrouillées
     * des conditions déjà remplies — « 1 000 XP requis » cadenassé à côté d'une
     * tuile annonçant 1 280 XP. Il fallait cliquer un bouton pour que la page
     * dise la vérité, ce qui n'est pas le rôle de la personne.
     *
     * Dans `mount()` et non dans `render()` : `render()` rejoue à chaque
     * interaction, et recalculer huit conditions à chaque clic de filtre
     * coûterait sans rien apporter.
     */
    public function mount(AttributionDesBadges $attribution): void
    {
        $attribution->recalculer($this->utilisateur());
    }

    public function render(
        NiveauCitoyen $niveaux,
        SerieDeVigilance $series,
        XpLogRepositoryInterface $journalXp,
    ): View {
        $utilisateur = $this->utilisateur();

        return view('livewire.citizen.recompenses', [
            'utilisateur' => $utilisateur,
            'niveau' => $niveaux->niveau($utilisateur),
            'nomDuNiveau' => $niveaux->nom($utilisateur),
            'xpDansLePalier' => $niveaux->xpDansLePalier($utilisateur),
            'xpParPalier' => NiveauCitoyen::XP_PAR_PALIER,
            'xpRestant' => $niveaux->xpRestant($utilisateur),
            'pourcentage' => $niveaux->pourcentage($utilisateur),
            'serieVivante' => $series->estVivante($utilisateur),
            'joursAvantLaPrime' => $series->joursAvantLaPrime($utilisateur),
            'badges' => $this->badges(),
            'debloques' => $this->debloques(),
            'certificats' => $this->certificats(),
            'prochainBadge' => $this->prochainBadge(),
            'activite' => $this->activite(),
            'origineDesXp' => $this->origineDesXp($journalXp, $utilisateur),
        ]);
    }

    /**
     * Le badge le plus proche d'être décroché.
     *
     * Celui qui demande le moins d'XP parmi ceux qui restent : montrer une
     * cible atteignable vaut mieux que d'en montrer une lointaine.
     */
    private function prochainBadge(): ?Badge
    {
        $obtenus = UserBadge::where('user_id', $this->utilisateur()->id)->pluck('badge_id');

        return Badge::whereNotIn('id', $obtenus)
            ->orderBy('xp_reward')
            ->first();
    }

    /**
     * D'où viennent les points de ce compte.
     *
     * ## La ligne « points antérieurs », qui n'est pas un pis-aller
     *
     * Le journal `xp_logs` n'existe que depuis le 2 septembre 2026. Les comptes
     * ouverts avant ont un total sans une seule ligne pour l'expliquer : sans
     * cette ligne, quelqu'un qui affiche 1 780 XP verrait une répartition qui
     * en totalise 250, et conclurait à une perte.
     *
     * On préfère donc l'écart AVOUÉ au décompte muet. Elle disparaîtra d'elle-même
     * pour les comptes créés ensuite, sans qu'aucun code ne change.
     *
     * L'écart est borné à zéro : si le journal dépassait le compteur, ce serait un
     * défaut à corriger, pas une ligne à afficher à l'envers.
     *
     * @return list<array{libelle: string, points: int}> du plus gros au plus petit
     */
    private function origineDesXp(XpLogRepositoryInterface $journal, User $utilisateur): array
    {
        $lignes = [];

        foreach ($journal->repartition($utilisateur) as $motif => $points) {
            if ($points === 0) {
                continue;
            }

            $lignes[] = [
                'libelle' => MotifGainXp::from((string) $motif)->libelle(),
                'points' => (int) $points,
            ];
        }

        $anterieurs = max(0, (int) $utilisateur->xp - $journal->somme($utilisateur));

        if ($anterieurs > 0) {
            $lignes[] = [
                'libelle' => (string) __('cit_recompenses.points_anterieurs_au_journal'),
                'points' => $anterieurs,
            ];
        }

        usort($lignes, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);

        return $lignes;
    }

    /**
     * L'activité récente, telle qu'elle s'est produite.
     *
     * Trois sources réunies puis triées : mouvements d'unités, badges
     * décrochés, certificats délivrés. Le prototype affiche quatre lignes de
     * démonstration ; une récompense qu'on n'a pas reçue n'a rien à faire dans
     * son propre historique.
     *
     * @return list<array{titre: string, detail: string, quand: string, date: Carbon}>
     */
    private function activite(): array
    {
        $utilisateur = $this->utilisateur();
        $evenements = [];

        foreach (UnitLog::where('user_id', $utilisateur->id)->latest()->limit(5)->get() as $mouvement) {
            $evenements[] = [
                'titre' => ($mouvement->delta > 0 ? '🎁 +' : '↘ ').abs((int) $mouvement->delta)
                    .' '.Str::plural('unité', abs((int) $mouvement->delta)),
                'detail' => (string) $mouvement->label,
                'quand' => $mouvement->created_at->diffForHumans(),
                'date' => $mouvement->created_at,
            ];
        }

        foreach (UserBadge::with('badge')->where('user_id', $utilisateur->id)
            ->latest('unlocked_at')->limit(5)->get() as $obtention) {
            $evenements[] = [
                'titre' => '🏅 Badge débloqué',
                'detail' => (string) ($obtention->badge?->name ?? 'Badge'),
                'quand' => $obtention->unlocked_at->diffForHumans(),
                'date' => $obtention->unlocked_at,
            ];
        }

        foreach (Certificate::where('user_id', $utilisateur->id)
            ->latest('issued_at')->limit(5)->get() as $certificat) {
            $evenements[] = [
                'titre' => '🎓 Certificat obtenu',
                'detail' => 'Cours « '.$certificat->course_title.' » terminé',
                'quand' => $certificat->issued_at->diffForHumans(),
                'date' => $certificat->issued_at,
            ];
        }

        usort($evenements, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return array_slice($evenements, 0, 6);
    }

    /**
     * Tous les badges, débloqués ou non.
     *
     * @return Collection<int, Badge>
     */
    private function badges(): Collection
    {
        return Badge::where('is_active', true)->orderBy('position')->get();
    }

    /**
     * Dates de déblocage, indexées par badge.
     *
     * Chargées EN UNE FOIS : interroger depuis chaque tuile ferait une requête
     * par badge affiché.
     *
     * @return \Illuminate\Support\Collection<int, UserBadge>
     */
    #[Computed]
    public function obtentions(): \Illuminate\Support\Collection
    {
        return UserBadge::where('user_id', $this->utilisateur()->id)
            ->get()
            ->keyBy('badge_id');
    }

    public function obtention(Badge $badge): ?UserBadge
    {
        return $this->obtentions()->get($badge->id);
    }

    private function debloques(): int
    {
        return $this->obtentions()->count();
    }

    /**
     * @return Collection<int, Certificate>
     */
    private function certificats(): Collection
    {
        return Certificate::with('course:id,slug,title')
            ->where('user_id', $this->utilisateur()->id)
            ->whereNull('revoked_at')
            ->latest('issued_at')
            ->get();
    }

    /**
     * Recalcule les badges à la demande.
     *
     * Le bouton existe parce que les conditions peuvent changer — un badge
     * ajouté, un seuil corrigé — et que personne ne doit attendre sa prochaine
     * vérification pour recevoir ce qu'il a déjà mérité.
     */
    public function verifierMesBadges(AttributionDesBadges $attribution): void
    {
        $attribution->recalculer($this->utilisateur());

        unset($this->obtentions);
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
