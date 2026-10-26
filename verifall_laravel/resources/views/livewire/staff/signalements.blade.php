{{--
  | Signalements — la file des vérifications citoyennes.
  |
  | Les plus ANCIENNES en tête, à l'inverse des autres listes du back-office :
  | cet écran sert à vider une file, et trier par date décroissante enterrerait
  | ce qui attend depuis le plus longtemps.
--}}
<div>

    <x-premium.entete titre="Signalements">
        Ce que les citoyens ont soumis à vérification. Les plus anciens d'abord — ce sont ceux dont quelqu'un attend encore la réponse.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($chiffres['total'], 0, ',', ' ')" label="Total" />
        <x-kpi variant="staff" :value="number_format($chiffres['en_attente'], 0, ',', ' ')"
               label="En attente d'analyse" />
        {{-- Le chiffre qui commande : une vérification que personne n'a prise
             n'avance pas, quel que soit son statut technique. --}}
        <x-kpi variant="staff" :value="number_format($chiffres['non_assignees'], 0, ',', ' ')"
               label="Non assignées" />
        <x-kpi variant="staff" :value="number_format($chiffres['revue_humaine'], 0, ',', ' ')"
               label="Revue humaine" />
    </x-kpi-grid>

    <section class="my-5 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0" style="min-width: 220px;">
            <label for="sig-q" class="etiquette">Rechercher dans l'affirmation ou le contexte</label>
<input id="sig-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreStatut" class="champ w-auto" aria-label="Filtrer par statut">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $statut)
                <option value="{{ $statut->value }}">{{ $statut->libelle() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreAssignation" class="champ w-auto" aria-label="Filtrer par prise en charge">
            <option value="">Toutes</option>
            <option value="non-assignees">Non assignées</option>
            <option value="a-moi">Confiées à moi</option>
        </select>

        @if ($recherche !== '' || $filtreStatut !== '' || $filtreAssignation !== '')
            <button type="button" wire:click="reinitialiser" class="text-sm text-brand-700 dark:text-brand font-semibold underline">
                Réinitialiser
            </button>
        @endif
    </section>

    @if ($lignes->total() === 0)
        <x-empty-state variant="staff" icon="fact_check"
                       title="La file est vide"
                       description="Aucune vérification ne correspond à ces filtres." />
    @else
        <x-data-table variant="staff" :count="$lignes->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Contenu</th>
                <th scope="col">Type</th>
                <th scope="col">Soumis par</th>
                <th scope="col">Statut</th>
                <th scope="col">Assigné à</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($lignes as $ligne)
                <tr wire:key="sig-{{ $ligne->uuid }}">
                    <td style="max-width: 320px;">
                        <p class="font-semibold text-encre dark:text-white mb-0" style="word-break: break-word;">
                            {{ Str::limit((string) $ligne->claim, 120) }}
                        </p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                            {{ $ligne->created_at?->diffForHumans() }}
                            @if ($ligne->theme) · {{ $ligne->theme }} @endif
                        </p>
                    </td>

                    <td class="text-sm text-encre-2 dark:text-nuit-2 whitespace-nowrap">{{ $ligne->type }}</td>

                    <td>
                        <p class="text-sm text-encre-2 dark:text-nuit-2 mb-0">
                            {{-- Une soumission d'invité n'a pas de compte : le dire
                                 plutôt que d'afficher un tiret, qui se lirait comme
                                 une donnée manquante. --}}
                            {{ $ligne->user?->name ?? 'Visiteur non connecté' }}
                        </p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $ligne->user?->email }}</p>
                    </td>

                    <td class="whitespace-nowrap">
                        <span class="pastille">{{ $ligne->status }}</span>
                        {{--
                          | `verdict` est NULLABLE dès qu'un rapport est MIXTE
                          | (contrat V-8) : la garde sur `->verdict` seul ne
                          | plantait pas ici, mais masquait l'information en
                          | silence — un modérateur voyait une ligne sans
                          | aucun verdict sur un rapport pourtant tranché
                          | claim par claim. On affiche « Mixte » au lieu de
                          | se taire.
                        --}}
                        @if ($ligne->report?->estMixte())
                            <span class="block text-xs text-encre-3 dark:text-nuit-3 mt-1">
                                Verdict : {{ $ligne->report->composition_status->libelle() }}
                            </span>
                        @elseif ($ligne->report?->verdict)
                            <span class="block text-xs text-encre-3 dark:text-nuit-3 mt-1">
                                Verdict : {{ $ligne->report->verdict->libelle() }}
                            </span>
                        @endif
                    </td>

                    <td class="text-sm whitespace-nowrap">
                        @if ($ligne->assignee)
                            {{ $ligne->assignee->name }}
                        @else
                            <span class="text-encre-3 dark:text-nuit-3">Personne</span>
                        @endif
                    </td>

                    <td class="text-right whitespace-nowrap">
                        @if ($peutAssigner)
                            <button type="button" wire:click="ouvrirLAssignation('{{ $ligne->uuid }}')"
                                    class="text-xs font-bold text-brand-700 dark:text-brand">Assigner</button>
                        @endif

                        {{--
                          | `$ligne->report?->verdict` exclut À DESSEIN un
                          | rapport MIXTE : `VerifiedFact::verdict` est un
                          | scalaire NOT NULL, et verser un rapport à
                          | plusieurs verdicts dans la base de connaissances
                          | exigerait une décision éditoriale qu'aucun
                          | contrat ne couvre encore — pas une correction à
                          | faire ici.
                        --}}
                        @if ($peutPublier && $ligne->report?->verdict)
                            <span class="text-encre-3 dark:text-nuit-3" aria-hidden="true">·</span>
                            <button type="button" wire:click="publier('{{ $ligne->uuid }}')"
                                    wire:loading.attr="disabled"
                                    class="text-xs font-bold text-brand-700 dark:text-brand">Verser à la base</button>
                        @endif

                        @if ($peutRetirer)
                            <span class="text-encre-3 dark:text-nuit-3" aria-hidden="true">·</span>
                            <button type="button" wire:click="ouvrirLeRetrait('{{ $ligne->uuid }}')"
                                    class="text-xs font-bold text-critique">Retirer</button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $lignes->links() }}</div>

        {{-- Dit une fois, en bas, plutôt que répété sur chaque bouton : « Verser »
             n'est pas « publier », et laisser croire l'inverse ferait d'un verdict
             automatique une référence opposable. --}}
        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-3 mb-0">
            « Verser à la base » ajoute la vérification au fonds de connaissances en
            <strong>brouillon</strong> : les analyses suivantes y trouveront le même contenu sans
            rappeler l'IA, une fois qu'un relecteur l'aura publiée. « Retirer » est réversible et
            demande un motif.
        </p>
    @endif

    {{-- ═══════════════════════════════════════════════════════ assigner --}}
    @if ($aAssigner)
        <div class="voile !z-[79]" wire:click="fermerLesModales" aria-hidden="true"></div>

        {{-- `z-index` EN LIGNE : le build Tailwind est figé et ne contient aucune
             valeur arbitraire ; `z-50` laisserait la modale sous le fond du
             tiroir staff (z-79). Même raison que dans `<x-delete-modal>`. --}}
        <div class="carte fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6"
             style="z-index:90" role="dialog" aria-modal="true" aria-labelledby="assign-titre">

            <h2 id="assign-titre" class="text-lg font-titre font-bold text-encre dark:text-white mb-2">
                Confier cette vérification
            </h2>

            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-4">
                Seuls les comptes qui peuvent rendre un verdict apparaissent ici : confier à
                quelqu'un d'autre créerait une file qui n'avance jamais.
            </p>

            <select wire:model="destinataireUuid" class="champ w-full mb-4" aria-label="Destinataire">
                <option value="">Choisir une personne…</option>
                @foreach ($destinataires as $personne)
                    <option value="{{ $personne->uuid }}">{{ $personne->name }}</option>
                @endforeach
            </select>

            <div class="flex gap-2 justify-end">
                <button type="button" wire:click="fermerLesModales" class="btn-second">Annuler</button>
                <button type="button" wire:click="assigner" wire:loading.attr="disabled"
                        class="btn-primaire" @disabled($destinataireUuid === '')>Confier</button>
            </div>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════ retirer --}}
    @if ($aRetirer)
        <div class="voile !z-[79]" wire:click="fermerLesModales" aria-hidden="true"></div>

        <div class="carte fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6"
             style="z-index:90" role="alertdialog" aria-modal="true" aria-labelledby="retrait-titre">

            <h2 id="retrait-titre" class="text-lg font-titre font-bold text-encre dark:text-white mb-2">
                Retirer cette vérification ?
            </h2>

            <p class="text-sm text-encre-2 dark:text-nuit-2 mb-4">
                Elle disparaîtra de la file <em>et de l'historique de la personne qui l'a
                demandée</em>. Le retrait est réversible, et le motif est enregistré au journal.
            </p>

            <form wire:submit="retirer">
                <div class="mb-1">
                    <label for="retrait-motif" class="etiquette">Motif du retrait *</label>
<textarea id="retrait-motif" wire:model.blur="motifDeRetrait" rows="3" class="champ !h-auto py-2.5"></textarea>
                </div>
                @error('motifDeRetrait')
                    <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p>
                @enderror

                <div class="flex gap-2 justify-end mt-5">
                    <button type="button" wire:click="fermerLesModales" class="btn-second">Annuler</button>
                    <button type="submit" wire:loading.attr="disabled" class="btn-primaire">Retirer</button>
                </div>
            </form>
        </div>
    @endif
</div>
