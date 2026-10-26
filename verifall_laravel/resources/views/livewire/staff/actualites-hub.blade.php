{{--
  | « Actualités » du Hub d'infos (lot 8).
  |
  | Un article du Hub est un DÉMENTI : il porte une affirmation vérifiée, un
  | verdict, et alimente le flux ClaimReview que les moteurs ingèrent. À ne pas
  | confondre avec le blog, qui est du texte de fond sans verdict.
  |
  | Deux effets de bord automatiques, portés par `#[ObservedBy]` sur le modèle :
  | enregistrer met en file la fabrication de la carte de démenti et oublie le
  | cache du flux ; supprimer emporte la carte. La carte exige `queue:work`.
--}}
<div>

<x-premium.entete titre="Actualités du Hub" :fil="['Actualités du Hub']">
        Les démentis publiés. Chacun porte l'affirmation exacte qui circule et son verdict — c'est cette paire que les moteurs de recherche ingèrent.
        <x-slot:actions>
            @if ($peutCreer)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvel article
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Articles" trend="Tout le Hub" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publies'], 0, ',', ' ')"
               label="Publiés" trend="Visibles et indexés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['brouillons'], 0, ',', ' ')"
               label="Brouillons" trend="Pas encore en ligne" />

        <x-kpi variant="staff" :value="number_format($indicateurs['faux'], 0, ',', ' ')"
               label="Démentis publiés" trend="Verdict FAUX en ligne" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="ah-q" class="etiquette">Rechercher un titre, une affirmation</label>
<input id="ah-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreVerdict" class="champ w-auto" aria-label="Filtrer par verdict">
            <option value="">Verdict : tous</option>
            @foreach ($verdicts as $unVerdict)
                <option value="{{ $unVerdict->value }}">{{ $unVerdict->libelle() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="publies">Publiés</option>
            <option value="brouillons">Brouillons</option>
        </select>
    </section>

    @if ($articles->isEmpty())
        <x-empty-state variant="staff" icon="newspaper"
                       title="Aucun article"
                       description="Aucun article ne correspond à ces critères. Tant que le Hub est vide, le flux ClaimReview n'a rien à donner aux moteurs.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="btn-second">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$articles->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Article</th>
                <th scope="col">Verdict</th>
                <th scope="col">Catégorie</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($articles as $article)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ Str::limit($article->title, 70) }}</p>
                        {{-- L'affirmation est le cœur du démenti : c'est la phrase qui
                             circule, et celle que le flux publie comme `claimReviewed`. --}}
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">« {{ Str::limit($article->claim, 80) }} »</p>
                    </td>
                    <td class="whitespace-nowrap">
                        <span class="pastille p-info">{{ $article->verdict?->libelle() ?? $article->verdict }}</span>
                    </td>
                    <td class="whitespace-nowrap text-sm text-encre-3 dark:text-nuit-3">{{ $article->category }}</td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$article->is_published ? 'publie' : 'brouillon'"
                                        :label="$article->is_published ? 'Publié' : 'Brouillon'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutPublier)
                            <button type="button" wire:click="basculerLaPublication('{{ $article->uuid }}')"
                                    class="btn-icone" title="{{ $article->is_published ? 'Dépublier' : 'Publier' }}">
                                <x-lucide :name="$article->is_published ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                        @endif
                        @if ($peutModifier)
                            <button type="button" wire:click="ouvrirEdition('{{ $article->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                        @endif
                        @if ($peutSupprimer)
                            <button type="button" wire:click="demanderLaSuppression('{{ $article->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $articles->links() }}</div>
    @endif

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouvel article' : 'Modifier l\'article'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div>
                <label for="ah-titre" class="etiquette">Titre de l'article</label>
<input id="ah-titre" type="text" wire:model="titre" class="champ">
                @error('titre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="ah-claim" class="etiquette">Affirmation vérifiée</label>
<textarea id="ah-claim" wire:model="affirmation" rows="2" class="champ !h-auto py-2.5"></textarea>
                <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                    La phrase telle qu'elle circule, mot pour mot. Sans elle, l'article explique
                    une rumeur que personne ne reconnaît.
                </p>
                @error('affirmation') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="ah-verdict" class="block text-sm font-medium text-encre dark:text-white mb-1">Verdict</label>
                    <select id="ah-verdict" wire:model="verdict" class="champ w-full">
                        @foreach ($verdicts as $unVerdict)
                            <option value="{{ $unVerdict->value }}">{{ $unVerdict->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('verdict') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="ah-cat" class="etiquette">Catégorie</label>
<input id="ah-cat" type="text" wire:model="categorie" list="ah-cats" class="champ">
                    <datalist id="ah-cats">
                        @foreach ($categories as $uneCategorie)
                            <option value="{{ $uneCategorie }}"></option>
                        @endforeach
                    </datalist>
                    @error('categorie') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="ah-chapeau" class="etiquette">Chapeau</label>
<textarea id="ah-chapeau" wire:model="chapeau" rows="3" class="champ !h-auto py-2.5"></textarea>
                @error('chapeau') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            {{--
              | L'urgence, et ce qu'elle déclenche.
              |
              | La case est annoncée pour ce qu'elle FAIT — « un courriel part à
              | tous les abonnés » —, pas pour ce qu'elle est. Une case nommée
              | « Urgent » sans plus d'explication se coche par réflexe ; celle-ci
              | dit son prix.
            --}}
            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="urgent" class="mt-0.5">
                <span class="text-sm text-encre-2 dark:text-nuit-2">
                    Intox en circulation — alerter les abonnés
                    <span class="block text-xs text-encre-3 dark:text-nuit-3">
                        À la publication, un courriel part à tous les abonnés de l'infolettre.
                        Une seule fois : republier après correction ne le renvoie pas.
                    </span>
                </span>
            </label>

            <div>
                <label class="block text-sm font-medium text-encre dark:text-white mb-1">Corps de l'article</label>
                {{-- Quill écrit dans son propre DOM : sans wire:ignore, le premier
                     rendu de Livewire l'effacerait. --}}
                <div class="editeur-riche" wire:ignore>
                    <div data-quill-article></div>
                </div>
                @error('corps') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                    Le HTML est purifié avant enregistrement : cet article est rendu au public
                    et ingéré par des moteurs.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="mb-0">
                    <label for="ah-src" class="etiquette">Nom de la source</label>
<input id="ah-src" type="text" wire:model="nomDeLaSource" class="champ">
                    @error('nomDeLaSource') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="ah-srcurl" class="etiquette">Lien de la source</label>
<input id="ah-srcurl" type="url" wire:model="lienDeLaSource" class="champ">
                    @error('lienDeLaSource') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                La publication est un geste à part, depuis la liste. Enregistrer met déjà en file
                la fabrication de la carte de démenti.
            </p>

            {{-- ── COUVERTURE ────────────────────────────────────────────────
                 Disque PUBLIC : elle s'affiche dans les listes et dans les
                 aperçus de partage. La cacher derrière une route signée la
                 rendrait invisible là où elle sert. --}}
            <div>
                <label for="ah-couv" class="block text-sm font-medium text-encre dark:text-white mb-1">Image de couverture</label>
                <input id="ah-couv" type="file" wire:model="couverture"
                       accept="image/jpeg,image/png,image/webp" class="champ w-full">
                <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                    JPG, PNG ou WEBP, 4 Mo maximum. Laissée vide, l'image actuelle est conservée.
                </p>
                <div wire:loading wire:target="couverture" class="text-xs text-encre-3 dark:text-nuit-3">Envoi en cours…</div>
                @error('couverture') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            {{-- ── RÉFÉRENCEMENT ────────────────────────────────────────────
                 `Referencement.php` lisait `meta_title` et `meta_description`
                 sur cet article DEPUIS L'ORIGINE — et les colonnes n'existaient
                 pas. La surcharge n'a jamais rien fait, sans qu'aucune erreur
                 ne le signale. Voici enfin les champs. --}}
            <details class="rounded-xl border border-trait dark:border-nuit-trait p-3">
                <summary class="text-sm font-medium text-encre dark:text-white cursor-pointer">Référencement (facultatif)</summary>

                <div class="mt-3 space-y-3">
                    <div class="mb-0">
                        <label for="ah-metat" class="etiquette">Titre de recherche</label>
<input id="ah-metat" type="text" wire:model="metaTitre" class="champ">
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">70 caractères au plus — au-delà, Google tronque.</p>
                        @error('metaTitre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="ah-metad" class="etiquette">Description de recherche</label>
<textarea id="ah-metad" rows="2" wire:model="metaDescription" class="champ !h-auto py-2.5"></textarea>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">160 caractères au plus.</p>
                        @error('metaDescription') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="ah-metak" class="etiquette">Mots-clés, séparés par des virgules</label>
<input id="ah-metak" type="text" wire:model="metaMotsCles" class="champ">
                        @error('metaMotsCles') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>
            </details>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cet article ?"
                    description="La suppression est définitive et emporte la carte de démenti. Sans cela, l'image resterait servie par son URL publique : un démenti que le site ne montre plus, mais que WhatsApp continuerait d'afficher."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>

{{-- ------------------------------------------------------ pont vers Quill --}}
@push('scripts')
    <script src="{{ asset('assets/js/vendor/quill.js') }}"></script>
    <script>
        document.addEventListener('livewire:initialized', function () {
            var composant = @this;
            var hote = document.querySelector('[data-quill-article]');

            if (!hote || typeof Quill === 'undefined') { return; }

            var editeur = new Quill(hote, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'link'],
                        ['clean'],
                    ],
                },
                placeholder: 'Rédigez le démenti — ce qui circule, ce qui est établi, et sur quoi…',
            });

            var enChargement = false;

            editeur.on('text-change', function () {
                if (enChargement) { return; }

                var html = editeur.root.innerHTML;

                composant.set('corps', html === '<p><br></p>' ? '' : html, false);
            });

            composant.on('corps-article-charge', function (charge) {
                var detail = Array.isArray(charge) ? charge[0] : charge;

                enChargement = true;
                editeur.root.innerHTML = (detail && detail.html) ? detail.html : '';
                enChargement = false;
            });
        });
    </script>
@endpush
