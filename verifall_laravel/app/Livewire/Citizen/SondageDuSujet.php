<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Community\Services\Sondages;
use App\Models\Thread;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Le sondage d'un sujet — poser la question, voter, voir le résultat.
 *
 * La règle qui compte n'est PAS dans ce fichier : c'est {@see Sondages} qui
 * refuse de rendre les chiffres à quelqu'un qui n'a pas voté. Le composant se
 * contente d'afficher ce qu'on lui donne, et le service ne lui donne rien tant
 * que la personne ne s'est pas prononcée. Une vue qui oublierait la condition
 * n'aurait donc rien à oublier.
 */
final class SondageDuSujet extends Component
{
    use InteractsWithToasts;

    public string $slug = '';

    public bool $formulaireOuvert = false;

    public string $question = '';

    /**
     * Les réponses proposées. Quatre lignes au départ — le sondage le plus
     * courant en a deux ou trois, et une ligne vide ne coûte rien.
     *
     * @var array<int, string>
     */
    public array $optionsSaisies = ['', '', '', ''];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(Sondages $sondages): View
    {
        $sujet = $this->sujet();
        $lecteur = $this->lecteur();
        $sondage = $sondages->pourSujet($sujet);

        return view('livewire.citizen.sondage-du-sujet', [
            'sondage' => $sondage,
            'options' => $sondage?->options()->get(),
            // `null` = « pas encore voté » : l'écran montre alors les réponses
            // à choisir, et surtout aucun chiffre.
            'resultats' => $sondage !== null ? $sondages->resultats($sondage, $lecteur) : null,
            'connecte' => $lecteur !== null,
            'peutCreer' => $sondage === null && $lecteur !== null && $this->estAuteurDuSujet($lecteur),
        ]);
    }

    public function ouvrirFormulaire(): void
    {
        $this->formulaireOuvert = true;
        $this->resetErrorBag();
    }

    public function fermerFormulaire(): void
    {
        $this->reset(['formulaireOuvert', 'question', 'optionsSaisies']);
        $this->resetErrorBag();
    }

    public function creer(Sondages $sondages): void
    {
        $lecteur = $this->obligerLecteur();

        // L'AUTEUR du sujet, et lui seul : un sondage posé par un tiers sur le
        // fil de quelqu'un d'autre détournerait la discussion sans que son
        // auteur puisse rien y faire.
        abort_unless($this->estAuteurDuSujet($lecteur), 403);

        $sondages->creer($lecteur, $this->sujet(), $this->question, array_values($this->optionsSaisies));

        $this->reset(['formulaireOuvert', 'question', 'optionsSaisies']);

        $this->toastSuccess('Votre sondage est en ligne.');
    }

    public function voter(string $uuid, Sondages $sondages): void
    {
        $sondage = $sondages->pourSujet($this->sujet());

        abort_if($sondage === null, 404);

        $option = $sondages->optionParUuid($sondage, $uuid);

        abort_if($option === null, 404);

        $sondages->voter($this->obligerLecteur(), $sondage, $option);

        $this->toastSuccess('Votre vote est enregistré.');
    }

    #[Computed(persist: true)]
    public function sujetCharge(): Thread
    {
        return Thread::where('slug', $this->slug)->firstOrFail();
    }

    private function estAuteurDuSujet(User $lecteur): bool
    {
        return (int) $this->sujet()->user_id === (int) $lecteur->id;
    }

    private function sujet(): Thread
    {
        return $this->sujetCharge();
    }

    private function lecteur(): ?User
    {
        return Auth::guard('web')->user();
    }

    private function obligerLecteur(): User
    {
        $lecteur = $this->lecteur();

        abort_if($lecteur === null, 403);

        return $lecteur;
    }
}
