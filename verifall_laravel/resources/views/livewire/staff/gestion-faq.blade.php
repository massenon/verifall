{{--
  | « Gestion FAQ » (lot 9).
  |
  | L'entrée du menu portait ce nom et pointait sur `route('public.faq')` : on
  | cliquait pour gérer la FAQ et on atterrissait sur la page publique, en
  | lecture. Même famille que les deux défauts du lot 2 — et comme eux, le
  | correctif n'était pas un `href` : l'écran n'existait pas.
  |
  | Les DEUX LANGUES s'éditent ensemble. Les séparer garantirait qu'on corrige
  | la française et qu'on oublie l'anglaise — et une FAQ à moitié corrigée est
  | pire qu'une FAQ pas corrigée, parce qu'on la croit à jour.
--}}
<div>

<x-premium.entete titre="Gestion de la FAQ" :fil="['Gestion de la FAQ']">
        Les questions fréquentes, dans les deux langues du site. Une question sans traduction anglaise s'affiche en français sur la version anglaise — une FAQ incomplète vaut mieux qu'une FAQ trouée.
        <x-slot:actions>
            @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvelle question
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Questions" trend="En français" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publiees'], 0, ',', ' ')"
               label="Publiées" trend="Visibles sur le site" />

        <x-kpi variant="staff" :value="number_format($indicateurs['traduites'], 0, ',', ' ')"
               label="Traduites" trend="Version anglaise écrite" />

        <x-kpi variant="staff" :value="number_format($indicateurs['sans_rubrique'], 0, ',', ' ')"
               label="Sans rubrique" trend="À classer" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="fq-q" class="etiquette">Rechercher une question, une réponse</label>
<input id="fq-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreCategorie" class="champ w-auto" aria-label="Filtrer par rubrique">
            <option value="">Rubrique : toutes</option>
            @foreach ($categories as $uneCategorie)
                <option value="{{ $uneCategorie }}">{{ $uneCategorie }}</option>
            @endforeach
        </select>
    </section>

    @if ($questions->isEmpty())
        <x-empty-state variant="staff" icon="quiz"
                       title="Aucune question"
                       description="La FAQ publique sera vide. Chaque question ajoutée ici y paraît immédiatement, une fois publiée.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="btn-second">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$questions->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Question</th>
                <th scope="col">Rubrique</th>
                <th scope="col">Anglais</th>
                <th scope="col">Ordre</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($questions as $question)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ Str::limit($question->question, 80) }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ Str::limit(strip_tags($question->answer), 90) }}</p>
                    </td>
                    <td class="whitespace-nowrap text-sm text-encre-3 dark:text-nuit-3">{{ $question->category ?? '—' }}</td>
                    <td class="whitespace-nowrap">
                        @if (in_array($question->group_key, $traduites, true))
                            <span class="pastille p-bon">Traduite</span>
                        @else
                            {{-- Ce n'est pas une erreur : la page anglaise se replie sur
                                 le français. C'est un reste-à-faire, et il se voit. --}}
                            <span class="text-xs text-encre-3 dark:text-nuit-3">À traduire</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-encre-3 dark:text-nuit-3">{{ $question->sort_order }}</td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$question->is_published ? 'publie' : 'brouillon'"
                                        :label="$question->is_published ? 'Publiée' : 'Masquée'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLaPublication('{{ $question->group_key }}')"
                                    class="btn-icone" title="{{ $question->is_published ? 'Masquer' : 'Publier' }}">
                                <x-lucide :name="$question->is_published ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $question->group_key }}')"
                                    class="btn-icone" title="Modifier (les deux langues)">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $question->group_key }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $questions->links() }}</div>
    @endif

    @if ($peutGerer)
        <x-offcanvas-form :open="$formulaireOuvert"
                          :title="$enEdition === null ? 'Nouvelle question' : 'Modifier la question'"
                          close="fermerLeTiroir">
            <form wire:submit="enregistrer" class="space-y-4">
                <div>
                    <label for="fq-cat" class="etiquette">Rubrique (facultatif)</label>
<input id="fq-cat" type="text" wire:model="categorie" list="fq-cats" class="champ">
                    <datalist id="fq-cats">
                        @foreach ($categories as $uneCategorie)
                            <option value="{{ $uneCategorie }}"></option>
                        @endforeach
                    </datalist>
                    @error('categorie') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="carte p-3">
                    <p class="text-xs font-bold text-encre-3 dark:text-nuit-3 mb-2">FRANÇAIS</p>

                    <div>
                        <label for="fq-qfr" class="etiquette">Question</label>
<textarea id="fq-qfr" wire:model="questionFr" rows="2" class="champ !h-auto py-2.5"></textarea>
                        @error('questionFr') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="fq-rfr" class="etiquette">Réponse</label>
<textarea id="fq-rfr" wire:model="reponseFr" rows="4" class="champ !h-auto py-2.5"></textarea>
                        @error('reponseFr') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="carte p-3">
                    <p class="text-xs font-bold text-encre-3 dark:text-nuit-3 mb-2">ENGLISH — facultatif</p>

                    <div>
                        <label for="fq-qen" class="etiquette">Question</label>
<textarea id="fq-qen" wire:model="questionEn" rows="2" class="champ !h-auto py-2.5"></textarea>
                        @error('questionEn') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="fq-ren" class="etiquette">Answer</label>
<textarea id="fq-ren" wire:model="reponseEn" rows="4" class="champ !h-auto py-2.5"></textarea>
                        @error('reponseEn') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-2 mb-0">
                        Laissés vides, la version anglaise du site affiche le français.
                        Vider une traduction existante la supprime.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="mb-0">
                        <label for="fq-ordre" class="etiquette">Ordre d'affichage</label>
<input id="fq-ordre" type="number" min="0" wire:model="ordre" class="champ">
                        @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model="publiee" class="mt-0.5">
                        <span class="text-sm text-encre-2 dark:text-nuit-2">Publiée sur le site</span>
                    </label>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette question ?"
                    description="La question disparaît dans TOUTES les langues. Pour la retirer du site sans l'effacer, masquez-la plutôt."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
