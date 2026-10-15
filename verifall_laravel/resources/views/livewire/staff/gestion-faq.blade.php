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
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Gestion de la FAQ</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Gestion de la FAQ</h1>
            <p class="text-[#64748B] text-sm mb-0">
                Les questions fréquentes, dans les deux langues du site.
                Une question sans traduction anglaise s'affiche en français sur la version anglaise —
                une FAQ incomplète vaut mieux qu'une FAQ trouée.
            </p>
        </div>

        @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouvelle question
            </button>
        @endif
    </header>

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
        <div class="ffield flex-1 mb-0">
            <input id="fq-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="fq-q">Rechercher une question, une réponse</label>
        </div>

        <select wire:model.live="filtreCategorie" class="st-input w-auto" aria-label="Filtrer par rubrique">
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
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
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
                        <p class="font-semibold text-[#0F172A] mb-0">{{ Str::limit($question->question, 80) }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ Str::limit(strip_tags($question->answer), 90) }}</p>
                    </td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">{{ $question->category ?? '—' }}</td>
                    <td class="whitespace-nowrap">
                        @if (in_array($question->group_key, $traduites, true))
                            <span class="st-badge st-badge-pub">Traduite</span>
                        @else
                            {{-- Ce n'est pas une erreur : la page anglaise se replie sur
                                 le français. C'est un reste-à-faire, et il se voit. --}}
                            <span class="text-xs text-[#64748B]">À traduire</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-[#64748B]">{{ $question->sort_order }}</td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$question->is_published ? 'publie' : 'brouillon'"
                                        :label="$question->is_published ? 'Publiée' : 'Masquée'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLaPublication('{{ $question->group_key }}')"
                                    class="st-icnbtn" title="{{ $question->is_published ? 'Masquer' : 'Publier' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $question->is_published ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $question->group_key }}')"
                                    class="st-icnbtn" title="Modifier (les deux langues)">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $question->group_key }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
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
                <div class="ffield">
                    <input id="fq-cat" type="text" wire:model="categorie" list="fq-cats" placeholder=" ">
                    <label for="fq-cat">Rubrique (facultatif)</label>
                    <datalist id="fq-cats">
                        @foreach ($categories as $uneCategorie)
                            <option value="{{ $uneCategorie }}"></option>
                        @endforeach
                    </datalist>
                    @error('categorie') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="st-card p-3">
                    <p class="text-xs font-bold text-[#64748B] mb-2">FRANÇAIS</p>

                    <div class="ffield">
                        <textarea id="fq-qfr" wire:model="questionFr" rows="2" placeholder=" "></textarea>
                        <label for="fq-qfr">Question</label>
                        @error('questionFr') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="ffield mb-0">
                        <textarea id="fq-rfr" wire:model="reponseFr" rows="4" placeholder=" "></textarea>
                        <label for="fq-rfr">Réponse</label>
                        @error('reponseFr') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="st-card p-3">
                    <p class="text-xs font-bold text-[#64748B] mb-2">ENGLISH — facultatif</p>

                    <div class="ffield">
                        <textarea id="fq-qen" wire:model="questionEn" rows="2" placeholder=" "></textarea>
                        <label for="fq-qen">Question</label>
                        @error('questionEn') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="ffield mb-0">
                        <textarea id="fq-ren" wire:model="reponseEn" rows="4" placeholder=" "></textarea>
                        <label for="fq-ren">Answer</label>
                        @error('reponseEn') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-[#64748B] mt-2 mb-0">
                        Laissés vides, la version anglaise du site affiche le français.
                        Vider une traduction existante la supprime.
                    </p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="ffield mb-0">
                        <input id="fq-ordre" type="number" min="0" wire:model="ordre" placeholder=" ">
                        <label for="fq-ordre">Ordre d'affichage</label>
                        @error('ordre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model="publiee" class="mt-0.5">
                        <span class="text-sm text-[#334155]">Publiée sur le site</span>
                    </label>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
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
