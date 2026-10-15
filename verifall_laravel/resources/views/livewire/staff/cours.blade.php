{{--
  | « Tous les Cours » (lot 6).
  |
  | Cet écran tient ce qui DÉCRIT un cours. Le contenu — leçons et quiz — vit
  | dans le constructeur, qui s'ouvre depuis une ligne. Réunir les deux
  | donnerait un tiroir à quinze champs plus quarante-six leçons.
  |
  | La case « publier » ne figure PAS dans le formulaire, et c'est délibéré :
  | la matrice sépare `course.publish` de `course.update` parce que publier
  | engage la plateforme. La mettre au formulaire rendrait le droit
  | contournable par quiconque peut modifier.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Tous les cours</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Tous les cours</h1>
            <p class="text-[#64748B] text-sm mb-0">
                La fiche d'un cours : titre, rayon, niveau, coût, formateur.
                Les leçons et les quiz s'écrivent dans le constructeur, depuis le bouton de chaque ligne.
            </p>
        </div>

        @can('create', App\Models\Course::class)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouveau cours
            </button>
        @endcan
    </header>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Cours"
               :trend="$indicateurs['publies'].' au catalogue'" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publies'], 0, ',', ' ')"
               label="Publiés"
               trend="Visibles côté citoyen" />

        <x-kpi variant="staff" :value="number_format($indicateurs['brouillons'], 0, ',', ' ')"
               label="Brouillons"
               trend="Pas encore en ligne" />

        <x-kpi variant="staff" :value="number_format($indicateurs['inscrits'], 0, ',', ' ')"
               label="Inscriptions"
               trend="Tous cours confondus" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="cours-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="cours-q">Rechercher un titre, une description</label>
        </div>

        <select wire:model.live="filtreCategorie" class="st-input w-auto" aria-label="Filtrer par catégorie">
            <option value="">Rayon : tous</option>
            @foreach ($categories as $categorie)
                <option value="{{ $categorie->uuid }}">{{ $categorie->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreNiveau" class="st-input w-auto" aria-label="Filtrer par niveau">
            <option value="">Niveau : tous</option>
            @foreach ($niveaux as $unNiveau)
                <option value="{{ $unNiveau->value }}">{{ $unNiveau->libelle() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="publies">Publiés</option>
            <option value="brouillons">Brouillons</option>
            <option value="archives">Archivés</option>
        </select>
    </section>

    @if ($recherche !== '' || $filtreCategorie !== '' || $filtreNiveau !== '' || $filtreEtat !== '')
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @if ($recherche !== '')
                <span class="st-chip">« {{ $recherche }} »
                    <button type="button" wire:click="$set('recherche', '')" aria-label="Retirer la recherche">×</button>
                </span>
            @endif
            <button type="button" wire:click="reinitialiser" class="text-sm text-[#059669] font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($cours->isEmpty())
        <x-empty-state variant="staff" icon="school"
                       title="Aucun cours"
                       description="Aucune fiche ne correspond à ces critères. Un cours créé ici reste en brouillon jusqu'à ce qu'il ait des leçons et qu'on le publie.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$cours->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Cours</th>
                <th scope="col">Rayon</th>
                <th scope="col">Contenu</th>
                <th scope="col">Coût</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($cours as $unCours)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $unCours->title }}</p>
                        <p class="text-xs text-[#64748B] mb-0">
                            {{ $unCours->level?->libelle() }}
                            @if ($unCours->instructor !== null)
                                · {{ $unCours->instructor->name }}
                            @else
                                · <span class="text-[#DC2626]">sans formateur</span>
                            @endif
                        </p>
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($unCours->categorie !== null)
                            <span class="st-badge st-badge-info">{{ $unCours->categorie->name }}</span>
                        @else
                            <span class="text-xs text-[#64748B]">Sans rayon</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        {{-- Le nombre de leçons est l'information qui décide de la
                             publication : un cours à zéro leçon ne se publie pas. --}}
                        @if ($unCours->lessons_count === 0)
                            <span class="text-[#DC2626] font-semibold">0 leçon</span>
                        @else
                            <span class="text-[#334155]">{{ $unCours->lessons_count }} leçon(s)</span>
                        @endif
                        <span class="block text-xs text-[#64748B]">{{ $unCours->enrollments_count }} inscrit(s)</span>
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        @if ($unCours->cost_units === 0)
                            <span class="st-badge st-badge-pub">Gratuit</span>
                        @else
                            <span class="st-badge st-badge-premium">{{ $unCours->cost_units }} u.</span>
                        @endif
                    </td>
                    <td>
                        @if ($unCours->trashed())
                            <x-badge-statut variant="staff" statut="archive" label="Archivé" />
                        @else
                            <x-badge-statut variant="staff"
                                            :statut="$unCours->is_published ? 'publie' : 'brouillon'"
                                            :label="$unCours->is_published ? 'Publié' : 'Brouillon'" />
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $unCours)
                            <a href="{{ route('staff.course-builder', $unCours->slug) }}"
                               class="st-icnbtn" title="Ouvrir le constructeur">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">design_services</span>
                            </a>
                            {{-- Les statistiques du cours : inscrits, certificats,
                                 progression moyenne, et la liste de qui décroche. --}}
                            <a href="{{ route('staff.course-stats', $unCours->slug) }}"
                               class="st-icnbtn" title="Statistiques du cours">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">monitoring</span>
                            </a>
                        @endcan
                        @can('publish', $unCours)
                            <button type="button" wire:click="basculerLaPublication('{{ $unCours->uuid }}')"
                                    class="st-icnbtn" title="{{ $unCours->is_published ? 'Retirer du catalogue' : 'Publier' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $unCours->is_published ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                        @endcan
                        @can('update', $unCours)
                            <button type="button" wire:click="ouvrirEdition('{{ $unCours->uuid }}')"
                                    class="st-icnbtn" title="Modifier la fiche">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                        @endcan
                        @can('delete', $unCours)
                            <button type="button" wire:click="demanderLaSuppression('{{ $unCours->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Archiver">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $cours->links() }}</div>
    @endif

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouveau cours' : 'Modifier la fiche'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div class="ffield">
                <input id="cours-titre" type="text" wire:model="titre" placeholder=" ">
                <label for="cours-titre">Titre du cours</label>
                @error('titre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <textarea id="cours-desc" wire:model="descriptif" rows="4" placeholder=" "></textarea>
                <label for="cours-desc">Ce que ce cours apprend</label>
                @error('descriptif') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="cours-cat" class="block text-sm font-medium text-[#0F172A] mb-1">Rayon</label>
                    <select id="cours-cat" wire:model="categorieUuid" class="st-input w-full">
                        <option value="">— Sans rayon —</option>
                        @foreach ($categories as $categorie)
                            <option value="{{ $categorie->uuid }}">{{ $categorie->name }}</option>
                        @endforeach
                    </select>
                    @error('categorieUuid') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cours-niv" class="block text-sm font-medium text-[#0F172A] mb-1">Niveau</label>
                    <select id="cours-niv" wire:model="niveau" class="st-input w-full">
                        @foreach ($niveaux as $unNiveau)
                            <option value="{{ $unNiveau->value }}">{{ $unNiveau->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('niveau') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="ffield mb-0">
                    <input id="cours-duree" type="text" wire:model="duree" placeholder=" ">
                    <label for="cours-duree">Durée annoncée (ex. 4 h)</label>
                    @error('duree') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="cours-cout" type="number" min="0" max="500" wire:model="cout" placeholder=" ">
                    <label for="cours-cout">Coût en unités (0 = gratuit)</label>
                    @error('cout') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="cours-form" class="block text-sm font-medium text-[#0F172A] mb-1">Formateur</label>
                <select id="cours-form" wire:model="formateurUuid" class="st-input w-full">
                    <option value="">— À attribuer —</option>
                    @foreach ($formateurs as $formateur)
                        <option value="{{ $formateur->uuid }}">{{ $formateur->name }}</option>
                    @endforeach
                </select>
                {{-- Mentors et administrateurs seulement : le nom s'affiche en
                     « formateur » sur la fiche publique du cours. --}}
                <p class="text-xs text-[#64748B] mt-1 mb-0">Son nom apparaît sur la fiche publique du cours.</p>
                @error('formateurUuid') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="delivreUnCertificat" class="mt-0.5">
                <span class="text-sm text-[#334155]">
                    Délivre un certificat
                    <span class="block text-xs text-[#64748B]">
                        À l'achèvement du parcours, un certificat vérifiable publiquement est émis.
                    </span>
                </span>
            </label>

            {{-- ── COUVERTURE ────────────────────────────────────────────────
                 Sur le disque PUBLIC, contrairement aux supports de leçon : une
                 couverture est faite pour être vue — catalogue, partages,
                 aperçus de réseaux sociaux. La cacher derrière une route signée
                 la rendrait invisible là où elle sert. --}}
            <div>
                <label for="cours-couv" class="block text-sm font-medium text-[#0F172A] mb-1">Image de couverture</label>

                @if ($enEdition !== null && $couvertureActuelle)
                    <img src="{{ $couvertureActuelle }}" alt=""
                         class="rounded-xl border border-line mb-2" style="max-height: 120px;">
                @endif

                <input id="cours-couv" type="file" wire:model="couverture"
                       accept="image/jpeg,image/png,image/webp" class="st-input w-full">
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    JPG, PNG ou WEBP, 4 Mo maximum. Laissée vide, la couverture actuelle est conservée.
                </p>
                <div wire:loading wire:target="couverture" class="text-xs text-[#64748B]">Envoi en cours…</div>
                @error('couverture') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="cours-langue" class="block text-sm font-medium text-[#0F172A] mb-1">Langue du cours</label>
                    <select id="cours-langue" wire:model="langue" class="st-input w-full">
                        @foreach ($langues as $code => $nom)
                            <option value="{{ $code }}">{{ $nom }}</option>
                        @endforeach
                    </select>
                    @error('langue') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="cours-xp" type="number" min="0" max="1000" step="10" wire:model="xp" placeholder=" ">
                    <label for="cours-xp">Points XP à 100 %</label>
                    @error('xp') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- ── PRÉREQUIS ET OBJECTIFS ────────────────────────────────────
                 Une entrée PAR LIGNE, stockée en liste. Un bloc de texte se
                 remettrait en page de travers dès qu'on change de gabarit. --}}
            <div class="ffield">
                <textarea id="cours-prereq" rows="4" wire:model="prerequis" placeholder=" "></textarea>
                <label for="cours-prereq">Prérequis — un par ligne</label>
                <p class="text-xs text-[#64748B] mt-1 mb-0">Ce qu'il faut déjà savoir. Laissez vide s'il n'y en a pas.</p>
                @error('prerequis') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <textarea id="cours-objectifs" rows="4" wire:model="objectifs" placeholder=" "></textarea>
                <label for="cours-objectifs">Objectifs — un par ligne</label>
                <p class="text-xs text-[#64748B] mt-1 mb-0">Ce qu'on saura faire à la fin. C'est ce que lit la fiche publique.</p>
                @error('objectifs') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            {{-- ── RÉFÉRENCEMENT ────────────────────────────────────────────
                 Facultatif, et c'est voulu : sans surcharge, le titre et le
                 descriptif du cours servent de métadonnées. Un champ SEO vide
                 ne dégrade rien. --}}
            <details class="rounded-xl border border-line p-3">
                <summary class="text-sm font-medium text-[#0F172A] cursor-pointer">Référencement (facultatif)</summary>

                <div class="mt-3 space-y-3">
                    <div class="ffield mb-0">
                        <input id="cours-meta-t" type="text" wire:model="metaTitre" placeholder=" ">
                        <label for="cours-meta-t">Titre SEO</label>
                        <p class="text-xs text-[#64748B] mt-1 mb-0">Vide, le titre du cours est employé.</p>
                        @error('metaTitre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="ffield mb-0">
                        <textarea id="cours-meta-d" rows="2" wire:model="metaDescription" placeholder=" "></textarea>
                        <label for="cours-meta-d">Description SEO</label>
                        <p class="text-xs text-[#64748B] mt-1 mb-0">160 à 320 caractères. Vide, le descriptif est employé.</p>
                        @error('metaDescription') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="ffield mb-0">
                        <input id="cours-meta-k" type="text" wire:model="metaMotsCles" placeholder=" ">
                        <label for="cours-meta-k">Mots-clés, séparés par des virgules</label>
                        @error('metaMotsCles') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>
            </details>

            <p class="text-xs text-[#64748B] mb-0">
                La publication est un geste à part, depuis la liste — et elle exige au moins une leçon.
            </p>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Archiver ce cours ?"
                    description="Le cours quitte le catalogue mais n'est pas effacé : des inscriptions, des progressions et des certificats y renvoient, et la page publique de contrôle d'un certificat doit continuer de fonctionner."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
