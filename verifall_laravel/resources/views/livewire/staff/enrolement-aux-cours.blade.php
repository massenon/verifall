{{--
  | « Enrôlement aux Cours » (lot 7).
  |
  | L'écran qui répond aux deux questions les plus posées au support : « je me
  | suis inscrit et le cours n'apparaît pas », « j'ai payé et je n'ai pas
  | accès ». Personne ne pouvait le vérifier — `enrollments` n'avait aucun
  | lecteur côté administration.
  |
  | La PROGRESSION est affichée, jamais modifiable : elle se calcule sur les
  | leçons réellement terminées. Un champ modifiable la ferait mentir dès la
  | leçon suivante — et une progression forcée à 100 % déclencherait un
  | certificat que rien n'adosse.
--}}
<div>

<x-premium.entete titre="Enrôlement aux cours" :fil="['Enrôlement aux cours']">
        Qui suit quoi, et où il en est. Une inscription donnée ici ne débite aucune unité — elle est marquée comme telle pour qu'un accès offert reste distinguable d'un accès acheté.
        <x-slot:actions>
            @if ($peutInscrire)
            <button type="button" wire:click="ouvrirInscription" class="btn-primaire">
                <x-lucide name="user-check" class="w-4 h-4" />
                Donner un accès
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Inscriptions" trend="Tous cours confondus" />

        <x-kpi variant="staff" :value="number_format($indicateurs['en_cours'], 0, ',', ' ')"
               label="En cours" trend="Parcours commencés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['termines'], 0, ',', ' ')"
               label="Terminés" trend="Parcours achevés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['offerts'], 0, ',', ' ')"
               label="Accès offerts" trend="Cours payant, zéro unité débitée" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="ec-q" class="etiquette">Rechercher un inscrit</label>
<input id="ec-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreCours" class="champ w-auto" aria-label="Filtrer par cours">
            <option value="">Cours : tous</option>
            @foreach ($cours as $unCours)
                <option value="{{ $unCours->uuid }}">{{ Str::limit($unCours->title, 40) }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreStatut" class="champ w-auto" aria-label="Filtrer par statut">
            <option value="">Statut : tous</option>
            @foreach ($statuts as $unStatut)
                <option value="{{ $unStatut->value }}">{{ $unStatut->libelle() }}</option>
            @endforeach
        </select>
    </section>

    @if ($inscriptions->isEmpty())
        <x-empty-state variant="staff" icon="how_to_reg"
                       title="Aucune inscription"
                       description="Aucune inscription ne correspond à ces critères.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="btn-second">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$inscriptions->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Inscrit</th>
                <th scope="col">Cours</th>
                <th scope="col">Progression</th>
                <th scope="col">Payé</th>
                <th scope="col">Statut</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($inscriptions as $inscription)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $inscription->user?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $inscription->user?->email }}</p>
                    </td>
                    <td class="text-sm text-encre-2 dark:text-nuit-2">{{ Str::limit($inscription->course?->title ?? '—', 32) }}</td>
                    <td class="whitespace-nowrap">
                        {{-- Affichée, jamais modifiable : elle se calcule sur les leçons
                             réellement terminées. --}}
                        <span class="text-sm text-encre-2 dark:text-nuit-2">{{ $inscription->progress }} %</span>
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($inscription->paid_units > 0)
                            <span class="text-sm text-encre-2 dark:text-nuit-2">{{ $inscription->paid_units }} u.</span>
                        @elseif (($inscription->course?->cost_units ?? 0) > 0)
                            {{-- Cours payant, zéro unité : c'est un accès DONNÉ, et
                                 c'est le chiffre qu'on cherche quand les recettes ne
                                 tombent pas juste. --}}
                            <span class="pastille p-alerte">Offert</span>
                        @else
                            <span class="pastille p-bon">Gratuit</span>
                        @endif
                    </td>
                    <td>
                        <span class="pastille p-info">{{ $inscription->status?->libelle() ?? $inscription->status }}</span>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutRetirer)
                            <button type="button" wire:click="demanderLeRetrait('{{ $inscription->uuid }}')"
                                    class="btn-icone text-critique" title="Retirer l'inscription">
                                <x-lucide name="user-minus" class="w-4 h-4" />
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $inscriptions->links() }}</div>
    @endif

    @if ($peutInscrire)
        <x-offcanvas-form :open="$formulaireOuvert"
                          title="Donner un accès à un cours"
                          close="fermerLeTiroir">
            <form wire:submit="inscrire" class="space-y-4">
                <p class="text-sm text-encre-2 dark:text-nuit-2 mb-0">
                    Ce geste <span class="font-semibold">contourne le paiement</span> : aucune unité ne sera débitée,
                    et l'inscription sera marquée « offerte ».
                </p>

                <div>
                    <label for="ec-compte" class="etiquette">Adresse e-mail ou identifiant</label>
<input id="ec-compte" type="text" wire:model.live.debounce.500ms="compte" class="champ">
                    @error('compte') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                @if ($compte !== '')
                    @if ($compteTrouve !== null)
                        @php($cible = App\Models\User::query()->where('uuid', $compteTrouve)->first())
                        @if ($cible !== null)
                            <div class="rounded-lg border border-trait dark:border-nuit-trait bg-green-100 dark:bg-green-950 p-3">
                                <p class="text-sm font-semibold text-brand-700 dark:text-brand mb-0">{{ $cible->name }}</p>
                                <p class="text-xs text-brand-700 dark:text-brand mb-0">{{ $cible->email }}</p>
                            </div>
                        @endif
                    @else
                        <div class="rounded-lg border border-trait dark:border-nuit-trait bg-red-100 dark:bg-red-950 p-3">
                            <p class="text-sm text-critique mb-0">Aucun compte ne correspond à cette saisie.</p>
                        </div>
                    @endif
                @endif

                <div>
                    <label for="ec-cours" class="block text-sm font-medium text-encre dark:text-white mb-1">Cours</label>
                    <select id="ec-cours" wire:model="coursUuid" class="champ w-full">
                        <option value="">— Choisir —</option>
                        @foreach ($cours as $unCours)
                            <option value="{{ $unCours->uuid }}">
                                {{ $unCours->title }}{{ $unCours->cost_units > 0 ? ' ('.$unCours->cost_units.' u.)' : ' (gratuit)' }}
                            </option>
                        @endforeach
                    </select>
                    @error('coursUuid') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="ec-motif" class="etiquette">Justification</label>
<textarea id="ec-motif" wire:model="motif" rows="3" class="champ !h-auto py-2.5"></textarea>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                        Obligatoire : sans elle, un accès offert ressemble à un accès acheté dans six mois.
                    </p>
                    @error('motif') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Donner l'accès</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aRetirer !== null"
                    title="Retirer cette inscription ?"
                    description="La progression et l'historique de l'inscrit seront perdus. AUCUNE unité ne sera recréditée : le retrait et le remboursement sont deux gestes distincts, et le second passe par le journal des unités."
                    confirm="retirer"
                    cancel="annulerLeRetrait" />
</div>
