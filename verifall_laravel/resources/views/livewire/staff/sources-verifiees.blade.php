{{--
  | « Sources vérifiées » (lot 5).
  |
  | Ce référentiel n'est pas un annuaire : `AnalyseTechniqueAdapter` le consulte
  | à chaque analyse de lien, et une fiche active y désarme la règle d'usurpation
  | institutionnelle. La colonne « effet sur le moteur » existe pour cela — un
  | référentiel dont on ne voit pas la conséquence devient un tas de fiches que
  | plus personne n'ose retirer.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/searchBuilder.dataTables-1.8.4.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

<x-premium.entete titre="Sources vérifiées" :fil="['Sources vérifiées']">
        Le moteur d'analyse consulte ce référentiel à chaque lien examiné. Une source active n'est plus soupçonnée d'usurper une institution, et son nom apparaît dans le raisonnement rendu au citoyen.
        <x-slot:actions>
            @can('create', App\Models\TrustedSource::class)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Référencer une source
            </button>
        @endcan
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Sources"
               :trend="$indicateurs['actives'].' actives'" />

        <x-kpi variant="staff" :value="number_format($indicateurs['actives'], 0, ',', ' ')"
               label="Actives"
               trend="Reconnues par le moteur" />

        <x-kpi variant="staff" :value="number_format($indicateurs['familles'], 0, ',', ' ')"
               label="Familles"
               trend="Catégories de sources" />

        <x-kpi variant="staff" :value="$indicateurs['credibilite'].' / 100'"
               label="Crédibilité moyenne"
               trend="Sur les sources actives" />
    </x-kpi-grid>

    <div wire:ignore
         data-vdt="sources-verifiees"
         data-vdt-source="{{ route('staff.trusted-sources.data') }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\SourcesVerifiees::EVENEMENT_TABLE_A_RECHARGER }}">

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <div class="ffield flex-1 mb-0">
                <input id="src-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" placeholder=" ">
                <label for="src-q">Rechercher un nom, un domaine, une note</label>
            </div>

            <select data-vdt-filtre="famille" data-vdt-url="famille" data-vdt-livewire="filtreCategorie" class="st-input w-auto" aria-label="Filtrer par famille">
                <option value="">Famille : toutes</option>
                @foreach ($famillesEnUsage as $famille)
                    <option value="{{ $famille->uuid }}">{{ $famille->name }}</option>
                @endforeach
                <option value="{{ \App\Core\Application\Contracts\DepotSourcesFiables::SANS_FAMILLE }}">Sans famille</option>
            </select>

            <select data-vdt-filtre="etat" data-vdt-url="etat" data-vdt-livewire="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
                <option value="">État : tous</option>
                <option value="actives">Actives</option>
                <option value="inactives">Inactives</option>
            </select>

            <select data-vdt-filtre="confiance" data-vdt-url="confiance" class="st-input w-auto" aria-label="Filtrer par crédibilité">
                <option value="">Crédibilité : toutes</option>
                <option value="elevee">Élevée (80-100)</option>
                <option value="moyenne">Moyenne (50-79)</option>
                <option value="faible">Faible (0-49)</option>
            </select>

            <button type="button" data-vdt-reinitialiser class="st-btn st-btn-secondary">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-[#FCA5A5] bg-[#FEF2F2] text-[#991B1B] text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger le référentiel.</p>
            <button type="button" data-vdt-reessayer class="st-btn st-btn-secondary">Réessayer</button>
        </div>

        <table id="table-sources-verifiees" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Source</th>
                    <th scope="col">Domaine</th>
                    <th scope="col">Famille</th>
                    <th scope="col">Crédibilité</th>
                    <th scope="col">Effet sur le moteur</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Référencer une source' : 'Modifier la fiche'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div class="ffield">
                <input id="src-nom" type="text" wire:model="nom" placeholder=" ">
                <label for="src-nom">Nom de la source</label>
                @error('nom') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <input id="src-domaine" type="text" wire:model="domaine" placeholder=" ">
                <label for="src-domaine">Domaine</label>
                {{-- L'aide dit ce que la normalisation fait, pour qu'un « https://www. »
                     collé depuis le navigateur ne ressemble pas à une erreur. --}}
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Collez l'adresse si c'est plus simple : elle sera ramenée au domaine (<span class="font-mono">lemonde.fr</span>).
                    Les sous-domaines en héritent — <span class="font-mono">edition.cnn.com</span> trouve <span class="font-mono">cnn.com</span>.
                </p>
                @error('domaine') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="src-fam" class="block text-sm font-medium text-[#0F172A] mb-1">Famille</label>
                    <select id="src-fam" wire:model.live="categorieUuid" class="st-input w-full">
                        <option value="">— Sans famille —</option>
                        @foreach ($familles as $famille)
                            <option value="{{ $famille->uuid }}">{{ $famille->name }}</option>
                        @endforeach
                    </select>
                    @error('categorieUuid') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="src-cred" type="number" min="0" max="100" wire:model="credibilite" placeholder=" ">
                    <label for="src-cred">Crédibilité (0-100)</label>
                    @error('credibilite') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="ffield">
                <input id="src-url" type="url" wire:model="lien" placeholder=" ">
                <label for="src-url">Adresse du site (facultatif)</label>
                @error('lien') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="ffield">
                <textarea id="src-note" wire:model="note" rows="4" placeholder=" "></textarea>
                <label for="src-note">Note éditoriale</label>
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Pourquoi cette source est ici, ce qu'elle vaut, ce qu'on lui reproche.
                    Interne à la rédaction — jamais montrée au citoyen.
                </p>
                @error('note') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="active" class="mt-0.5">
                <span class="text-sm text-[#334155]">
                    Source active
                    <span class="block text-xs text-[#64748B]">
                        Décochée, la fiche reste ici mais le moteur cesse de la reconnaître.
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
                    title="Retirer cette source du référentiel ?"
                    description="La suppression est définitive, note éditoriale comprise. Pour que le moteur cesse simplement de la reconnaître, désactivez-la plutôt."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>

@push('scripts')
  <script src="{{ asset('assets/vendor/datatables/js/jquery-3.7.1.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables-2.3.8.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.buttons-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.html5-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.print-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.colVis-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.dateTime-1.6.3.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.searchBuilder-1.8.4.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.responsive-3.0.8.min.js') }}"></script>
  <script src="{{ asset('assets/js/verifall-datatables.js') }}"></script>
  <script src="{{ asset('assets/js/pages/staff-sources-verifiees.js') }}"></script>
@endpush
