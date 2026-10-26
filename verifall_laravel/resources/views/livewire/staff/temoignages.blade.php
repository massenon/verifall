{{--
  | « Témoignages » (lot 9).
  |
  | Non publié PAR DÉFAUT, et c'est le seul choix défendable : un témoignage est
  | la parole de quelqu'un, publiée sous le nom de la plateforme. L'erreur n'a
  | pas la même conséquence dans les deux sens — un témoignage qui attend un
  | jour ne coûte rien ; un témoignage publié par inadvertance engage la
  | plateforme sur des propos qu'elle n'a pas relus.
  |
  | La note est NULLABLE. Un témoignage sans note n'est pas cinq étoiles.
--}}
<div>

<x-premium.entete titre="Témoignages" :fil="['Témoignages']">
        Un témoignage se relit avant de paraître : c'est la parole de quelqu'un, publiée sous le nom de la plateforme.
        <x-slot:actions>
            @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="btn-primaire">
                <x-lucide name="plus" class="w-4 h-4" />
                Nouveau témoignage
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Témoignages" trend="Recueillis" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publies'], 0, ',', ' ')"
               label="Publiés" trend="Visibles sur le site" />

        <x-kpi variant="staff" :value="number_format($indicateurs['en_attente'], 0, ',', ' ')"
               label="À relire" trend="Pas encore publiés" />

        {{-- `null` tant qu'aucun témoignage publié ne porte de note : une
             moyenne sur zéro avis est un chiffre inventé. --}}
        <x-kpi variant="staff" :value="$indicateurs['note_moyenne'] !== null ? $indicateurs['note_moyenne'].' / 5' : '—'"
               label="Note moyenne" trend="Sur les témoignages notés" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="tm-q" class="etiquette">Rechercher un nom, un texte</label>
<input id="tm-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreEtat" class="champ w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="publies">Publiés</option>
            <option value="en-attente">À relire</option>
        </select>
    </section>

    @if ($temoignages->isEmpty())
        <x-empty-state variant="staff" icon="reviews"
                       title="Aucun témoignage"
                       description="Aucun témoignage ne correspond à ces critères." />
    @else
        <x-data-table variant="staff" :count="$temoignages->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Personne</th>
                <th scope="col">Témoignage</th>
                <th scope="col">Note</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($temoignages as $temoignage)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $temoignage->author }}</p>
                        @if ($temoignage->role !== null)
                            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $temoignage->role }}</p>
                        @endif
                    </td>
                    <td class="text-sm text-encre-2 dark:text-nuit-2">« {{ Str::limit($temoignage->quote, 90) }} »</td>
                    <td class="whitespace-nowrap">
                        @if ($temoignage->rating !== null)
                            <span class="text-sm text-encre-2 dark:text-nuit-2">{{ $temoignage->rating }} / 5</span>
                        @else
                            {{-- Pas de note : pas cinq étoiles. Le tiret est
                                 l'information. --}}
                            <span class="text-xs text-encre-3 dark:text-nuit-3">—</span>
                        @endif
                    </td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$temoignage->is_published ? 'publie' : 'brouillon'"
                                        :label="$temoignage->is_published ? 'Publié' : 'À relire'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLaPublication('{{ $temoignage->uuid }}')"
                                    class="btn-icone" title="{{ $temoignage->is_published ? 'Retirer du site' : 'Publier' }}">
                                <x-lucide :name="$temoignage->is_published ? 'visibility_off' : 'visibility'" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $temoignage->uuid }}')"
                                    class="btn-icone" title="Modifier">
                                <x-lucide name="pencil" class="w-4 h-4" />
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $temoignage->uuid }}')"
                                    class="btn-icone text-critique" title="Supprimer">
                                <x-lucide name="trash-2" class="w-4 h-4" />
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $temoignages->links() }}</div>
    @endif

    @if ($peutGerer)
        <x-offcanvas-form :open="$formulaireOuvert"
                          :title="$enEdition === null ? 'Nouveau témoignage' : 'Modifier le témoignage'"
                          close="fermerLeTiroir">
            <form wire:submit="enregistrer" class="space-y-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="mb-0">
                        <label for="tm-auteur" class="etiquette">Nom de la personne</label>
<input id="tm-auteur" type="text" wire:model="auteur" class="champ">
                        @error('auteur') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="tm-role" class="etiquette">Qualité (ex. étudiante)</label>
<input id="tm-role" type="text" wire:model="qualite" class="champ">
                        @error('qualite') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="tm-quote" class="etiquette">Témoignage</label>
<textarea id="tm-quote" wire:model="citation" rows="4" class="champ !h-auto py-2.5"></textarea>
                    @error('citation') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="mb-0">
                        <label for="tm-note" class="etiquette">Note sur 5 (facultatif)</label>
<input id="tm-note" type="number" min="1" max="5" wire:model="note" class="champ">
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">Vide = pas de note, jamais cinq étoiles.</p>
                        @error('note') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>

                    <div class="mb-0">
                        <label for="tm-ordre" class="etiquette">Ordre d'affichage</label>
<input id="tm-ordre" type="number" min="0" wire:model="ordre" class="champ">
                        @error('ordre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                    </div>
                </div>

                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                    La publication est un geste à part, depuis la liste — un témoignage se relit d'abord.
                </p>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer ce témoignage ?"
                    description="La suppression est définitive. Pour le retirer du site sans l'effacer, dépubliez-le plutôt."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
