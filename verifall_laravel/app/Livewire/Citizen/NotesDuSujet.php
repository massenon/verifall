<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Community\Services\NotesDeCommunaute;
use App\Core\Community\Services\ReputationCitoyenne;
use App\Core\Community\Services\ScoreDePontage;
use App\Core\Community\Services\SignalementDeContenu;
use App\Models\Thread;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Les notes de communauté d'un sujet.
 *
 * Composant IMBRIQUÉ dans la page d'un sujet, et non une page à lui : une note
 * n'a aucun sens détachée du fil qu'elle éclaire.
 *
 * Aucune logique métier ici — ni la règle de visibilité, ni le refus d'une note
 * sans source, ni le calcul du pont. Tout cela vit dans
 * {@see NotesDeCommunaute} et {@see ScoreDePontage}.
 * Ce fichier ne fait que saisir, appeler, et rendre.
 */
final class NotesDuSujet extends Component
{
    use InteractsWithToasts;

    public string $slug = '';

    /** Le texte de la note en cours de rédaction. */
    public string $texte = '';

    /** Les sources, une adresse par ligne — la saisie la plus simple qui tienne sur un téléphone. */
    public string $sourcesSaisies = '';

    public bool $formulaireOuvert = false;

    /** La file d'évaluation est REPLIÉE par défaut : c'est une démarche, pas une lecture. */
    public bool $fileOuverte = false;

    /** uuid de la note en cours de signalement, le cas échéant. */
    public ?string $noteSignalee = null;

    public string $motifSignalement = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(NotesDeCommunaute $notes): View
    {
        $sujet = $this->sujet();
        $lecteur = $this->lecteur();

        $groupes = $notes->visiblesPour($sujet, $lecteur);

        return view('livewire.citizen.notes-du-sujet', [
            'publiees' => $groupes['publiees'],
            'miennes' => $groupes['miennes'],
            'aEvaluer' => $groupes['a_evaluer'],
            'mesVotes' => $notes->mesVotes($sujet, $lecteur),
            'connecte' => $lecteur !== null,
            'reputation' => $this->reputation(),
            'motifs' => SignalementDeContenu::motifs(),
        ]);
    }

    public function ouvrirFormulaire(): void
    {
        $this->formulaireOuvert = true;
        $this->resetErrorBag();
    }

    public function fermerFormulaire(): void
    {
        $this->reset(['formulaireOuvert', 'texte', 'sourcesSaisies']);
        $this->resetErrorBag();
    }

    public function basculerLaFile(): void
    {
        $this->fileOuverte = ! $this->fileOuverte;
    }

    /**
     * Propose une note.
     *
     * Les refus (texte trop court, aucune source, note déjà proposée) sont
     * levés par le SERVICE sous forme de `ValidationException` : Livewire les
     * transforme en messages de champ sans qu'on ait à les dupliquer ici. Une
     * seconde liste de règles dans le composant finirait par diverger de la
     * première, et c'est toujours celle du composant qui est la plus laxiste.
     */
    public function proposer(NotesDeCommunaute $notes): void
    {
        $notes->proposer(
            $this->obligerLecteur(),
            $this->sujet(),
            $this->texte,
            $this->sources(),
        );

        $this->reset(['formulaireOuvert', 'texte', 'sourcesSaisies']);

        // Le message dit l'ATTENTE, jamais la publication : promettre une
        // publication qui dépend du vote d'autres personnes serait mentir, et
        // la déception se paie en notes qu'on n'écrit plus.
        $this->toastSuccess('Votre note est enregistrée. Elle sera publiée si des personnes aux avis opposés la jugent utile.');
    }

    public function voter(string $uuid, bool $utile, NotesDeCommunaute $notes): void
    {
        $note = $notes->parUuid($uuid);

        abort_if($note === null || (int) $note->thread_id !== (int) $this->sujet()->id, 404);

        $notes->voter($this->obligerLecteur(), $note, $utile);

        $this->toastSuccess('Votre évaluation est prise en compte.');
    }

    /**
     * Ouvre la fenêtre de signalement d'une note.
     *
     * Les notes en attente sont lues par toute personne connectée qui ouvre la
     * file d'évaluation : sans chemin de signalement, une note insultante
     * n'aurait aucune issue avant que l'algorithme ne la refuse — et
     * l'algorithme ne juge pas les insultes, il juge les ponts.
     *
     * Le service de signalement est POLYMORPHE et n'a rien demandé de
     * particulier : une note s'y signale comme un sujet ou une réponse.
     */
    public function ouvrirSignalement(string $uuid): void
    {
        $this->noteSignalee = $uuid;
        $this->motifSignalement = '';
        $this->resetErrorBag();
    }

    public function fermerSignalement(): void
    {
        $this->noteSignalee = null;
    }

    public function signaler(NotesDeCommunaute $notes, SignalementDeContenu $signalements): void
    {
        $this->validate(
            ['motifSignalement' => ['required', 'string']],
            ['motifSignalement.required' => 'Choisissez un motif.'],
        );

        $note = $notes->parUuid((string) $this->noteSignalee);

        abort_if($note === null || (int) $note->thread_id !== (int) $this->sujet()->id, 404);

        $signalements->signaler($this->obligerLecteur(), $note, $this->motifSignalement);

        $this->noteSignalee = null;

        // Un signalement déclenche un examen, pas une sanction : promettre le
        // retrait serait mentir.
        $this->toastSuccess('Merci. Un modérateur va examiner cette note.');
    }

    /**
     * La réputation du lecteur, ou `null` s'il n'est pas connecté.
     *
     * Calculée UNE fois par rendu, et pour la seule personne connectée. Celle
     * de chaque auteur de note n'est volontairement pas affichée : elle
     * coûterait une poignée de requêtes par note, et surtout elle pousserait à
     * juger l'auteur plutôt que la note — exactement ce que le pontage cherche
     * à éviter. Voir `docs/ARBITRAGES.md`, entrée 163.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function reputation(): ?array
    {
        $lecteur = $this->lecteur();

        if ($lecteur === null) {
            return null;
        }

        return app(ReputationCitoyenne::class)->pour($lecteur);
    }

    #[Computed(persist: true)]
    public function sujetCharge(): Thread
    {
        return Thread::where('slug', $this->slug)->firstOrFail();
    }

    /**
     * Les adresses saisies, une par ligne.
     *
     * @return list<string>
     */
    private function sources(): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R/', $this->sourcesSaisies) ?: []),
            static fn (string $ligne): bool => $ligne !== '',
        ));
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
