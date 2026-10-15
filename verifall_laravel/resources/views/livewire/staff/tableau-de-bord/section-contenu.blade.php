{{--
  | 06 — Performance du contenu (§13) : les volumes, type par type.
  |
  | Séparée du fact-checking, et séparée de l'analyse éditoriale qui suit : ici
  | on répond à « combien, et combien de nouveau ? », la section suivante à
  | « qu'est-ce qui est lu ? ».
  |
  | Un tiret dans une cellule veut dire « pas mesuré pour ce type » — expliqué
  | sous la table, jamais laissé à deviner.
--}}
<div>
    @if ($enErreur)
        @include('livewire.staff.tableau-de-bord.erreur', ['bloc' => 'Performance du contenu'])
    @else
        <article class="st-card tdb-card">
            <div class="tdb-card-h">
                <div>
                    <h3 class="tdb-h3">Contenus publiés</h3>
                    <p class="tdb-meta">Volumes et consultations · {{ $periodeCalculee->libelle() }}</p>
                </div>
            </div>

            <div class="tdb-table-wrap">
                <table class="st-table tdb-table">
                    <thead>
                        <tr>
                            <th scope="col">Type</th>
                            <th scope="col" class="is-num">Publiés</th>
                            <th scope="col" class="is-num is-secondaire">Total</th>
                            <th scope="col" class="is-num">Nouveaux</th>
                            <th scope="col" class="is-num">Vues</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($d['lignes'] as $ligne)
                            <tr wire:key="contenu-{{ $loop->index }}">
                                <th scope="row" style="text-align: left; font-weight: 600; color: var(--st-ink);">
                                    <span style="display: inline-flex; align-items: center; gap: .45rem;">
                                        <span class="material-symbols-rounded" style="font-size: 18px; color: var(--st-brand);" aria-hidden="true">{{ $ligne['icone'] }}</span>
                                        @if ($ligne['route'])
                                            <a href="{{ route($ligne['route']) }}" style="color: inherit;">{{ $ligne['libelle'] }}</a>
                                        @else
                                            {{ $ligne['libelle'] }}
                                        @endif
                                    </span>
                                </th>
                                <td class="is-num">{{ number_format($ligne['publies'], 0, ',', ' ') }}</td>
                                <td class="is-num is-secondaire">{{ $ligne['total'] === null ? '—' : number_format($ligne['total'], 0, ',', ' ') }}</td>
                                <td class="is-num">{{ $ligne['publies_periode'] === null ? '—' : number_format($ligne['publies_periode'], 0, ',', ' ') }}</td>
                                <td class="is-num">
                                    @if ($ligne['vues'] === null)
                                        <span class="tdb-na" title="Les consultations de ce type de contenu ne sont pas suivies.">Non mesuré</span>
                                    @else
                                        {{ number_format($ligne['vues'], 0, ',', ' ') }}
                                        @include('livewire.staff.tableau-de-bord.variation', ['valeur' => $ligne['variation_vues']])
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="tdb-card-b" style="padding-top: .75rem;">
                <p class="tdb-meta">
                    « Nouveaux » : publiés sur la période. « Vues » : visites humaines, une par personne
                    et par jour, robots et aperçus de partage exclus. Faits et cours : visites non suivies.
                </p>
            </div>
        </article>
    @endif
</div>
