{{--
  | Performance — ce que les cours d'un mentor produisent.
  |
  | La colonne « Tendance » du prototype n'est PAS transposée : aucune série
  | temporelle ne la soutient, et une flèche inventée est pire qu'une colonne
  | absente. Voir le bloc de documentation du composant.
--}}
<div>

    <x-premium.entete titre="Performance">
        Quatre chiffres par cours, et un seul sur lequel vous pouvez agir : la complétion.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format(count($lignes), 0, ',', ' ')" label="Cours" />
        <x-kpi variant="staff" :value="number_format($totalApprenants, 0, ',', ' ')" label="Apprenants" />
        <x-kpi variant="staff" :value="$completionMoyenne.' %'" label="Complétion moyenne" />
    </x-kpi-grid>

    {{-- Une moyenne des moyennes, et on le dit. La calculer sur le total des
         inscrits serait plus juste mathématiquement, mais un mentor avec un cours
         à 500 inscrits et cinq à 10 ne verrait plus que le premier. --}}
    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-3 mb-5">
        La complétion moyenne pèse chaque cours à égalité, quel que soit son nombre
        d'inscrits : sans cela, le cours le plus fréquenté masquerait tous les autres.
    </p>

    @if ($lignes === [])
        <x-empty-state variant="staff" icon="insights"
                       title="Aucun cours ne vous est attribué"
                       description="Vos chiffres apparaîtront ici dès qu'un cours vous sera confié comme formateur." />
    @else
        <x-premium.barre-de-liste placeholder="Rechercher un cours…" />

        <x-data-table variant="staff" :count="$page->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Cours</th>
                <th scope="col">Apprenants</th>
                <th scope="col">Complétion</th>
                <th scope="col">Score moyen</th>
                <th scope="col">Avis</th>
                <th class="text-right">Action</th>
            </x-slot:head>

            @foreach ($page as $ligne)
                <tr wire:key="perf-{{ $ligne['cours']->uuid }}">
                    <td>
                        <p class="font-semibold text-encre dark:text-white mb-0">{{ $ligne['cours']->title }}</p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                            {{ $ligne['cours']->category }} · {{ $ligne['cours']->lessons_count }} leçon(s)
                        </p>
                    </td>

                    <td class="text-sm text-encre-2 dark:text-nuit-2">
                        {{ number_format($ligne['apprenants'], 0, ',', ' ') }}
                    </td>

                    <td style="min-width: 150px;">
                        @if ($ligne['apprenants'] === 0)
                            {{-- Un cours qui vient d'être publié n'a pas « échoué » :
                                 il n'a pas commencé. Le dire avec des mots plutôt
                                 qu'avec un 0 % trompeur. --}}
                            <span class="text-xs text-encre-3 dark:text-nuit-3">Pas encore d’inscrit</span>
                        @else
                            <div class="flex items-center gap-2">
                                <div style="flex:1; height:6px; border-radius:3px; background:#E2E8F0;">
                                    <div style="height:6px; border-radius:3px; background:#059669;
                                                width: {{ max(0, min(100, $ligne['completion'])) }}%;"></div>
                                </div>
                                <span class="text-xs text-encre-3 dark:text-nuit-3 shrink-0">{{ $ligne['completion'] }} %</span>
                            </div>
                        @endif
                    </td>

                    <td class="text-sm whitespace-nowrap">
                        @if ($ligne['scoreMoyen'] !== null)
                            <span class="font-semibold text-encre dark:text-white">{{ $ligne['scoreMoyen'] }} %</span>
                        @else
                            <span class="text-encre-3 dark:text-nuit-3">Aucun quiz passé</span>
                        @endif
                    </td>

                    <td class="text-sm whitespace-nowrap">
                        @if ($ligne['note'] !== null)
                            <span class="font-semibold text-encre dark:text-white">{{ number_format($ligne['note'], 1, ',', ' ') }}/5</span>
                            <span class="block text-xs text-encre-3 dark:text-nuit-3">
                                {{ trans_choice('{1}:count avis|[2,*]:count avis', $ligne['avis']) }}
                            </span>
                        @else
                            <span class="text-encre-3 dark:text-nuit-3">Aucun avis</span>
                        @endif
                    </td>

                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('staff.course-stats', ['slug' => $ligne['cours']->slug]) }}"
                           class="text-xs font-bold text-brand-700 dark:text-brand">Rapport de cours</a>
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-premium.pied-de-liste :liste="$page" />
    @endif
</div>
