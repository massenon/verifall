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
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Campagnes publicitaires</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Campagnes publicitaires</h1>
            <p class="text-[#64748B] text-sm mb-0">
                Bandeaux et modales du site public. Une campagne a une fenêtre de diffusion :
                elle peut se préparer à l'avance et s'éteindre seule.
            </p>
        </div>

        @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Nouvelle campagne
            </button>
        @endif
    </header>

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
        <div class="ffield flex-1 mb-0">
            <input id="cp-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="cp-q">Rechercher une campagne</label>
        </div>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
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
                                <p class="font-semibold text-[#0F172A] mb-0">{{ $campagne->title }}</p>
                                <p class="text-xs text-[#64748B] mb-0">{{ Str::limit($campagne->text, 70) }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap">
                        <span class="st-badge st-badge-info">{{ $campagne->type === 'overlay' ? 'Modale' : 'Bandeau' }}</span>
                        @if ($campagne->type === 'overlay' && $campagne->delay_seconds > 0)
                            <span class="block text-xs text-[#64748B] mt-1">après {{ $campagne->delay_seconds }} s</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">
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
                            <span class="st-badge st-badge-draft">Inactive</span>
                        @elseif ($campagne->starts_at !== null && $campagne->starts_at > now())
                            <span class="st-badge st-badge-warn">Programmée</span>
                        @else
                            {{-- Active mais fenêtre passée : ne paraît nulle part. Sans
                                 cet état, la ligne dirait « active » et personne ne
                                 comprendrait pourquoi rien ne s'affiche. --}}
                            <span class="st-badge st-badge-arch">Terminée</span>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLActivation('{{ $campagne->uuid }}')"
                                    class="st-icnbtn" title="{{ $campagne->is_active ? 'Arrêter' : 'Activer' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $campagne->is_active ? 'pause' : 'play_arrow' }}</span>
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $campagne->uuid }}')"
                                    class="st-icnbtn" title="Modifier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $campagne->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
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
                <div class="ffield">
                    <input id="cp-titre" type="text" wire:model="titre" placeholder=" ">
                    <label for="cp-titre">Titre</label>
                    @error('titre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <textarea id="cp-texte" wire:model="texte" rows="3" placeholder=" "></textarea>
                    <label for="cp-texte">Message</label>
                    @error('texte') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="cp-type" class="block text-sm font-medium text-[#0F172A] mb-1">Type</label>
                        <select id="cp-type" wire:model.live="type" class="st-input w-full">
                            <option value="banner">Bandeau</option>
                            <option value="overlay">Modale</option>
                        </select>
                        @error('type') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cp-couleur" class="block text-sm font-medium text-[#0F172A] mb-1">Couleur</label>
                        <input id="cp-couleur" type="color" wire:model="couleur" class="st-input w-full">
                        @error('couleur') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($type === 'overlay')
                    <div class="ffield">
                        <input id="cp-delai" type="number" min="0" max="120" wire:model="delai" placeholder=" ">
                        <label for="cp-delai">Délai avant apparition (secondes)</label>
                        <p class="text-xs text-[#64748B] mt-1 mb-0">
                            Au-delà de deux minutes, la modale tombe sur quelqu'un qui lit — et se fait
                            fermer sans être lue.
                        </p>
                        @error('delai') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="ffield mb-0">
                        <input id="cp-bouton" type="text" wire:model="bouton" placeholder=" ">
                        <label for="cp-bouton">Libellé du bouton</label>
                        @error('bouton') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="ffield mb-0">
                        <input id="cp-lien" type="text" wire:model="lien" placeholder=" ">
                        <label for="cp-lien">Destination</label>
                        @error('lien') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <p class="text-xs text-[#64748B] mb-0">
                    Bouton et destination sont obligatoires : une campagne sans eux occuperait
                    le haut de chaque page pour dire quelque chose sans permettre d'y donner suite.
                    La destination accepte une adresse complète, un chemin du site
                    (<span class="font-mono">/verifier-une-information</span>) ou une ancre
                    (<span class="font-mono">#infolettre</span>).
                </p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="ffield mb-0">
                        <input id="cp-debut" type="datetime-local" wire:model="debut" placeholder=" ">
                        <label for="cp-debut">Début (facultatif)</label>
                        @error('debut') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="ffield mb-0">
                        <input id="cp-fin" type="datetime-local" wire:model="fin" placeholder=" ">
                        <label for="cp-fin">Fin (facultatif)</label>
                        @error('fin') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="ffield">
                    <input id="cp-ordre" type="number" min="0" wire:model="ordre" placeholder=" ">
                    <label for="cp-ordre">Ordre</label>
                    @error('ordre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model="active" class="mt-0.5">
                    <span class="text-sm text-[#334155]">
                        Campagne active
                        <span class="block text-xs text-[#64748B]">
                            Elle paraîtra dans sa fenêtre de diffusion, ou immédiatement si aucune borne n'est fixée.
                        </span>
                    </span>
                </label>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
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
