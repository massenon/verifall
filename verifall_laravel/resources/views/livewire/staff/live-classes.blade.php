{{--
  | « Live Classes » côté administration (lot 2).
  |
  | L'entrée du menu latéral menait jusqu'ici à `citizen.live-classes` : un
  | administrateur basculait sur le guard `web` et tombait sur l'écran de
  | connexion CITOYEN, depuis le back-office. Aucun écran ne permettait de
  | programmer une séance.
  |
  | Gabarit CRUD ordinaire du back-office, cloné d'« Opportunités ».
  |
  | Le second bloc du tiroir — la rediffusion — pose la poignée que le lot 1
  | avait laissée manquante : les trois colonnes existaient, l'espace citoyen
  | savait les lire, et rien ne permettait de les remplir.
--}}
<div>

<x-premium.entete titre="Live Classes" :fil="['Live Classes']">
        Les séances en direct de l'Académie, et leur rediffusion. L'état — à venir, en direct, terminée — se déduit de l'heure : il n'y a pas de case à cocher.
        <x-slot:actions>
            @can('create', App\Models\LiveClass::class)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Programmer une séance
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Séances"
               :trend="$indicateurs['publiees'].' publiées'" />

        <x-kpi variant="staff" :value="number_format($indicateurs['aVenir'], 0, ',', ' ')"
               label="À venir"
               trend="Le lien visio s'ouvre 15 min avant" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publiees'], 0, ',', ' ')"
               label="Publiées"
               trend="Visibles côté citoyen" />

        {{--
          | Le chiffre qui appelle une action. Une séance passée sans
          | rediffusion affiche « Rediffusion à venir » côté citoyen — une
          | promesse que personne ne tient tant que le lien n'est pas déposé.
        --}}
        <x-kpi variant="staff" :value="number_format($indicateurs['avecReplay'], 0, ',', ' ')"
               label="Avec rediffusion"
               trend="Les autres affichent « à venir »" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="lc-q" class="etiquette">Rechercher un titre, un descriptif</label>
<input id="lc-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par moment">
            <option value="">Moment : tous</option>
            <option value="upcoming">À venir</option>
            <option value="past">Passées</option>
        </select>

        <select wire:model.live="filtre" class="champ w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="publiees">Publiées</option>
            <option value="brouillons">Brouillons</option>
            <option value="avec-replay">Avec rediffusion</option>
            <option value="sans-replay">Sans rediffusion</option>
        </select>
    </section>

    @if ($recherche !== '' || $filtreEtat !== '' || $filtre !== '')
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @if ($recherche !== '')
                <span class="pastille p-bon">« {{ $recherche }} »
                    <button type="button" wire:click="$set('recherche', '')" aria-label="Retirer la recherche">×</button>
                </span>
            @endif
            @if ($filtreEtat !== '')
                <span class="pastille p-bon">{{ $filtreEtat === 'upcoming' ? 'À venir' : 'Passées' }}
                    <button type="button" wire:click="$set('filtreEtat', '')" aria-label="Retirer le filtre de moment">×</button>
                </span>
            @endif
            @if ($filtre !== '')
                <span class="pastille p-bon">{{ $filtre }}
                    <button type="button" wire:click="$set('filtre', '')" aria-label="Retirer le filtre d'état">×</button>
                </span>
            @endif
            <button type="button" wire:click="reinitialiser" class="text-sm text-brand-700 dark:text-brand font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($seances->isEmpty())
        <x-empty-state variant="staff" icon="videocam"
                       title="Aucune séance"
                       description="Aucune séance ne correspond à ces critères. Tant qu'aucune n'est programmée, l'onglet « Live Classes » citoyen reste vide.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="btn-second">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$seances->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Séance</th>
                <th scope="col">Quand</th>
                <th scope="col">Inscrits</th>
                <th scope="col">État</th>
                <th scope="col">Rediffusion</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($seances as $seance)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $seance->title }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                            {{ $seance->course?->title ?? 'Séance ouverte' }}
                            · {{ $seance->host?->name ?? "L'équipe Vérif'All" }}
                        </p>
                    </td>
                    <td class="whitespace-nowrap">
                        {{ $seance->starts_at?->translatedFormat('j M Y · H:i') ?? '—' }}
                        <span class="block text-xs text-encre-3 dark:text-nuit-3">{{ $seance->minutes }} min</span>
                    </td>
                    <td class="whitespace-nowrap">{{ $seance->registrations_count }}{{ $seance->seats ? ' / '.$seance->seats : '' }}</td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <x-badge-statut variant="staff"
                                            :statut="$seance->is_published ? 'publie' : 'brouillon'"
                                            :label="$seance->is_published ? 'Publiée' : 'Brouillon'" />

                            @if ($seance->etat() === 'live')
                                <span class="pastille p-critique">En direct</span>
                            @elseif ($seance->etat() === 'past')
                                <span class="pastille p-info">Terminée</span>
                            @endif
                        </div>
                    </td>
                    <td class="whitespace-nowrap">
                        {{-- Trois états distincts, et la nuance compte : « déposée,
                             pas encore diffusée » n'est pas « rien ». --}}
                        @if ($seance->rediffusionDisponible())
                            <span class="pastille p-bon">En ligne</span>
                        @elseif ($seance->replay_url)
                            <span class="pastille p-info">Programmée</span>
                        @elseif ($seance->estPassee())
                            <span class="pastille p-critique">Manquante</span>
                        @else
                            <span class="text-xs text-encre-3 dark:text-nuit-3">—</span>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @can('update', $seance)
                            <button type="button" wire:click="basculerLaPublication('{{ $seance->uuid }}')"
                                    class="btn-icone" title="{{ $seance->is_published ? 'Retirer du calendrier' : 'Publier' }}">
                                <x-lucide :name="$seance->is_published ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $seance->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                        @endcan
                        @can('delete', $seance)
                            <button type="button" wire:click="demanderLaSuppression('{{ $seance->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $seances->links() }}</div>
    @endif

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Programmer une séance' : 'Modifier la séance'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div>
                <label for="lc-titre" class="etiquette">Titre de la séance</label>
<input id="lc-titre" type="text" wire:model="titre" class="champ">
                @error('titre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="lc-desc" class="etiquette">Au programme</label>
<textarea id="lc-desc" wire:model="descriptif" rows="4" class="champ !h-auto py-2.5"></textarea>
                @error('descriptif') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="lc-cours" class="block text-sm font-medium text-encre dark:text-white mb-1">Cours rattaché</label>
                    <select id="lc-cours" wire:model="coursId" class="champ w-full">
                        {{-- « Aucun » n'est pas un défaut paresseux : une séance hors
                             cours est ouverte à tout compte actif, y compris pour sa
                             rediffusion. C'est un choix éditorial. --}}
                        <option value="">Aucun — séance ouverte</option>
                        @foreach ($cours as $unCours)
                            <option value="{{ $unCours->id }}">{{ $unCours->title }}</option>
                        @endforeach
                    </select>
                    @error('coursId') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="lc-animateur" class="block text-sm font-medium text-encre dark:text-white mb-1">Animateur</label>
                    <select id="lc-animateur" wire:model="animateurId" class="champ w-full">
                        <option value="">L'équipe Vérif'All</option>
                        @foreach ($animateurs as $animateur)
                            <option value="{{ $animateur->id }}">{{ $animateur->name }}</option>
                        @endforeach
                    </select>
                    @error('animateurId') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="mb-0">
                    <label for="lc-debut" class="etiquette">Date et heure</label>
<input id="lc-debut" type="datetime-local" wire:model="debut" class="champ">
                    @error('debut') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="mb-0">
                    <label for="lc-duree" class="etiquette">Durée prévue (minutes)</label>
<input id="lc-duree" type="number" min="5" max="480" wire:model="duree" class="champ">
                    @error('duree') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="lc-visio" class="etiquette">Lien de visioconférence (https)</label>
<input id="lc-visio" type="url" wire:model="lienVisio" class="champ">
                {{-- Le lien n'est servi aux citoyens que 15 minutes avant : un lien
                     publié trois semaines à l'avance est un lien qui circule. --}}
                @error('lienVisio') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="lc-places" class="etiquette">Places (laisser vide si illimité)</label>
<input id="lc-places" type="number" min="1" wire:model="places" class="champ">
                @error('places') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="publiee" class="mt-0.5">
                <span class="text-sm text-encre-2 dark:text-nuit-2">
                    Publier au calendrier
                    <span class="block text-xs text-encre-3 dark:text-nuit-3">
                        Une séance non publiée n'apparaît nulle part côté citoyen.
                    </span>
                </span>
            </label>

            {{-- ══════════════════════════════════════════════ LA REDIFFUSION

                 Les trois colonnes existent depuis le lot 1 et rien ne
                 permettait de les remplir : la carte citoyenne aurait dit
                 « Rediffusion à venir » indéfiniment.                       --}}
            <fieldset class="border-t border-trait dark:border-nuit-trait pt-4">
                <legend class="text-sm font-bold text-encre dark:text-white mb-1">Rediffusion</legend>
                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-3">
                    Facultative. Tant qu'aucun lien n'est déposé, la carte citoyenne
                    annonce « Rediffusion à venir » plutôt que d'offrir un bouton mort.
                    Une <strong>mise en ligne</strong> vide diffuse tout de suite ; une date
                    future garde l'enregistrement au chaud jusqu'à elle.
                </p>

                <div>
                    <label for="lc-replay" class="etiquette">Lien de l'enregistrement (https)</label>
<input id="lc-replay" type="url" wire:model="lienReplay" class="champ">
                    @error('lienReplay') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="mb-0">
                        <input id="lc-replay-date" type="datetime-local" wire:model="replayPublieLe" placeholder=" ">
                        {{-- Libellé court : dans un champ de cette largeur, la
                             version longue se coupait à « Mise en ligne (vide = t… ».
                             Vu au passage navigateur. La précision passe dans l'aide
                             du fieldset, qui a la place de la porter. --}}
                        <label for="lc-replay-date">Mise en ligne</label>
                        {{-- Une séance s'enregistre le mardi et se publie le jeudi,
                             une fois relue et coupée. --}}
                        @error('replayPublieLe') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="lc-replay-duree" class="etiquette">Durée réelle (minutes)</label>
<input id="lc-replay-duree" type="number" min="1" max="480" wire:model="dureeEnregistrement" class="champ">
                        {{-- Distincte de la durée prévue : annoncer 60 min sur une
                             vidéo qui en fait 40 fait renoncer avant de lancer. --}}
                        @error('dureeEnregistrement') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette séance ?"
                    description="La suppression est définitive. Une séance déjà passée ne peut pas être supprimée : ses inscriptions et sa rediffusion en dépendent."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
