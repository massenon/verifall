{{--
  | « Opportunités » — l'alimentation du Hub.
  |
  | Gabarit CRUD du back-office, identique aux autres écrans : fil d'Ariane,
  | en-tête avec l'action principale, indicateurs, carte de recherche et
  | filtres, jetons de filtres actifs, table sur grand écran et cartes sur
  | téléphone, tiroir de formulaire, modale de suppression nominative.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Opportunités</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Opportunités</h1>
            <p class="text-[#64748B] text-sm mb-0">
                Ce que le Hub propose à des gens qui cherchent du travail. La vérification
                est un geste distinct de la publication — et elle porte un nom.
            </p>
        </div>

        @can('create', App\Models\HubJob::class)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouvelle opportunité
            </button>
        @endcan
    </header>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Opportunités"
               :trend="$indicateurs['publiees'].' en ligne'" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publiees'], 0, ',', ' ')"
               label="Publiées"
               trend="Visibles dans le Hub citoyen" />

        {{--
          | LE chiffre à regarder en premier. Une offre en ligne et non vérifiée
          | est exactement ce que le Hub promet de ne pas faire : son public est
          | celui que visent les arnaques au recrutement.
        --}}
        <x-kpi variant="staff" :value="number_format($indicateurs['aVerifier'], 0, ',', ' ')"
               label="En ligne, non vérifiées"
               :trend="$indicateurs['aVerifier'] > 0 ? 'À contrôler en priorité' : 'Rien à contrôler'" />

        <x-kpi variant="staff" :value="number_format($indicateurs['echeanceProche'], 0, ',', ' ')"
               label="Échéance sous 7 jours"
               trend="Fermeture automatique 1 semaine après" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="offres-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="offres-q">Rechercher un intitulé, une organisation, un lieu</label>
        </div>

        <select wire:model.live="filtrePortee" class="st-input w-auto" aria-label="Filtrer par portée">
            <option value="">Portée : toutes</option>
            @foreach ($portees as $portee)
                <option value="{{ $portee->value }}">{{ $portee->libelle() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreType" class="st-input w-auto" aria-label="Filtrer par nature">
            <option value="">Nature : toutes</option>
            @foreach ($types as $unType)
                <option value="{{ $unType->value }}">{{ $unType->libelle() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="publiees">Publiées</option>
            <option value="brouillons">Brouillons</option>
            <option value="a-verifier">En ligne, non vérifiées</option>
            <option value="expirees">Échéance dépassée</option>
        </select>
    </section>

    @if ($recherche !== '' || $filtrePortee !== '' || $filtreType !== '' || $filtreEtat !== '')
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @if ($recherche !== '')
                <span class="st-chip">{{ $recherche }}
                    <button type="button" wire:click="$set('recherche', '')" aria-label="Retirer la recherche">×</button>
                </span>
            @endif
            @if ($filtrePortee !== '')
                <span class="st-chip">{{ \App\Enums\PorteeOpportunite::from($filtrePortee)->libelle() }}
                    <button type="button" wire:click="$set('filtrePortee', '')" aria-label="Retirer le filtre de portée">×</button>
                </span>
            @endif
            @if ($filtreType !== '')
                <span class="st-chip">{{ \App\Enums\TypeOpportunite::from($filtreType)->libelle() }}
                    <button type="button" wire:click="$set('filtreType', '')" aria-label="Retirer le filtre de nature">×</button>
                </span>
            @endif
            @if ($filtreEtat !== '')
                <span class="st-chip">{{ $filtreEtat }}
                    <button type="button" wire:click="$set('filtreEtat', '')" aria-label="Retirer le filtre d'état">×</button>
                </span>
            @endif
            <button type="button" wire:click="reinitialiser" class="text-sm text-[#059669] font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($offres->isEmpty())
        <x-empty-state variant="staff" icon="work"
                       title="Aucune opportunité"
                       description="Aucune offre ne correspond à ces critères. Tant que le Hub est vide, l'onglet « Opportunités » citoyen l'est aussi.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$offres->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Intitulé</th>
                <th scope="col">Nature</th>
                <th scope="col">Portée</th>
                <th scope="col">Échéance</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($offres as $offre)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $offre->title }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $offre->organisation }} · {{ $offre->location }}</p>
                    </td>
                    <td><span class="st-badge st-badge-info">{{ $offre->type->libelle() }}</span></td>
                    <td class="whitespace-nowrap">{{ $offre->scope->libelle() }}</td>
                    <td class="whitespace-nowrap">
                        {{-- Une échéance dépassée se signale : c'est elle qui décide
                             de la fermeture automatique une semaine plus tard. --}}
                        <span class="{{ $offre->deadline?->isPast() ? 'text-[#B45309] font-semibold' : '' }}">
                            {{ $offre->deadline?->translatedFormat('j M Y') ?? '—' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-badge-statut variant="staff"
                                            :statut="$offre->is_published ? 'publie' : 'brouillon'"
                                            :label="$offre->is_published ? 'Publiée' : 'Brouillon'" />

                            @if ($offre->is_verified)
                                <span class="st-badge st-badge-pub">Vérifiée</span>
                            @elseif ($offre->is_published)
                                {{-- En ligne sans contrôle : l'état qu'on ne veut pas voir. --}}
                                <span class="st-badge st-badge-danger">Non vérifiée</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $offre)
                            <button type="button" wire:click="basculerLaVerification('{{ $offre->uuid }}')"
                                    class="st-icnbtn" title="{{ $offre->is_verified ? 'Retirer la vérification' : 'Marquer comme vérifiée' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $offre->is_verified ? 'gpp_bad' : 'verified' }}</span>
                            </button>
                            <button type="button" wire:click="basculerLaPublication('{{ $offre->uuid }}')"
                                    class="st-icnbtn" title="{{ $offre->is_published ? 'Retirer du Hub' : 'Publier' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $offre->is_published ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $offre->uuid }}')"
                                    class="st-icnbtn" title="Modifier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                        @endcan
                        @can('delete', $offre)
                            <button type="button" wire:click="demanderLaSuppression('{{ $offre->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-card-list variant="staff">
            @foreach ($offres as $offre)
                <article class="st-card p-4">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-[#0F172A] mb-0">{{ $offre->title }}</p>
                            <p class="text-xs text-[#64748B] mb-0">{{ $offre->organisation }} · {{ $offre->type->libelle() }}</p>
                        </div>
                        <x-badge-statut variant="staff"
                                        :statut="$offre->is_published ? 'publie' : 'brouillon'"
                                        :label="$offre->is_published ? 'Publiée' : 'Brouillon'"
                                        class="shrink-0" />
                    </div>
                    <p class="text-xs text-[#64748B] mb-0">
                        Échéance {{ $offre->deadline?->translatedFormat('j M Y') ?? '—' }}
                        @unless ($offre->is_verified) · <span class="text-[#DC2626] font-semibold">non vérifiée</span> @endunless
                    </p>
                </article>
            @endforeach
        </x-card-list>

        <div class="mt-4">{{ $offres->links() }}</div>
    @endif

    {{-- ————————————————————————————————————————— le tiroir de saisie --}}
    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouvelle opportunité' : 'Modifier l’opportunité'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div class="ffield">
                <input id="offre-titre" type="text" wire:model="titre" placeholder=" ">
                <label for="offre-titre">Intitulé du poste ou de l'opportunité</label>
                @error('titre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <input id="offre-orga" type="text" wire:model="organisation" placeholder=" ">
                <label for="offre-orga">Organisation</label>
                @error('organisation') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="offre-portee" class="block text-sm font-medium text-[#0F172A] mb-1">Portée</label>
                    <select id="offre-portee" wire:model="portee" class="st-input w-full">
                        @foreach ($portees as $portee)
                            <option value="{{ $portee->value }}">{{ $portee->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('portee') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="offre-type" class="block text-sm font-medium text-[#0F172A] mb-1">Nature</label>
                    <select id="offre-type" wire:model="type" class="st-input w-full">
                        @foreach ($types as $unType)
                            <option value="{{ $unType->value }}">{{ $unType->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="ffield">
                <input id="offre-lieu" type="text" wire:model="lieu" placeholder=" ">
                <label for="offre-lieu">Lieu</label>
                @error('lieu') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <textarea id="offre-desc" wire:model="descriptif" rows="6" placeholder=" "></textarea>
                <label for="offre-desc">Descriptif</label>
                @error('descriptif') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <input id="offre-lien" type="url" wire:model="lienCandidature" placeholder=" ">
                <label for="offre-lien">Lien de candidature (https)</label>
                {{-- `https` exigé : une candidature transmet un nom, une adresse et
                     souvent une pièce d'identité. En clair, tout cela circule en clair. --}}
                @error('lienCandidature') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <input id="offre-echeance" type="date" wire:model="echeance" placeholder=" ">
                <label for="offre-echeance">Date limite de candidature</label>
                @error('echeance') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="publiee" class="mt-0.5">
                <span class="text-sm text-[#334155]">
                    Publier dans le Hub
                    <span class="block text-xs text-[#64748B]">
                        La publication et la vérification sont deux gestes distincts.
                        Une offre publiée non vérifiée apparaît en rouge dans la liste.
                    </span>
                </span>
            </label>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette opportunité ?"
                    description="La suppression est définitive. Une offre déjà publiée ne peut pas être supprimée — retirez-la du Hub d'abord."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
