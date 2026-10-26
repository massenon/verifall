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
<div>

<x-premium.entete titre="Forum citoyen" :fil="['Forum citoyen']">
        Les sujets du débat, les plus actifs en tête. Pour traiter ce que les citoyens ont signalé, passez par <a href="{{ route('staff.content-reports') }}" class="text-brand-700 dark:text-brand underline">Signalements</a>.
    </x-premium.entete>

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
        <div class="flex-1 mb-0">
            <label for="fc-q" class="etiquette">Rechercher un sujet</label>
<input id="fc-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreCategorie" class="champ w-auto" aria-label="Filtrer par rubrique">
            <option value="">Rubrique : toutes</option>
            @foreach ($categories as $categorie)
                <option value="{{ $categorie->uuid }}">{{ $categorie->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par état">
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
                <button type="button" wire:click="reinitialiser" class="btn-second">
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
                        <p class="font-semibold text-encre dark:text-white mb-0">
                            @if ($sujet->is_pinned)
                                <x-lucide name="pin" class="w-4 h-4 text-amber-700 dark:text-amber-300" />
                            @endif
                            {{ Str::limit($sujet->title, 70) }}
                        </p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                            {{ $sujet->author?->name ?? 'Compte supprimé' }}
                        </p>
                    </td>
                    <td class="whitespace-nowrap text-sm text-encre-3 dark:text-nuit-3">
                        {{ $sujet->category?->name ?? 'Sans rubrique' }}
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        <span class="text-encre-2 dark:text-nuit-2">{{ $sujet->replies_count }} réponse(s)</span>
                        <span class="block text-xs text-encre-3 dark:text-nuit-3">
                            {{ $sujet->last_activity_at?->diffForHumans() ?? '—' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($sujet->official_verdict !== null)
                                <span class="pastille p-bon">{{ $sujet->official_verdict->libelle() }}</span>
                            @elseif ($sujet->escalated_at !== null)
                                {{-- Escaladé sans verdict : c'est la file réelle du
                                     fact-checking côté forum. --}}
                                <span class="pastille p-alerte">Transmis</span>
                            @endif
                            @if ($sujet->is_locked)
                                <span class="pastille p-neutre">Verrouillé</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutModerer)
                            <button type="button" wire:click="basculerLEpingle('{{ $sujet->uuid }}')"
                                    class="btn-icone" title="{{ $sujet->is_pinned ? 'Désépingler' : 'Épingler' }}">
                                <x-lucide name="pin" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="basculerLeVerrou('{{ $sujet->uuid }}')"
                                    class="btn-icone" title="{{ $sujet->is_locked ? 'Rouvrir aux réponses' : 'Verrouiller' }}">
                                <x-lucide :name="$sujet->is_locked ? 'lock_open' : 'lock'" class="w-4 h-4" />
                            </button>
                            @if ($sujet->official_verdict === null && $sujet->escalated_at === null)
                                <button type="button" wire:click="escalader('{{ $sujet->uuid }}')"
                                        class="btn-icone" title="Transmettre au fact-checking">
                                    <x-lucide name="file-check" class="w-4 h-4" />
                                </button>
                            @endif
                        @endif
                        @if ($peutSupprimer)
                            <button type="button" wire:click="demanderLaSuppression('{{ $sujet->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer le sujet">
                                <x-lucide name="trash-2" class="w-4 h-4" />
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
