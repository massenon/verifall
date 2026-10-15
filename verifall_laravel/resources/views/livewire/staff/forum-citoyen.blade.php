{{--
  | « Forum Citoyen » (lot 8).
  |
  | Ce n'est PAS la file de signalements — celle-là est traitée par l'écran
  | « Signalements ». Ici, un modérateur parcourt le forum et agit sur ce qu'il
  | lit, souvent avant que quiconque ait signalé quoi que ce soit. Les deux
  | écrans sont complémentaires ; les confondre ferait passer à côté de ce que
  | personne ne signale.
  |
  | Verrouiller n'est pas supprimer : un sujet verrouillé reste LISIBLE.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Forum citoyen</span>
    </nav>

    <header class="mb-6">
        <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Forum citoyen</h1>
        <p class="text-[#64748B] text-sm mb-0">
            Les sujets du débat, les plus actifs en tête. Pour traiter ce que les citoyens ont signalé,
            passez par <a href="{{ route('staff.content-reports') }}" class="text-[#059669] underline">Signalements</a>.
        </p>
    </header>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['sujets'], 0, ',', ' ')"
               label="Sujets" trend="Tout le forum" />

        <x-kpi variant="staff" :value="number_format($indicateurs['verrouilles'], 0, ',', ' ')"
               label="Verrouillés" trend="Lisibles, fermés aux réponses" />

        <x-kpi variant="staff" :value="number_format($indicateurs['escalades'], 0, ',', ' ')"
               label="En attente de verdict" trend="Transmis au fact-checking" />

        <x-kpi variant="staff" :value="number_format($indicateurs['tranches'], 0, ',', ' ')"
               label="Tranchés" trend="Verdict officiel apposé" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="fc-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="fc-q">Rechercher un sujet</label>
        </div>

        <select wire:model.live="filtreCategorie" class="st-input w-auto" aria-label="Filtrer par rubrique">
            <option value="">Rubrique : toutes</option>
            @foreach ($categories as $categorie)
                <option value="{{ $categorie->uuid }}">{{ $categorie->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="verrouilles">Verrouillés</option>
            <option value="epingles">Épinglés</option>
            <option value="escalades">En attente de verdict</option>
            <option value="sans-verdict">Sans verdict</option>
        </select>
    </section>

    @if ($sujets->isEmpty())
        <x-empty-state variant="staff" icon="forum"
                       title="Aucun sujet"
                       description="Aucun sujet ne correspond à ces critères. Un forum vide est un état correct, pas une panne.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$sujets->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Sujet</th>
                <th scope="col">Rubrique</th>
                <th scope="col">Activité</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($sujets as $sujet)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">
                            @if ($sujet->is_pinned)
                                <span class="material-symbols-rounded !text-[16px] text-[#D97706]" aria-hidden="true">push_pin</span>
                            @endif
                            {{ Str::limit($sujet->title, 70) }}
                        </p>
                        <p class="text-xs text-[#64748B] mb-0">
                            {{ $sujet->author?->name ?? 'Compte supprimé' }}
                        </p>
                    </td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        {{ $sujet->category?->name ?? 'Sans rubrique' }}
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        <span class="text-[#334155]">{{ $sujet->replies_count }} réponse(s)</span>
                        <span class="block text-xs text-[#64748B]">
                            {{ $sujet->last_activity_at?->diffForHumans() ?? '—' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($sujet->official_verdict !== null)
                                <span class="st-badge st-badge-pub">{{ $sujet->official_verdict->libelle() }}</span>
                            @elseif ($sujet->escalated_at !== null)
                                {{-- Escaladé sans verdict : c'est la file réelle du
                                     fact-checking côté forum. --}}
                                <span class="st-badge st-badge-warn">Transmis</span>
                            @endif
                            @if ($sujet->is_locked)
                                <span class="st-badge st-badge-arch">Verrouillé</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutModerer)
                            <button type="button" wire:click="basculerLEpingle('{{ $sujet->uuid }}')"
                                    class="st-icnbtn" title="{{ $sujet->is_pinned ? 'Désépingler' : 'Épingler' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">push_pin</span>
                            </button>
                            <button type="button" wire:click="basculerLeVerrou('{{ $sujet->uuid }}')"
                                    class="st-icnbtn" title="{{ $sujet->is_locked ? 'Rouvrir aux réponses' : 'Verrouiller' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $sujet->is_locked ? 'lock_open' : 'lock' }}</span>
                            </button>
                            @if ($sujet->official_verdict === null && $sujet->escalated_at === null)
                                <button type="button" wire:click="escalader('{{ $sujet->uuid }}')"
                                        class="st-icnbtn" title="Transmettre au fact-checking">
                                    <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">fact_check</span>
                                </button>
                            @endif
                        @endif
                        @if ($peutSupprimer)
                            <button type="button" wire:click="demanderLaSuppression('{{ $sujet->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer le sujet">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $sujets->links() }}</div>
    @endif

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer ce sujet ?"
                    :description="$sujetVise !== null
                        ? 'La discussion entière disparaît, y compris ses '.$sujetVise->replies()->count().' réponse(s) — celles de bonne foi comprises. Pour mettre un terme au débat en laissant tout lisible, VERROUILLEZ plutôt.'
                        : ''"
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
