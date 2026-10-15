<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\MessageBloque;
use App\Core\Community\Services\SignalementDeContenu;
use App\Core\Community\Services\TransmissionAuLabo;
use App\Enums\AvisCommunaute;
use App\Enums\VerdictFactCheck;
use App\Models\ContentLike;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\ThreadVote;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Un sujet du forum — transposition de compte-user/forum-sujet.html.
 *
 * C'est ici qu'on vote, qu'on répond, et qu'on voit le verdict officiel s'il a
 * été rendu. Les deux jugements restent séparés à l'écran comme en base.
 */
#[Layout('components.layouts.citizen', ['title' => "Sujet · Vérif'All"])]
final class Sujet extends Component
{
    use InteractsWithToasts;

    public string $slug = '';

    public string $reponse = '';

    /** uuid de la réponse à laquelle on répond, le cas échéant. */
    public ?string $repondA = null;

    /** Motif de signalement en cours de saisie. */
    public string $motifSignalement = '';

    /** uuid de la réponse signalée, ou null pour le sujet lui-même. */
    public ?string $signaleUuid = null;

    /** Fenêtre de signalement ouverte ? */
    public bool $signalementOuvert = false;

    public string $detailSignalement = '';

    /** Verdict que la modération s'apprête à apposer. */
    public string $verdictChoisi = '';

    public function mount(string $thread): void
    {
        $this->slug = $thread;

        $this->sujet();
    }

    public function render(): View
    {
        $sujet = $this->sujet();

        return view('livewire.citizen.sujet', [
            'sujet' => $sujet,
            'reponses' => $this->reponses(),
            'monAvis' => $this->monAvis(),
            'avis' => AvisCommunaute::cases(),
            'motifs' => SignalementDeContenu::motifs(),
            'peutModerer' => $this->peutModerer(),
            'verdicts' => VerdictFactCheck::cases(),
        ]);
    }

    /**
     * Donne, change ou retire son avis.
     *
     * Changer d'avis est légitime — on découvre un élément, on se ravise. Ce
     * qui ne l'est pas, c'est de peser deux fois dans le même sondage.
     */
    public function voter(string $opinion, AnimationDuForum $forum): void
    {
        $avis = AvisCommunaute::tryFrom($opinion);

        if ($avis === null) {
            return;
        }

        $forum->voter($this->utilisateur(), $this->sujet(), $avis);

        unset($this->voteCourant);

        $this->toastSuccess('Votre avis est enregistré.');
    }

    public function repondre(AnimationDuForum $forum): void
    {
        $sujet = $this->sujet();

        if ($sujet->is_locked) {
            $this->toastWarning('Ce fil est clos : il n\'accepte plus de réponse.');

            return;
        }

        $this->validate(
            ['reponse' => ['required', 'string', 'min:2', 'max:5000']],
            ['reponse.required' => 'Écrivez quelque chose avant d\'envoyer.'],
        );

        $parent = $this->repondA !== null
            ? Reply::where('uuid', $this->repondA)->where('thread_id', $sujet->id)->first()
            : null;

        // Même filet que pour la publication d'un sujet : un blocage renvoie
        // une erreur de champ, jamais une page 500 qui perdrait le texte.
        try {
            $forum->repondre($this->utilisateur(), $sujet, $this->reponse, $parent);
        } catch (MessageBloque $blocage) {
            $this->addError('reponse', $blocage->getMessage());

            return;
        }

        $this->reset(['reponse', 'repondA']);

        $this->toastSuccess('Votre réponse est publiée.');
    }

    public function citer(?string $uuid = null): void
    {
        $this->repondA = $uuid;
    }

    public function aimer(string $slug, AnimationDuForum $forum): void
    {
        $forum->aimer($this->utilisateur(), $this->sujet());

        unset($this->mesJaimeSujet);
    }

    public function aimerReponse(string $uuid, AnimationDuForum $forum): void
    {
        $reponse = Reply::where('uuid', $uuid)->where('thread_id', $this->sujet()->id)->firstOrFail();

        $forum->aimer($this->utilisateur(), $reponse);

        unset($this->mesJaimeReponses);
    }

    /**
     * Ouvre la fenêtre de signalement.
     *
     * `null` signale le sujet ; un uuid signale une réponse. Un seul écran
     * pour les deux : dupliquer la modale produirait deux formulaires à tenir
     * à jour, et l'un finirait par oublier un motif.
     */
    public function ouvrirSignalement(?string $uuid = null): void
    {
        $this->signaleUuid = $uuid;
        $this->signalementOuvert = true;
        $this->reset(['motifSignalement', 'detailSignalement']);
        $this->resetErrorBag();
    }

    public function fermerSignalement(): void
    {
        $this->signalementOuvert = false;
    }

    public function signaler(SignalementDeContenu $signalements): void
    {
        $this->validate(
            ['motifSignalement' => ['required', 'string'], 'detailSignalement' => ['nullable', 'string', 'max:1000']],
            ['motifSignalement.required' => 'Choisissez un motif.'],
        );

        $cible = $this->signaleUuid === null
            ? $this->sujet()
            : Reply::where('uuid', $this->signaleUuid)->where('thread_id', $this->sujet()->id)->firstOrFail();

        $signalements->signaler($this->utilisateur(), $cible, $this->motifSignalement, $this->detailSignalement);

        $this->signalementOuvert = false;

        // Le message ne dit pas « supprimé » : un signalement déclenche un
        // examen, pas une sanction. Promettre le retrait serait mentir.
        $this->toastSuccess('Merci. Un modérateur va examiner ce contenu.');
    }

    /**
     * La personne peut-elle modérer ?
     *
     * La permission est portée par le COMPTE, pas par le guard : un modérateur
     * qui lit le forum depuis son espace citoyen doit pouvoir agir sur place,
     * sans repasser par le back-office pour verrouiller un fil qui dérape.
     */
    public function peutModerer(): bool
    {
        return $this->utilisateur()->can('forum.moderate');
    }

    public function basculerVerrou(): void
    {
        abort_unless($this->peutModerer(), 403);

        $sujet = $this->sujet();
        $verrouille = ! $sujet->is_locked;

        $sujet->forceFill(['is_locked' => $verrouille])->save();

        unset($this->sujetCharge);

        $this->toastSuccess($verrouille ? 'Fil clos.' : 'Fil rouvert.');
    }

    public function basculerEpingle(): void
    {
        abort_unless($this->peutModerer(), 403);

        $sujet = $this->sujet();
        $epingle = ! $sujet->is_pinned;

        $sujet->forceFill(['is_pinned' => $epingle])->save();

        unset($this->sujetCharge);

        $this->toastSuccess($epingle ? 'Sujet épinglé.' : 'Sujet désépinglé.');
    }

    /**
     * La personne peut-elle escalader ce sujet en fact-check ?
     *
     * La règle vit dans le service : l'auteur du sujet et les modérateurs, et
     * pas un sujet déjà transmis. La détailler ici en produirait une seconde
     * version, qui finirait par diverger de la première.
     */
    public function peutEscalader(): bool
    {
        return app(TransmissionAuLabo::class)->peutEscalader($this->utilisateur(), $this->sujet());
    }

    /**
     * Envoie le signalement au laboratoire.
     *
     * Gratuit et unique : c'est la plateforme qui décide de vérifier, et deux
     * analyses de la même rumeur pourraient rendre deux verdicts sur le même
     * fil.
     *
     * OUVERT À L'AUTEUR DU SUJET depuis le lot D : réservé aux modérateurs, le
     * pont entre le forum et le laboratoire — qui est le mécanisme central du
     * produit — restait enfoui dans un bandeau que la quasi-totalité des
     * visiteurs ne voit jamais. Celui qui a signalé la rumeur est précisément
     * celui qui veut savoir. Voir `docs/ARBITRAGES.md`, entrée 165.
     */
    public function transmettreAuLabo(TransmissionAuLabo $transmission): void
    {
        abort_unless($transmission->peutEscalader($this->utilisateur(), $this->sujet()), 403);

        $transmission->transmettre($this->utilisateur(), $this->sujet());

        unset($this->sujetCharge);

        $this->toastSuccess("Transmis au laboratoire. Le verdict s'affichera ici.");
    }

    /**
     * Appose le verdict officiel de la plateforme.
     *
     * Distinct de l'avis de la communauté, et il le reste à l'écran : une
     * majorité qui se trompe reste une majorité qui se trompe.
     */
    public function apposerLeVerdict(TransmissionAuLabo $transmission): void
    {
        abort_unless($this->peutModerer(), 403);

        $verdict = VerdictFactCheck::tryFrom($this->verdictChoisi);

        if ($verdict === null) {
            $this->toastWarning('Choisissez un verdict avant de le publier.');

            return;
        }

        $transmission->apposerLeVerdict($this->utilisateur(), $this->sujet(), $verdict);

        unset($this->sujetCharge);
        $this->verdictChoisi = '';

        $this->toastSuccess('Verdict officiel publié.');
    }

    #[Computed]
    public function voteCourant(): ?ThreadVote
    {
        return ThreadVote::where('thread_id', $this->sujet()->id)
            ->where('user_id', $this->utilisateur()->id)
            ->first();
    }

    private function monAvis(): ?AvisCommunaute
    {
        return $this->voteCourant()?->opinion;
    }

    /**
     * @return Collection<int, Reply>
     */
    private function reponses(): Collection
    {
        return Reply::with(['author:id,name', 'children.author:id,name'])
            ->where('thread_id', $this->sujet()->id)
            ->whereNull('parent_id')
            // Les réponses OFFICIELLES d'abord : c'est la parole de la
            // plateforme, et quelqu'un qui arrive sur un fil de quarante
            // messages doit la trouver sans dérouler.
            ->orderByDesc('is_official')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function mesJaimeSujet(): array
    {
        return ContentLike::where('user_id', $this->utilisateur()->id)
            ->where('likeable_type', (new Thread)->getMorphClass())
            ->pluck('likeable_id')
            ->all();
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function mesJaimeReponses(): array
    {
        return ContentLike::where('user_id', $this->utilisateur()->id)
            ->where('likeable_type', (new Reply)->getMorphClass())
            ->pluck('likeable_id')
            ->all();
    }

    public function aAime(Thread $sujet): bool
    {
        return in_array($sujet->id, $this->mesJaimeSujet(), true);
    }

    public function aAimeReponse(Reply $reponse): bool
    {
        return in_array($reponse->id, $this->mesJaimeReponses(), true);
    }

    #[Computed(persist: true)]
    public function sujetCharge(): Thread
    {
        return Thread::with(['author:id,name', 'category:id,name,slug', 'verification', 'escalatedBy:id,name'])
            ->where('slug', $this->slug)
            ->firstOrFail();
    }

    private function sujet(): Thread
    {
        return $this->sujetCharge();
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }

    /** Initiales d'affichage — évite de répéter la même chaîne dans la vue. */
    public function initiales(?string $nom): string
    {
        return Str::of($nom ?? '?')
            ->explode(' ')
            ->map(fn (string $mot): string => Str::substr($mot, 0, 1))
            ->take(2)
            ->implode('');
    }
}
