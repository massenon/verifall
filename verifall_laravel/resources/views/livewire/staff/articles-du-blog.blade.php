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
<div>

    <div @class(['hidden' => $formulaireOuvert])>
    <x-premium.entete titre="Articles du blog" :fil="['Articles du blog']">
        Les textes de fond : méthode, coulisses, tribunes. Pour un démenti avec verdict, passez par <a href="{{ route('staff.hub-news') }}" class="text-[#059669] underline">les actualités du Hub</a>.
        <x-slot:actions>
            @if ($peutCreer)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouveau billet
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

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
    </div>

    {{-- ═════════════ L'ÉDITEUR PLEINE PAGE ═════════════
         Toujours dans le DOM (masqué quand fermé) : Quill s'attache à son hôte
         une seule fois, au chargement. La liste se masque à l'ouverture. --}}
    <section @class(['hidden' => ! $formulaireOuvert]) aria-label="Éditeur de billet">
        <form wire:submit="enregistrer" @if ($formulaireOuvert) wire:poll.30s="autoEnregistrer" @endif>
            <div class="sticky top-16 z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 mb-6 bg-papier/95 dark:bg-nuit/95 backdrop-blur border-b filet flex flex-wrap items-center gap-3">
                <button type="button" wire:click="fermerLeTiroir" class="btn-fantome btn-sm"><x-lucide name="arrow-left" />Retour aux billets</button>
                <div class="min-w-0 flex-1">
                    <p class="font-titre font-semibold truncate text-encre dark:text-white">{{ $titre !== '' ? $titre : ($enEdition === null ? 'Nouveau billet' : 'Billet sans titre') }}</p>
                    <p class="text-xs texte-3" role="status">
                        @if ($dernierEnregistrement) Brouillon enregistré à {{ $dernierEnregistrement }}.
                        @elseif ($estPublie) Billet publié : l'enregistrement automatique est coupé, enregistrez à la main.
                        @else Enregistrement automatique toutes les 30 secondes, en brouillon. @endif
                    </p>
                </div>
                <button type="submit" class="btn-primaire"><x-lucide name="check" />Enregistrer</button>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] items-start">
                <div class="space-y-5 min-w-0">
                    <div>
                        <label for="ab-titre" class="etiquette">Titre du billet</label>
                        <input id="ab-titre" type="text" wire:model.live.debounce.500ms="titre" class="champ !h-12 text-lg font-titre @error('titre') champ-erreur @enderror" autocomplete="off">
                        @error('titre') <p class="aide-erreur">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ab-chapeau" class="etiquette">Chapeau</label>
                        <textarea id="ab-chapeau" wire:model.live.debounce.500ms="chapeau" rows="3" class="champ !h-auto py-2.5 @error('chapeau') champ-erreur @enderror"></textarea>
                        @error('chapeau') <p class="aide-erreur">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="etiquette">Corps du billet</label>
                        <div class="st-quill" wire:ignore>
                            <div data-quill-billet></div>
                        </div>
                        @error('corps') <p class="aide-erreur">{{ $message }}</p> @enderror
                        <p class="aide">Le HTML est purifié avant enregistrement : ce texte est rendu au public. <span data-quill-compte></span></p>
                    </div>
                </div>

                <aside class="space-y-5 lg:sticky lg:top-40">
                    <x-premium.carte titre="Publication">
                        <p class="text-sm texte-2">{{ $estPublie ? 'Ce billet est en ligne.' : 'Ce billet est un brouillon.' }} La publication est un geste à part, depuis la liste.</p>
                        <label class="flex items-start gap-2.5 cursor-pointer mt-4">
                            <input type="checkbox" wire:model="aLaUne" class="case mt-0.5">
                            <span class="text-sm">Mettre à la une<span class="block text-xs texte-3">Mis en avant sur la page du blog, une fois publié.</span></span>
                        </label>
                    </x-premium.carte>

                    <x-premium.carte titre="Rubrique et couverture">
                        <label for="ab-cat" class="etiquette">Rubrique</label>
                        <select id="ab-cat" wire:model="categorieUuid" class="champ">
                            <option value="">— Sans rubrique —</option>
                            @foreach ($categories as $categorie)
                                <option value="{{ $categorie->uuid }}">{{ $categorie->name }}</option>
                            @endforeach
                        </select>
                        @error('categorieUuid') <p class="aide-erreur">{{ $message }}</p> @enderror

                        {{-- Disque PUBLIC : elle s'affiche dans les listes et les aperçus de partage. --}}
                        <label for="ab-couv" class="etiquette mt-4">Image de couverture</label>
                        @if ($couverture)
                            <img src="{{ $couverture->temporaryUrl() }}" alt="Aperçu de la couverture" class="w-full rounded-ctrl border filet mb-2">
                        @endif
                        <input id="ab-couv" type="file" wire:model="couverture" accept="image/jpeg,image/png,image/webp" class="champ !h-auto py-2">
                        <p class="aide">JPG, PNG ou WEBP, 4 Mo. Laissée vide, l'image actuelle est conservée.</p>
                        <div wire:loading wire:target="couverture" class="aide">Envoi en cours…</div>
                        @error('couverture') <p class="aide-erreur">{{ $message }}</p> @enderror
                    </x-premium.carte>

                    <x-premium.carte titre="Référencement">
                        {{-- L'aperçu montre ce que Google affichera : le titre de recherche,
                             sinon le titre ; la description, sinon le chapeau. --}}
                        @php
                            $apercuTitre = $metaTitre !== '' ? $metaTitre : $titre;
                            $apercuDescription = $metaDescription !== '' ? $metaDescription : $chapeau;
                        @endphp
                        <div class="rounded-ctrl border filet bg-white dark:bg-nuit p-3 mb-4" aria-label="Aperçu dans les résultats de recherche">
                            <p class="text-xs texte-3 truncate">{{ parse_url(config('app.url'), PHP_URL_HOST) }} › blog</p>
                            <p class="text-[16px] leading-snug text-blue-700 dark:text-blue-300 truncate">{{ \Illuminate\Support\Str::limit($apercuTitre !== '' ? $apercuTitre : 'Titre du billet', 70, '…') }}</p>
                            <p class="text-[13px] texte-2 line-clamp-2">{{ \Illuminate\Support\Str::limit($apercuDescription !== '' ? $apercuDescription : 'La description apparaîtra ici.', 160, '…') }}</p>
                        </div>

                        <label for="ab-metat" class="etiquette">Titre de recherche <span class="texte-3 font-normal">({{ mb_strlen($metaTitre) }}/70)</span></label>
                        <input id="ab-metat" type="text" wire:model.live.debounce.500ms="metaTitre" class="champ @error('metaTitre') champ-erreur @enderror" placeholder="Laissé vide : le titre">
                        @error('metaTitre') <p class="aide-erreur">{{ $message }}</p> @enderror

                        <label for="ab-metad" class="etiquette mt-3">Description <span class="texte-3 font-normal">({{ mb_strlen($metaDescription) }}/160)</span></label>
                        <textarea id="ab-metad" wire:model.live.debounce.500ms="metaDescription" rows="3" class="champ !h-auto py-2 @error('metaDescription') champ-erreur @enderror" placeholder="Laissée vide : le chapeau"></textarea>
                        @error('metaDescription') <p class="aide-erreur">{{ $message }}</p> @enderror

                        <label for="ab-metak" class="etiquette mt-3">Mots-clés</label>
                        <input id="ab-metak" type="text" wire:model="metaMotsCles" class="champ @error('metaMotsCles') champ-erreur @enderror" placeholder="séparés par des virgules">
                        @error('metaMotsCles') <p class="aide-erreur">{{ $message }}</p> @enderror
                    </x-premium.carte>
                </aside>
            </div>
        </form>
    </section>

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

                // Compte de mots, pour savoir où l'on en est sans quitter la page.
                var texte = editeur.getText().trim();
                var compte = document.querySelector('[data-quill-compte]');
                if (compte) { compte.textContent = texte === '' ? '' : (texte.split(/\s+/).length + ' mots'); }
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
