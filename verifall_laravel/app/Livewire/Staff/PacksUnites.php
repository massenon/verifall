<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\Payment;
use App\Models\UnitPack;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * « Packs d'unités » — la grille tarifaire, enfin modifiable.
 *
 * C'est la réserve n° 15 des arbitrages qui se ferme ici. Le prototype et la
 * spécification de paiement divergeaient d'un facteur deux à deux et demi sur
 * la valeur vendue ; les valeurs du prototype ont été appliquées faute de
 * mieux, mais le choix est commercial. Tant qu'il vivait dans un fichier de
 * configuration, le trancher demandait un développeur et un déploiement.
 *
 * Trois précautions qui ne sont pas décoratives :
 *
 *   · Le prix et la quantité ne sont JAMAIS lus dans la requête d'achat. Le
 *     client n'envoie qu'un code — sinon on achèterait soixante unités au prix
 *     de dix en modifiant un formulaire.
 *   · Un pack déjà acheté ne se supprime pas : effacer sa ligne rendrait
 *     illisibles les paiements qui le référencent. On le retire de la vente.
 *   · Chaque écriture est journalisée. Un changement de prix est une décision
 *     qui engage de l'argent : on doit pouvoir dire qui l'a prise, et quand.
 */
#[Layout('components.layouts.staff', ['title' => "Packs d'unités — Vérif'All"])]
final class PacksUnites extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    /** '' | 'actifs' | 'retires' */
    #[Url(as: 'etat', except: '')]
    public string $filtreEtat = '';

    /** UUID du pack en cours d'édition ; null = création. */
    public ?string $enEdition = null;

    public bool $formulaireOuvert = false;

    public string $code = '';

    public string $label = '';

    public ?int $units = null;

    public ?int $prix = null;

    public bool $enAvant = false;

    public bool $actif = true;

    /** UUID du pack dont la suppression est demandée. */
    public ?string $aSupprimer = null;

    public function mount(): void
    {
        $this->authorize('viewAny', UnitPack::class);
    }

    public function render(): View
    {
        return view('livewire.staff.packs-unites', [
            'packs' => $this->packs(),
            'indicateurs' => $this->indicateurs(),
            'packASupprimer' => $this->aSupprimer !== null
                ? UnitPack::where('uuid', $this->aSupprimer)->first()
                : null,
        ]);
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['recherche', 'filtreEtat'], true)) {
            $this->resetPage();
        }
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'filtreEtat']);
        $this->resetPage();
    }

    /* ═══════════════════════════════════════════════════ création / édition */

    public function ouvrirCreation(): void
    {
        $this->authorize('create', UnitPack::class);

        $this->reset(['enEdition', 'code', 'label', 'units', 'prix', 'enAvant']);
        $this->actif = true;
        $this->resetErrorBag();
        $this->formulaireOuvert = true;
    }

    public function ouvrirEdition(string $uuid): void
    {
        $pack = $this->pack($uuid);

        $this->authorize('update', $pack);

        $this->enEdition = $pack->uuid;
        $this->code = $pack->code;
        $this->label = $pack->label;
        $this->units = $pack->units;
        $this->prix = $pack->price_fcfa;
        $this->enAvant = $pack->is_featured;
        $this->actif = $pack->is_active;
        $this->resetErrorBag();
        $this->formulaireOuvert = true;
    }

    public function fermerFormulaire(): void
    {
        $this->formulaireOuvert = false;
        $this->resetErrorBag();
    }

    public function enregistrer(AuditLogService $journal): void
    {
        $pack = $this->enEdition !== null ? $this->pack($this->enEdition) : null;

        $this->authorize($pack !== null ? 'update' : 'create', $pack ?? UnitPack::class);

        $donnees = $this->validate([
            /*
             | Le code est l'identifiant que le client envoie. Minuscules,
             | chiffres et tirets : un code contenant une espace ou un accent
             | finirait encodé différemment selon le client, et un pack
             | introuvable est un achat perdu.
             */
            'code' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9\-]+$/',
                'unique:unit_packs,code'.($pack !== null ? ','.$pack->id : '')],
            'label' => ['required', 'string', 'max:120'],
            'units' => ['required', 'integer', 'min:1', 'max:100000'],
            // Le franc CFA n'a pas de subdivision en usage : un entier suffit,
            // et un flottant sur un montant est une erreur d'arrondi qui
            // attend son tour.
            'prix' => ['required', 'integer', 'min:1', 'max:10000000'],
        ], attributes: [
            'code' => 'code', 'label' => 'libellé',
            'units' => "nombre d'unités", 'prix' => 'prix',
        ]);

        $avant = $pack?->only(['code', 'label', 'units', 'price_fcfa', 'is_featured', 'is_active']) ?? [];

        DB::transaction(function () use (&$pack, $donnees): void {
            // Un seul pack mis en avant : deux « recommandés » côte à côte ne
            // recommandent rien.
            if ($this->enAvant) {
                UnitPack::where('is_featured', true)
                    ->when($pack !== null, fn (Builder $q) => $q->whereKeyNot($pack->id))
                    ->update(['is_featured' => false]);
            }

            $attributs = [
                'code' => $donnees['code'],
                'label' => $donnees['label'],
                'units' => $donnees['units'],
                'price_fcfa' => $donnees['prix'],
                'is_featured' => $this->enAvant,
                'is_active' => $this->actif,
            ];

            if ($pack === null) {
                $pack = UnitPack::create($attributs + [
                    'uuid' => (string) Str::uuid(),
                    'sort_order' => (int) UnitPack::max('sort_order') + 1,
                ]);

                return;
            }

            $pack->fill($attributs)->save();
        });

        $acteur = $this->acteur();

        if ($avant === []) {
            $journal->record(
                AuditLogService::CREATION, 'unit_pack', $acteur, $pack,
                null, $pack->only(['code', 'units', 'price_fcfa']), $pack->label,
            );
            $this->toastSuccess('Pack créé. Il est en vente dès maintenant.');
        } else {
            $journal->recordDiff(
                'unit_pack', $acteur, $pack, $avant,
                $pack->only(['code', 'label', 'units', 'price_fcfa', 'is_featured', 'is_active']),
                $pack->label,
            );
            $this->toastSuccess('Pack mis à jour.');
        }

        $this->formulaireOuvert = false;
    }

    /* ══════════════════════════════════════════════ retrait et suppression */

    /**
     * Retirer de la vente, ou y remettre.
     *
     * Réversible et sans perte : c'est le geste normal. La suppression, elle,
     * reste l'exception.
     */
    public function basculerActivite(string $uuid, AuditLogService $journal): void
    {
        $pack = $this->pack($uuid);

        $this->authorize('update', $pack);

        $avant = $pack->only(['is_active']);
        $pack->forceFill(['is_active' => ! $pack->is_active])->save();

        $journal->recordDiff('unit_pack', $this->acteur(), $pack, $avant,
            $pack->only(['is_active']), $pack->label);

        $this->toastSuccess($pack->is_active
            ? 'Pack remis en vente.'
            : 'Pack retiré de la vente. Les paiements passés restent lisibles.');
    }

    public function demanderSuppression(string $uuid): void
    {
        $this->aSupprimer = $uuid;
    }

    public function annulerSuppression(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(AuditLogService $journal): void
    {
        $pack = $this->pack((string) $this->aSupprimer);

        // La Policy refuse déjà un pack acheté ; le message, lui, doit
        // expliquer pourquoi plutôt que de renvoyer un 403 muet.
        if ($pack->payments()->exists()) {
            $this->aSupprimer = null;
            $this->toastError(
                'Ce pack a déjà été acheté : le supprimer rendrait illisibles '
                .'les paiements qui le référencent. Retirez-le de la vente.'
            );

            return;
        }

        $this->authorize('delete', $pack);

        $journal->record(
            AuditLogService::SUPPRESSION, 'unit_pack', $this->acteur(), $pack,
            $pack->only(['code', 'units', 'price_fcfa']), null, $pack->label,
        );

        $pack->delete();

        $this->aSupprimer = null;
        $this->toastSuccess('Pack supprimé.');
    }

    /* ═══════════════════════════════════════════════════════════ données */

    /**
     * @return LengthAwarePaginator<int, UnitPack>
     */
    private function packs(): LengthAwarePaginator
    {
        return UnitPack::query()
            ->withCount('payments')
            ->when($this->recherche !== '', function (Builder $q): void {
                $terme = '%'.mb_strtolower($this->recherche).'%';
                $q->where(fn (Builder $sous) => $sous
                    ->whereRaw('LOWER(code) LIKE ?', [$terme])
                    ->orWhereRaw('LOWER(label) LIKE ?', [$terme]));
            })
            ->when($this->filtreEtat === 'actifs', fn (Builder $q) => $q->where('is_active', true))
            ->when($this->filtreEtat === 'retires', fn (Builder $q) => $q->where('is_active', false))
            ->orderBy('sort_order')
            ->paginate(15);
    }

    /**
     * Les quatre indicateurs de l'en-tête.
     *
     * Tous COMPTÉS. Sur un écran qui sert à fixer des prix, un chiffre inventé
     * ferait prendre une décision commerciale sur une illusion.
     *
     * @return array{packs: int, actifs: int, encaisse: int, achats: int}
     */
    private function indicateurs(): array
    {
        $encaisse = Payment::where('status', 'succeeded')
            ->where('created_at', '>=', now()->subDays(30));

        return [
            'packs' => UnitPack::count(),
            'actifs' => UnitPack::where('is_active', true)->count(),
            'encaisse' => (int) $encaisse->clone()->sum('amount_fcfa'),
            'achats' => $encaisse->clone()->count(),
        ];
    }

    private function pack(string $uuid): UnitPack
    {
        return UnitPack::where('uuid', $uuid)->firstOrFail();
    }

    private function acteur(): User
    {
        /** @var User $acteur */
        $acteur = Auth::guard('staff')->user() ?? Auth::user();

        return $acteur;
    }
}
