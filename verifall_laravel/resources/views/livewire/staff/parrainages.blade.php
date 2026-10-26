{{--
  | « Parrainage » — la mesure du programme d'acquisition.
  |
  | Même gabarit que les autres écrans du back-office : fil d'Ariane, en-tête,
  | indicateurs, carte de recherche et filtres, jetons de filtres actifs, table
  | sur grand écran et cartes sur téléphone.
  |
  | AUCUNE action d'écriture, et c'est délibéré : un parrainage est la trace de
  | faits, pas une fiche. Voir la classe pour le raisonnement.
--}}
<div>

<x-premium.entete titre="Parrainage" :fil="['Parrainage']">
        Ce que le programme produit. La récompense part au moment où le filleul réalise sa première vérification éligible — jamais à l'inscription seule.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Parrainages"
               :trend="$indicateurs['inscrits'].' encore à l’étape inscription'" />

        <x-kpi variant="staff" :value="number_format($indicateurs['eligibles'], 0, ',', ' ')"
               label="Éligibles"
               trend="Première vérification faite, récompense à venir" />

        <x-kpi variant="staff" :value="number_format($indicateurs['recompenses'], 0, ',', ' ')"
               label="Récompensés"
               :trend="number_format($indicateurs['unites'], 0, ',', ' ').' unités distribuées'" />

        {{--
          | Le taux de transformation. Un tiret quand aucun parrainage n'existe
          | encore : « 0 % » se lirait « le programme ne marche pas », alors que
          | la vérité est « il n'a pas encore tourné ».
        --}}
        <x-kpi variant="staff"
               :value="$indicateurs['transformation'] !== null ? $indicateurs['transformation'].' %' : '—'"
               label="Transformation"
               :trend="$indicateurs['transformation'] !== null
                    ? 'Inscrits devenus vérificateurs'
                    : 'Aucun parrainage pour l’instant'" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 mb-0">
            <label for="parrainage-q" class="etiquette">Rechercher un parrain ou un filleul</label>
<input id="parrainage-q" type="search" wire:model.live.debounce.350ms="recherche" class="champ">
        </div>

        <select wire:model.live="filtreStatut" class="champ w-auto" aria-label="Filtrer par statut">
            <option value="">Statut : tous</option>
            @foreach ($statuts as $statut)
                <option value="{{ $statut->value }}">{{ $statut->libelle() }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtrePeriode" class="champ w-auto" aria-label="Filtrer par période">
            <option value="">Période : depuis toujours</option>
            <option value="7">7 derniers jours</option>
            <option value="30">30 derniers jours</option>
            <option value="90">90 derniers jours</option>
        </select>
    </section>

    @if ($recherche !== '' || $filtreStatut !== '' || $filtrePeriode !== '')
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @if ($recherche !== '')
                <span class="pastille p-bon">{{ $recherche }}
                    <button type="button" wire:click="$set('recherche', '')" aria-label="Retirer la recherche">×</button>
                </span>
            @endif
            @if ($filtreStatut !== '')
                <span class="pastille p-bon">{{ \App\Enums\StatutParrainage::from($filtreStatut)->libelle() }}
                    <button type="button" wire:click="$set('filtreStatut', '')" aria-label="Retirer le filtre de statut">×</button>
                </span>
            @endif
            @if ($filtrePeriode !== '')
                <span class="pastille p-bon">{{ $filtrePeriode }} derniers jours
                    <button type="button" wire:click="$set('filtrePeriode', '')" aria-label="Retirer le filtre de période">×</button>
                </span>
            @endif
            <button type="button" wire:click="reinitialiser" class="text-sm text-brand-700 dark:text-brand font-semibold underline">
                Réinitialiser
            </button>
        </div>
    @endif

    @if ($parrainages->isEmpty())
        <x-empty-state variant="staff" icon="group_add"
                       title="Aucun parrainage"
                       description="Aucun parrainage ne correspond à ces critères. Le programme se déclenche quand quelqu'un s'inscrit avec le code d'un autre.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="btn-second">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$parrainages->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Parrain</th>
                <th scope="col">Filleul</th>
                <th scope="col">Statut</th>
                <th scope="col">Inscrit le</th>
                <th scope="col">Éligible le</th>
                <th scope="col">Unités versées</th>
            </x-slot:head>

            @foreach ($parrainages as $parrainage)
                <tr>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $parrainage->referrer?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $parrainage->referrer?->email }}</p>
                    </td>
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $parrainage->referred?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $parrainage->referred?->email }}</p>
                    </td>
                    <td>
                        <x-badge-statut variant="staff" :statut="$parrainage->status->cleBadge()" :label="$parrainage->status->libelle()" />
                    </td>
                    <td class="whitespace-nowrap">{{ $parrainage->registered_at?->translatedFormat('j M Y') ?? '—' }}</td>
                    <td class="whitespace-nowrap">
                        {{-- Un tiret, pas une date inventée : beaucoup de parrainages
                             n'atteignent jamais cette étape, et c'est l'information. --}}
                        {{ $parrainage->qualified_at?->translatedFormat('j M Y') ?? '—' }}
                    </td>
                    <td class="tabular-nums">
                        @if ($parrainage->rewarded_at !== null)
                            {{ number_format((int) $parrainage->referrer_points + (int) $parrainage->referred_points, 0, ',', ' ') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-card-list variant="staff">
            @foreach ($parrainages as $parrainage)
                <article class="carte p-4">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-encre dark:text-white truncate mb-0">{{ $parrainage->referrer?->name ?? 'Compte supprimé' }}</p>
                            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                                a parrainé {{ $parrainage->referred?->name ?? 'un compte supprimé' }}
                            </p>
                        </div>
                        <x-badge-statut variant="staff" :statut="$parrainage->status->cleBadge()" :label="$parrainage->status->libelle()" class="shrink-0" />
                    </div>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                        Inscrit le {{ $parrainage->registered_at?->translatedFormat('j M Y') ?? '—' }}
                        @if ($parrainage->rewarded_at !== null)
                            · {{ number_format((int) $parrainage->referrer_points + (int) $parrainage->referred_points, 0, ',', ' ') }} unités versées
                        @endif
                    </p>
                </article>
            @endforeach
        </x-card-list>

        <div class="mt-4">{{ $parrainages->links() }}</div>
    @endif
</div>
