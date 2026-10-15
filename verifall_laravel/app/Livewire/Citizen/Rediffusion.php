<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * La rediffusion d'une Live Class — l'écran qui manquait derrière le bouton.
 *
 * ## Ce qu'il remplace
 *
 * Rien : il n'existait pas. « Voir le replay » renvoyait sur la liste des
 * tutoriels, et pour cause — `live_classes` ne portait aucune colonne
 * d'enregistrement (B-06). Le lien de repli était honnête faute de mieux ; il
 * n'en donnait pas moins l'impression d'un bouton cassé.
 *
 * ## Pourquoi un composant plutôt qu'une redirection vers l'adresse
 *
 * Renvoyer directement sur `replay_url` serait plus court d'un fichier, et
 * mauvais pour trois raisons. L'adresse serait exposée dans la barre du
 * navigateur, donc partageable hors de la plateforme, ce qui viderait de son
 * sens la règle d'accès ci-dessous. La page perdrait tout contexte — titre,
 * cours, mentor, date de la séance. Et une séance sans rediffusion n'aurait
 * nulle part où l'expliquer : on retomberait sur un 404, c'est-à-dire sur le
 * défaut d'origine sous un autre nom.
 *
 * ## Les états, et pourquoi aucun n'est un `abort`
 *
 * Trois refus possibles : la séance n'a pas de rediffusion, elle n'est pas
 * encore publiée, ou la personne n'est pas inscrite au cours. Chacun a sa
 * phrase et son geste de sortie. C'est la même leçon que le quiz (B-05) : le
 * serveur sait pourquoi il refuse, il n'y a aucune raison de le garder pour lui.
 *
 * Seule la séance NON PUBLIÉE reste un 404, et pour la même raison que dans le
 * contrôleur de calendrier : ce n'est pas un parcours interrompu, c'est une
 * ressource qui n'existe pas encore publiquement.
 */
#[Layout('components.layouts.citizen', ['title' => "Rediffusion · Vérif'All"])]
final class Rediffusion extends Component
{
    public string $uuid = '';

    /**
     * Pourquoi la rediffusion ne s'affiche pas — `null` quand elle s'affiche.
     *
     * @var null|'sans-rediffusion'|'non-inscrit'
     */
    public ?string $blocage = null;

    public function mount(LiveClass $liveClass): void
    {
        abort_unless($liveClass->is_published, 404);

        $this->uuid = $liveClass->uuid;

        /*
         | L'ordre compte. « Pas de rediffusion » se dit à tout le monde : c'est
         | un fait sur la séance, pas sur la personne. « Pas inscrit » ne se dit
         | qu'ensuite, et seulement s'il y avait effectivement quelque chose à
         | regarder — sinon on reprocherait à quelqu'un une inscription qui ne
         | lui aurait rien apporté.
         */
        if (! $liveClass->rediffusionDisponible()) {
            $this->blocage = 'sans-rediffusion';

            return;
        }

        if (! $liveClass->rediffusionVisiblePar($this->utilisateur())) {
            $this->blocage = 'non-inscrit';
        }
    }

    public function render(): View
    {
        $seance = LiveClass::with(['course', 'host'])->where('uuid', $this->uuid)->firstOrFail();

        return view('livewire.citizen.rediffusion', [
            'seance' => $seance,
            'minutes' => $seance->recording_minutes ?? $seance->minutes,
        ]);
    }

    private function utilisateur(): ?User
    {
        return Auth::guard('web')->user();
    }
}
