{{--
  | « Articles du Blog » (lot 8).
  |
  | À ne pas confondre avec le Hub d'infos : un article du Hub est un DÉMENTI,
  | avec affirmation et verdict, qui alimente le flux ClaimReview ingéré par les
  | moteurs. Un billet de blog est un texte de fond, sans verdict.
  |
  | Les confondre serait grave dans un sens : un texte d'opinion entré dans le
  | flux ClaimReview y serait lu comme une vérification.
  |
  | La case « publier » ne figure PAS au formulaire — `post.publish` est séparé
  | de `post.update` par la matrice, et l'y mettre rendrait le droit
  | contournable.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Articles du blog</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Articles du blog</h1>
            <p class="text-[#64748B] text-sm mb-0">
                Les textes de fond : méthode, coulisses, tribunes. Pour un démenti avec verdict,
                passez par <a href="{{ route('staff.hub-news') }}" class="text-[#059669] underline">les actualités du Hub</a>.
            </p>
        </div>

        @if ($peutCreer)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouveau billet
            </button>
        @endif
    </header>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Billets" trend="Tout le blog" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publies'], 0, ',', ' ')"
               label="Publiés" trend="En ligne et indexés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['brouillons'], 0, ',', ' ')"
               label="Brouillons" trend="Pas encore en ligne" />

        <x-kpi variant="staff" :value="number_format($indicateurs['une'], 0, ',', ' ')"
               label="À la une" trend="Mis en avant, publiés" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="ab-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="ab-q">Rechercher un titre, un chapeau</label>
        </div>

        <select wire:model.live="filtreCategorie" class="st-input w-auto" aria-label="Filtrer par rubrique">
            <option value="">Rubrique : toutes</option>
            @foreach ($categories as $categorie)
                <option value="{{ $categorie->uuid }}">{{ $categorie->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="publies">Publiés</option>
            <option value="brouillons">Brouillons</option>
            <option value="une">À la une</option>
        </select>
    </section>

    @if ($billets->isEmpty())
        <x-empty-state variant="staff" icon="edit_note"
                       title="Aucun billet"
                       description="Aucun billet ne correspond à ces critères.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$billets->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Billet</th>
                <th scope="col">Rubrique</th>
                <th scope="col">Auteur</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($billets as $billet)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">
                            @if ($billet->is_featured)
                                <span class="material-symbols-rounded !text-[16px] text-[#D97706]" aria-hidden="true">star</span>
                            @endif
                            {{ Str::limit($billet->title, 70) }}
                        </p>
                        <p class="text-xs text-[#64748B] mb-0">{{ Str::limit($billet->excerpt, 80) }}</p>
                    </td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        {{ $billet->category?->name ?? 'Sans rubrique' }}
                    </td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        {{ $billet->author?->name ?? '—' }}
                    </td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$billet->is_published ? 'publie' : 'brouillon'"
                                        :label="$billet->is_published ? 'Publié' : 'Brouillon'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutPublier)
                            <button type="button" wire:click="basculerLaPublication('{{ $billet->uuid }}')"
                                    class="st-icnbtn" title="{{ $billet->is_published ? 'Dépublier' : 'Publier' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $billet->is_published ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                        @endif
                        @if ($peutModifier)
                            <button type="button" wire:click="ouvrirEdition('{{ $billet->uuid }}')"
                                    class="st-icnbtn" title="Modifier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                        @endif
                        @if ($peutSupprimer)
                            <button type="button" wire:click="demanderLaSuppression('{{ $billet->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $billets->links() }}</div>
    @endif

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouveau billet' : 'Modifier le billet'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div class="ffield">
                <input id="ab-titre" type="text" wire:model="titre" placeholder=" ">
                <label for="ab-titre">Titre du billet</label>
                @error('titre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <textarea id="ab-chapeau" wire:model="chapeau" rows="3" placeholder=" "></textarea>
                <label for="ab-chapeau">Chapeau</label>
                @error('chapeau') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="ab-cat" class="block text-sm font-medium text-[#0F172A] mb-1">Rubrique</label>
                <select id="ab-cat" wire:model="categorieUuid" class="st-input w-full">
                    <option value="">— Sans rubrique —</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->uuid }}">{{ $categorie->name }}</option>
                    @endforeach
                </select>
                @error('categorieUuid') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-[#0F172A] mb-1">Corps du billet</label>
                <div class="st-quill" wire:ignore>
                    <div data-quill-billet></div>
                </div>
                @error('corps') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Le HTML est purifié avant enregistrement : ce texte est rendu au public.
                </p>
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="aLaUne" class="mt-0.5">
                <span class="text-sm text-[#334155]">
                    Mettre à la une
                    <span class="block text-xs text-[#64748B]">
                        Le billet est mis en avant sur la page du blog, une fois publié.
                    </span>
                </span>
            </label>

            <div class="ffield">
                <input id="ab-metat" type="text" wire:model="metaTitre" placeholder=" ">
                <label for="ab-metat">Titre de recherche (facultatif)</label>
                {{-- Facultatif et replié sur le contenu : `Referencement` se rabat
                     sur le titre et le chapeau. Les champs servent aux cas où l'on
                     veut s'en écarter. --}}
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Laissé vide, le titre du billet est employé. 70 caractères au plus :
                    au-delà, les moteurs tronquent.
                </p>
                @error('metaTitre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <textarea id="ab-metad" wire:model="metaDescription" rows="2" placeholder=" "></textarea>
                <label for="ab-metad">Description de recherche (facultatif)</label>
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Laissée vide, le chapeau est employé. 160 caractères au plus.
                </p>
                @error('metaDescription') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <p class="text-xs text-[#64748B] mb-0">
                La publication est un geste à part, depuis la liste.
            </p>

            {{-- ── COUVERTURE ────────────────────────────────────────────────
                 Disque PUBLIC : elle s'affiche dans les listes et dans les
                 aperçus de partage. La cacher derrière une route signée la
                 rendrait invisible là où elle sert. --}}
            <div>
                <label for="ab-couv" class="block text-sm font-medium text-[#0F172A] mb-1">Image de couverture</label>
                <input id="ab-couv" type="file" wire:model="couverture"
                       accept="image/jpeg,image/png,image/webp" class="st-input w-full">
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    JPG, PNG ou WEBP, 4 Mo maximum. Laissée vide, l'image actuelle est conservée.
                </p>
                <div wire:loading wire:target="couverture" class="text-xs text-[#64748B]">Envoi en cours…</div>
                @error('couverture') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <input id="ab-metak" type="text" wire:model="metaMotsCles" placeholder=" ">
                <label for="ab-metak">Mots-clés, séparés par des virgules</label>
                @error('metaMotsCles') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer ce billet ?"
                    description="La suppression est définitive. Le billet quitte le blog, le plan du site et le référencement. Pour le retirer sans l'effacer, dépubliez-le plutôt."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>

{{-- ------------------------------------------------------ pont vers Quill --}}
@push('scripts')
    <script src="{{ asset('assets/js/vendor/quill.js') }}"></script>
    <script>
        document.addEventListener('livewire:initialized', function () {
            var composant = @this;
            var hote = document.querySelector('[data-quill-billet]');

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
                placeholder: 'Rédigez le billet…',
            });

            var enChargement = false;

            editeur.on('text-change', function () {
                if (enChargement) { return; }

                var html = editeur.root.innerHTML;

                composant.set('corps', html === '<p><br></p>' ? '' : html, false);
            });

            composant.on('corps-billet-charge', function (charge) {
                var detail = Array.isArray(charge) ? charge[0] : charge;

                enChargement = true;
                editeur.root.innerHTML = (detail && detail.html) ? detail.html : '';
                enChargement = false;
            });
        });
    </script>
@endpush
