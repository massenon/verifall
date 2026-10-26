{{--
  | « Unités des Cours Premium » (lot 6).
  |
  | Le coût d'un cours est déjà modifiable dans sa fiche, un cours à la fois.
  | Mais fixer des prix est un geste COMPARATIF : on ne décide pas qu'un cours
  | vaut 30 unités dans l'absolu, on décide qu'il en vaut plus que celui d'à
  | côté. D'où une grille éditable en place, et non quinze tiroirs.
  |
  | Le revenu affiché est LU AU JOURNAL des unités, jamais recalculé : multiplier
  | le prix actuel par le nombre d'inscrits appliquerait le tarif d'aujourd'hui
  | à des achats d'hier, et donnerait un chiffre faux qui a l'air juste.
--}}
<div>

<x-premium.entete titre="Unités des cours premium" :fil="['Unités des cours premium']">
        La grille tarifaire de l'Académie, vue d'un bloc. Modifier un tarif n'affecte aucune inscription déjà payée : les inscrits gardent leur accès au prix consenti.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['payants'], 0, ',', ' ')"
               label="Cours payants" trend="Coût supérieur à zéro" />

        <x-kpi variant="staff" :value="number_format($indicateurs['gratuits'], 0, ',', ' ')"
               label="Cours gratuits" trend="Accessibles sans unité" />

        <x-kpi variant="staff" :value="$indicateurs['prix_moyen'].' u.'"
               label="Tarif moyen" trend="Sur les cours payants" />

        <x-kpi variant="staff" :value="number_format($indicateurs['revenu'], 0, ',', ' ')"
               label="Unités encaissées" trend="Lues au journal, tous cours" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="up-q" class="etiquette">Rechercher un cours</label>
<input id="up-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreTarif" class="champ w-auto" aria-label="Filtrer par tarif">
            <option value="payants">Payants</option>
            <option value="gratuits">Gratuits</option>
            <option value="">Tous</option>
        </select>
    </section>

    @if ($cours->isEmpty())
        <x-empty-state variant="staff" icon="payments"
                       title="Aucun cours dans cette vue"
                       description="Aucun cours ne correspond à ce filtre. Un cours à zéro unité est gratuit : c'est un tarif, pas une absence de tarif." />
    @else
        <x-data-table variant="staff" :count="$cours->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Cours</th>
                <th scope="col">Rayon</th>
                <th scope="col">Inscrits</th>
                <th scope="col">Unités encaissées</th>
                <th scope="col">Tarif</th>
            </x-slot:head>

            @foreach ($cours as $unCours)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $unCours->title }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                            {{ $unCours->is_published ? 'Publié' : 'Brouillon' }} · {{ $unCours->level?->libelle() }}
                        </p>
                    </td>
                    <td class="whitespace-nowrap text-sm text-encre-3 dark:text-nuit-3">
                        {{ $unCours->categorie?->name ?? 'Sans rayon' }}
                    </td>
                    <td class="whitespace-nowrap">{{ $unCours->enrollments_count }}</td>
                    <td class="whitespace-nowrap">
                        {{-- Lu au journal : c'est ce qui a été réellement débité, au
                             tarif qui s'appliquait alors. --}}
                        {{ number_format($revenus[$unCours->id] ?? 0, 0, ',', ' ') }}
                    </td>
                    <td class="whitespace-nowrap">
                        @can('update', $unCours)
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" max="500"
                                       wire:model="couts.{{ $unCours->uuid }}"
                                       class="champ w-20"
                                       aria-label="Tarif de {{ $unCours->title }}">
                                <button type="button"
                                        wire:click="enregistrerLeTarif('{{ $unCours->uuid }}')"
                                        class="btn-icone" title="Enregistrer ce tarif">
                                    <x-lucide name="check" class="w-4 h-4" />
                                </button>
                            </div>
                            @error('couts.'.$unCours->uuid)
                                <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p>
                            @enderror
                        @else
                            <span class="text-sm text-encre-2 dark:text-nuit-2">{{ $unCours->cost_units }} u.</span>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $cours->links() }}</div>
    @endif

    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-4 mb-0">
        Le tarif est en unités, jamais en francs : les unités sont une mesure d'usage interne,
        ni monnaie ni moyen de paiement — les CGU §5 le posent expressément.
    </p>
</div>
