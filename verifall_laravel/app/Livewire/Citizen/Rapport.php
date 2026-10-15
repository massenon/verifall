<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\FactChecking\Services\QrCodeRapport;
use App\Core\FactChecking\Services\RenduDeSynthese;
use App\Core\FactChecking\Services\RetoursSurLeRapport;
use App\Models\CounterExpertise;
use App\Models\User;
use App\Models\Verification;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * « Rapport de vérification » — page rapport.html du prototype.
 *
 * Tout ce qui est affiché vient du rapport enregistré : verdict, synthèse,
 * moteurs mobilisés, sources consultées. Rien n'est recalculé ici — un écran
 * qui refait le calcul pourrait afficher autre chose que ce qui a été décidé,
 * et c'est exactement l'incohérence que le pipeline s'emploie à empêcher.
 */
#[Layout('components.layouts.citizen', ['title' => "Rapport de vérification · Vérif'All"])]
final class Rapport extends Component
{
    use InteractsWithToasts;

    public string $uuid = '';

    /** Le tiroir « Demander une contre-expertise » est-il ouvert ? */
    public bool $tiroirContestation = false;

    /** Le motif saisi. Obligatoire : une demande vide ne s'instruit pas. */
    public string $motifContestation = '';

    /**
     * Enregistre l'avis d'utilité.
     *
     * Le vote se CHANGE : recliquer sur l'autre pouce remplace l'avis, il ne
     * s'ajoute pas. Sans cela, l'indicateur mesurerait l'agacement plutôt que
     * la satisfaction.
     */
    public function noter(bool $utile, RetoursSurLeRapport $retours): void
    {
        $retours->noter($this->verification(), $this->utilisateur(), $utile);

        $this->toastSuccess($utile
            ? 'Merci — c’est noté.'
            : 'Merci. Si le verdict vous paraît faux, demandez une contre-expertise.');
    }

    public function ouvrirLaContestation(): void
    {
        $this->tiroirContestation = true;
    }

    public function fermerLaContestation(): void
    {
        $this->tiroirContestation = false;
        $this->motifContestation = '';
        $this->resetValidation();
    }

    /**
     * Ouvre une contre-expertise.
     *
     * La validation est ici ET dans le service : l'écran donne le message le
     * plus utile au bon endroit, le service garantit la règle même appelé
     * depuis l'API mobile.
     */
    public function demanderUnExpert(RetoursSurLeRapport $retours): void
    {
        $this->validate([
            'motifContestation' => ['required', 'string', 'min:20', 'max:'.RetoursSurLeRapport::MOTIF_MAXIMUM],
        ], attributes: ['motifContestation' => 'motif']);

        try {
            $retours->demanderUneContreExpertise(
                $this->verification(),
                $this->utilisateur(),
                $this->motifContestation,
            );
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());
            $this->fermerLaContestation();

            return;
        }

        $this->fermerLaContestation();

        $this->toastSuccess(
            'Demande envoyée. Un journaliste reprend le dossier ; vous serez prévenu de l’issue.'
        );
    }

    public function mount(string $verification): void
    {
        $this->uuid = $verification;
        $this->verification();
    }

    public function render(RenduDeSynthese $rendu, QrCodeRapport $qr): View
    {
        $verification = $this->verification();
        $rapport = $verification->report;

        // Les moteurs dans l'ordre où ils ont répondu : c'est la
        // chronologie réelle, et elle rend la méthode lisible.
        $moteurs = $verification->engineRuns()->orderBy('id')->get();

        return view('livewire.citizen.rapport', [
            'verification' => $verification,
            'rapport' => $rapport,
            'moteurs' => $moteurs,

            /*
             | L'indice de haine et le compte des moteurs dissidents étaient
             | calculés dans la vue, en `@php`. Ce n'est pas qu'une question de
             | règle : `@php(...)` en ligne et `@php … @endphp` ne peuvent PAS
             | coexister dans un même gabarit — Blade apparie le premier `@php`
             | avec le premier `@endphp` et met tout l'intervalle en bloc brut.
             | Le symptôme est un « Undefined variable » à cent lignes de la
             | cause, et il a déjà coûté une heure sur ce fichier.
             */
            'haine' => $rapport->indiceHaine(),

            /*
             | La synthèse rédigée par le moteur le mieux étayé, rendue en HTML
             | SÛR. Le gabarit passait `Str::markdown()` sans options dans un
             | `{!! !!}` : le HTML écrit par le modèle entrait tel quel dans la
             | page. Voir RenduDeSynthese.
             |
             | Le verdict accompagne le texte : c'est lui qui décide quel
             | raisonnement est affichable. Un texte qui contredit le verdict
             | saute au profit du candidat suivant. Voir `pour()`.
             |
             | `verdict` est NULLABLE dès qu'un rapport est MIXTE (contrat
             | V-8) : il n'existe alors aucun verdict unique contre lequel
             | confronter un raisonnement de moteur. `RenduDeSynthese::pour()`
             | n'a pas sa place ici — le repli sur `$rapport->summary`, déjà
             | prévu par `corps.blade.php` quand `synthese` vaut `null`, porte
             | l'énumération par claim composée côté serveur.
             */
            'synthese' => $rapport->verdict !== null ? ($rendu->pour($moteurs, $rapport->verdict) ?: null) : null,

            /*
             | Les moteurs qui ont conclu autrement. Comptés ici parce que la
             | phrase qui les annonce est au pluriel variable, et qu'un
             | `trans_choice` a besoin d'un entier, pas d'une collection.
             |
             | Sur un rapport MIXTE, « dissident par rapport à quel verdict ? »
             | n'a pas de réponse unique : chaque claim a le sien. Compter zéro
             | plutôt que de planter.
             */
            'dissidents' => $rapport->verdict !== null
                ? $moteurs->filter(fn ($m): bool => ! $m->failed && $m->verdict !== null && $m->verdict !== $rapport->verdict)->count()
                : 0,
            // Les sources les plus fiables d'abord : un lecteur qui s'arrête à
            // la troisième ligne doit avoir vu les meilleures.
            'sources' => $verification->evidences()->orderByDesc('reliability')->get(),

            // L'avis déjà donné, pour que le pouce choisi reste visible au
            // rechargement : un vote qui disparaît donne l'impression de ne
            // pas avoir été pris.
            'avis' => app(RetoursSurLeRapport::class)->avisDe($verification, $this->utilisateur()),

            // Une contestation déjà ouverte remplace le bouton par son état :
            // proposer « Demander un expert » à quelqu'un qui vient de le
            // faire l'inviterait à recommencer.
            /*
             | Le lien et le message de partage sont calculés ICI, et non dans
             | un bloc `@php … @endphp` de la vue.
             |
             | Ce n'est pas qu'une question de propreté, et le piège a coûté une
             | heure : **Blade apparie le PREMIER `@php` du fichier — y compris
             | la forme en ligne `@php(...)`, présente deux fois dans ce
             | gabarit — avec le premier `@endphp`**, et met tout l'intervalle
             | en bloc BRUT. Plus rien ne s'y compile : les `{{ }}` sortent en
             | clair, les directives restent littérales, et le seul symptôme est
             | un « Undefined variable » à cent lignes de la cause.
             |
             | Un gabarit ne peut donc pas mélanger les deux formes. Ces valeurs
             | vivent de toute façon au bon endroit : la règle 1 veut qu'aucune
             | logique ne tienne dans une vue.
             */
            /*
             | DEUX liens, et la distinction compte.
             |
             | `lienDuRapport` reste l'adresse de CETTE page, pour qui y revient
             | depuis son historique. `lienPublic` est ce qu'on PARTAGE et ce que
             | porte le QR du rapport imprimé : celui qui le reçoit n'a pas de
             | compte, et `citizen.report` lui rendrait un écran de connexion —
             | le démenti n'arriverait jamais là où circule la rumeur.
             */
            'lienDuRapport' => route('citizen.report', ['verification' => $verification->uuid]),
            'lienPublic' => $qr->adresse($verification),

            /*
             | Le QR de contrôle, pour le pied de page IMPRIMÉ. `null` tant que
             | le rapport n'est pas publié : un code qui mène à une page
             | introuvable vaut moins que pas de code du tout.
             */
            'qrCode' => $qr->siPublie($verification),

            // La borne du champ vient du service, pas d'un nombre recopié dans
            // la vue : les deux ne peuvent pas diverger.
            'motifMaximum' => RetoursSurLeRapport::MOTIF_MAXIMUM,

            'contestation' => CounterExpertise::query()
                ->where('verification_id', $verification->getKey())
                ->latest('id')
                ->first(),
        ]);
    }

    private function verification(): Verification
    {
        $verification = Verification::with('report')
            ->where('uuid', $this->uuid)
            ->where('user_id', $this->utilisateur()->id)
            ->firstOrFail();

        // Pas encore de rapport : on renvoie vers l'écran d'attente plutôt que
        // d'afficher une page vide.
        if ($verification->report === null) {
            $this->redirectRoute('citizen.verification', ['verification' => $verification->uuid]);
        }

        return $verification;
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
