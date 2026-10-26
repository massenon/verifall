{{--
  | « Opportunités » — l'alimentation du Hub.
  |
  | Gabarit CRUD du back-office, identique aux autres écrans : fil d'Ariane,
  | en-tête avec l'action principale, indicateurs, carte de recherche et
  | filtres, jetons de filtres actifs, table sur grand écran et cartes sur
  | téléphone, tiroir de formulaire, modale de suppression nominative.
--}}
<div>

<x-premium.entete titre="Opportunités" :fil="['Opportunités']">
        Ce que le Hub propose à des gens qui cherchent du travail. La vérification est un geste distinct de la publication — et elle porte un nom.
        <x-slot:actions>
            @can('create', App\Models\HubJob::class)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvelle opportunité
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

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
        <div class="flex-1 mb-0">
            <label for="offres-q" class="etiquette">Rechercher un intitulé, une organisation, un lieu</label>
<input id="offres-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtrePortee" class="champ w-auto" aria-label="Filtrer par portée">
            <option value="">Portée : toutes</option>
            @foreach ($portees as $portee)
                <option value="{{ $portee->value }}">{{ $portee->libelle() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreType" class="champ w-auto" aria-label="Filtrer par nature">
            <option value="">Nature : toutes</option>
            @foreach ($types as $unType)
                <option value="{{ $unType->value }}">{{ $unType->libelle() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par état">
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
                <span class="pastille p-bon">{{ $recherche }}
                    <button type="button" wire:click="$set('recherche', '')" aria-label="Retirer la recherche">×</button>
                </span>
            @endif
            @if ($filtrePortee !== '')
                <span class="pastille p-bon">{{ \App\Enums\PorteeOpportunite::from($filtrePortee)->libelle() }}
                    <button type="button" wire:click="$set('filtrePortee', '')" aria-label="Retirer le filtre de portée">×</button>
                </span>
            @endif
            @if ($filtreType !== '')
                <span class="pastille p-bon">{{ \App\Enums\TypeOpportunite::from($filtreType)->libelle() }}
                    <button type="button" wire:click="$set('filtreType', '')" aria-label="Retirer le filtre de nature">×</button>
                </span>
            @endif
            @if ($filtreEtat !== '')
                <span class="pastille p-bon">{{ $filtreEtat }}
                    <button type="button" wire:click="$set('filtreEtat', '')" aria-label="Retirer le filtre d'état">×</button>
                </span>
            @endif
            <button type="button" wire:click="reinitialiser" class="text-sm text-brand-700 dark:text-brand font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($offres->isEmpty())
        <x-empty-state variant="staff" icon="work"
                       title="Aucune opportunité"
                       description="Aucune offre ne correspond à ces critères. Tant que le Hub est vide, l'onglet « Opportunités » citoyen l'est aussi.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="btn-second">
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
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $offre->title }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $offre->organisation }} · {{ $offre->location }}</p>
                    </td>
                    <td><span class="pastille p-info">{{ $offre->type->libelle() }}</span></td>
                    <td class="whitespace-nowrap">{{ $offre->scope->libelle() }}</td>
                    <td class="whitespace-nowrap">
                        {{-- Une échéance dépassée se signale : c'est elle qui décide
                             de la fermeture automatique une semaine plus tard. --}}
                        <span class="{{ $offre->deadline?->isPast() ? 'text-amber-700 dark:text-amber-300 font-semibold' : '' }}">
                            {{ $offre->deadline?->translatedFormat('j M Y') ?? '—' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-badge-statut variant="staff"
                                            :statut="$offre->is_published ? 'publie' : 'brouillon'"
                                            :label="$offre->is_published ? 'Publiée' : 'Brouillon'" />

                            @if ($offre->is_verified)
                                <span class="pastille p-bon">Vérifiée</span>
                            @elseif ($offre->is_published)
                                {{-- En ligne sans contrôle : l'état qu'on ne veut pas voir. --}}
                                <span class="pastille p-critique">Non vérifiée</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $offre)
                            <button type="button" wire:click="basculerLaVerification('{{ $offre->uuid }}')"
                                    class="btn-icone" title="{{ $offre->is_verified ? 'Retirer la vérification' : 'Marquer comme vérifiée' }}">
                                <x-lucide :name="$offre->is_verified ? 'gpp_bad' : 'verified'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="basculerLaPublication('{{ $offre->uuid }}')"
                                    class="btn-icone" title="{{ $offre->is_published ? 'Retirer du Hub' : 'Publier' }}">
                                <x-lucide :name="$offre->is_published ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $offre->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                        @endcan
                        @can('delete', $offre)
                            <button type="button" wire:click="demanderLaSuppression('{{ $offre->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-card-list variant="staff">
            @foreach ($offres as $offre)
                <article class="carte p-4">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-encre dark:text-white mb-0">{{ $offre->title }}</p>
                            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $offre->organisation }} · {{ $offre->type->libelle() }}</p>
                        </div>
                        <x-badge-statut variant="staff"
                                        :statut="$offre->is_published ? 'publie' : 'brouillon'"
                                        :label="$offre->is_published ? 'Publiée' : 'Brouillon'"
                                        class="shrink-0" />
                    </div>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                        Échéance {{ $offre->deadline?->translatedFormat('j M Y') ?? '—' }}
                        @unless ($offre->is_verified) · <span class="text-critique font-semibold">non vérifiée</span> @endunless
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
            <div>
                <label for="offre-titre" class="etiquette">Intitulé du poste ou de l'opportunité</label>
<input id="offre-titre" type="text" wire:model="titre" class="champ">
                @error('titre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="offre-orga" class="etiquette">Organisation</label>
<input id="offre-orga" type="text" wire:model="organisation" class="champ">
                @error('organisation') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="offre-portee" class="block text-sm font-medium text-encre dark:text-white mb-1">Portée</label>
                    <select id="offre-portee" wire:model="portee" class="champ w-full">
                        @foreach ($portees as $portee)
                            <option value="{{ $portee->value }}">{{ $portee->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('portee') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="offre-type" class="block text-sm font-medium text-encre dark:text-white mb-1">Nature</label>
                    <select id="offre-type" wire:model="type" class="champ w-full">
                        @foreach ($types as $unType)
                            <option value="{{ $unType->value }}">{{ $unType->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="offre-lieu" class="etiquette">Lieu</label>
<input id="offre-lieu" type="text" wire:model="lieu" class="champ">
                @error('lieu') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="offre-desc" class="etiquette">Descriptif</label>
<textarea id="offre-desc" wire:model="descriptif" rows="6" class="champ !h-auto py-2.5"></textarea>
                @error('descriptif') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="offre-lien" class="etiquette">Lien de candidature (https)</label>
<input id="offre-lien" type="url" wire:model="lienCandidature" class="champ">
                {{-- `https` exigé : une candidature transmet un nom, une adresse et
                     souvent une pièce d'identité. En clair, tout cela circule en clair. --}}
                @error('lienCandidature') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="offre-echeance" class="etiquette">Date limite de candidature</label>
<input id="offre-echeance" type="date" wire:model="echeance" class="champ">
                @error('echeance') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="publiee" class="mt-0.5">
                <span class="text-sm text-encre-2 dark:text-nuit-2">
                    Publier dans le Hub
                    <span class="block text-xs text-encre-3 dark:text-nuit-3">
                        La publication et la vérification sont deux gestes distincts.
                        Une offre publiée non vérifiée apparaît en rouge dans la liste.
                    </span>
                </span>
            </label>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette opportunité ?"
                    description="La suppression est définitive. Une offre déjà publiée ne peut pas être supprimée — retirez-la du Hub d'abord."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
