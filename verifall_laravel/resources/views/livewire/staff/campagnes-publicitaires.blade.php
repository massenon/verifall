{{--
  | « Campagnes Publicitaires » (lot 9).
  |
  | Le cache de `SeoServiceProvider` dure cinq minutes. Cinq minutes paraît peu ;
  | ça ne l'est pas quand on arrête une campagne PARCE QU'ELLE POSE PROBLÈME —
  | lien mort, formulation malheureuse, information devenue fausse. L'écran
  | oublie donc le cache à chaque écriture.
  |
  | L'état affiché est l'état RÉEL (« diffusée maintenant »), pas le seul
  | drapeau : une campagne active dont la fenêtre est passée ne paraît nulle
  | part, et la ligne doit le dire.
--}}
<div>

<x-premium.entete titre="Campagnes publicitaires" :fil="['Campagnes publicitaires']">
        Bandeaux et modales du site public. Une campagne a une fenêtre de diffusion : elle peut se préparer à l'avance et s'éteindre seule.
        <x-slot:actions>
            @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouvelle campagne
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Campagnes" trend="Toutes" />

        {{-- L'état RÉEL, par le scope du modèle : ce qui s'affiche, pas ce qui
             est coché. --}}
        <x-kpi variant="staff" :value="number_format($indicateurs['diffusees'], 0, ',', ' ')"
               label="Diffusées" trend="Visibles en ce moment" />

        <x-kpi variant="staff" :value="number_format($indicateurs['programmees'], 0, ',', ' ')"
               label="Programmées" trend="Actives, fenêtre à venir" />

        <x-kpi variant="staff" :value="number_format($indicateurs['terminees'], 0, ',', ' ')"
               label="Terminées" trend="Fenêtre passée" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="cp-q" class="etiquette">Rechercher une campagne</label>
<input id="cp-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="diffusees">Diffusées</option>
            <option value="programmees">Programmées</option>
            <option value="terminees">Terminées</option>
            <option value="inactives">Inactives</option>
        </select>
    </section>

    @if ($campagnes->isEmpty())
        <x-empty-state variant="staff" icon="campaign"
                       title="Aucune campagne"
                       description="Aucun bandeau ni modale ne s'affiche sur le site public. C'est un état correct." />
    @else
        <x-data-table variant="staff" :count="$campagnes->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Campagne</th>
                <th scope="col">Type</th>
                <th scope="col">Fenêtre</th>
                <th scope="col">État réel</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($campagnes as $campagne)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            {{-- Pastille rendue par `style` : la couleur est une donnée,
                                 et le build Tailwind est figé. --}}
                            <span class="inline-block w-3 h-3 rounded-full"
                                  style="background: {{ $campagne->color ?? '#059669' }}" aria-hidden="true"></span>
                            <div>
                                <p class="font-semibold text-encre dark:text-white mb-0">{{ $campagne->title }}</p>
                                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ Str::limit($campagne->text, 70) }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap">
                        <span class="pastille p-info">{{ $campagne->type === 'overlay' ? 'Modale' : 'Bandeau' }}</span>
                        @if ($campagne->type === 'overlay' && $campagne->delay_seconds > 0)
                            <span class="block text-xs text-encre-3 dark:text-nuit-3 mt-1">après {{ $campagne->delay_seconds }} s</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-sm text-encre-3 dark:text-nuit-3">
                        @if ($campagne->starts_at === null && $campagne->ends_at === null)
                            Sans borne
                        @else
                            {{ $campagne->starts_at?->translatedFormat('j M Y') ?? '…' }}
                            →
                            {{ $campagne->ends_at?->translatedFormat('j M Y') ?? '…' }}
                        @endif
                    </td>
                    <td>
                        @php($diffusee = $campagne->is_active
                            && ($campagne->starts_at === null || $campagne->starts_at <= now())
                            && ($campagne->ends_at === null || $campagne->ends_at >= now()))
                        @if ($diffusee)
                            <x-badge-statut variant="staff" statut="publie" label="Diffusée" />
                        @elseif (! $campagne->is_active)
                            <span class="pastille p-neutre">Inactive</span>
                        @elseif ($campagne->starts_at !== null && $campagne->starts_at > now())
                            <span class="pastille p-alerte">Programmée</span>
                        @else
                            {{-- Active mais fenêtre passée : ne paraît nulle part. Sans
                                 cet état, la ligne dirait « active » et personne ne
                                 comprendrait pourquoi rien ne s'affiche. --}}
                            <span class="pastille p-neutre">Terminée</span>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLActivation('{{ $campagne->uuid }}')"
                                    class="btn-icone" title="{{ $campagne->is_active ? 'Arrêter' : 'Activer' }}">
                                <x-lucide :name="$campagne->is_active ? 'pause' : 'play_arrow'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $campagne->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $campagne->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $campagnes->links() }}</div>
    @endif

    @if ($peutGerer)
        <x-offcanvas-form :open="$formulaireOuvert"
                          :title="$enEdition === null ? 'Nouvelle campagne' : 'Modifier la campagne'"
                          close="fermerLeTiroir">
            <form wire:submit="enregistrer" class="space-y-4">
                <div>
                    <label for="cp-titre" class="etiquette">Titre</label>
<input id="cp-titre" type="text" wire:model="titre" class="champ">
                    @error('titre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cp-texte" class="etiquette">Message</label>
<textarea id="cp-texte" wire:model="texte" rows="3" class="champ !h-auto py-2.5"></textarea>
                    @error('texte') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="cp-type" class="block text-sm font-medium text-encre dark:text-white mb-1">Type</label>
                        <select id="cp-type" wire:model.live="type" class="champ w-full">
                            <option value="banner">Bandeau</option>
                            <option value="overlay">Modale</option>
                        </select>
                        @error('type') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cp-couleur" class="block text-sm font-medium text-encre dark:text-white mb-1">Couleur</label>
                        <input id="cp-couleur" type="color" wire:model="couleur" class="champ w-full">
                        @error('couleur') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($type === 'overlay')
                    <div>
                        <label for="cp-delai" class="etiquette">Délai avant apparition (secondes)</label>
<input id="cp-delai" type="number" min="0" max="120" wire:model="delai" class="champ">
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                            Au-delà de deux minutes, la modale tombe sur quelqu'un qui lit — et se fait
                            fermer sans être lue.
                        </p>
                        @error('delai') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="mb-0">
                        <label for="cp-bouton" class="etiquette">Libellé du bouton</label>
<input id="cp-bouton" type="text" wire:model="bouton" class="champ">
                        @error('bouton') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="cp-lien" class="etiquette">Destination</label>
<input id="cp-lien" type="text" wire:model="lien" class="champ">
                        @error('lien') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                    Bouton et destination sont obligatoires : une campagne sans eux occuperait
                    le haut de chaque page pour dire quelque chose sans permettre d'y donner suite.
                    La destination accepte une adresse complète, un chemin du site
                    (<span class="font-mono">/verifier-une-information</span>) ou une ancre
                    (<span class="font-mono">#infolettre</span>).
                </p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="mb-0">
                        <label for="cp-debut" class="etiquette">Début (facultatif)</label>
<input id="cp-debut" type="datetime-local" wire:model="debut" class="champ">
                        @error('debut') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="cp-fin" class="etiquette">Fin (facultatif)</label>
<input id="cp-fin" type="datetime-local" wire:model="fin" class="champ">
                        @error('fin') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="cp-ordre" class="etiquette">Ordre</label>
<input id="cp-ordre" type="number" min="0" wire:model="ordre" class="champ">
                    @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model="active" class="mt-0.5">
                    <span class="text-sm text-encre-2 dark:text-nuit-2">
                        Campagne active
                        <span class="block text-xs text-encre-3 dark:text-nuit-3">
                            Elle paraîtra dans sa fenêtre de diffusion, ou immédiatement si aucune borne n'est fixée.
                        </span>
                    </span>
                </label>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cette campagne ?"
                    description="La suppression est définitive. Pour l'arrêter en gardant son contenu, désactivez-la plutôt — elle disparaît immédiatement du site."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
