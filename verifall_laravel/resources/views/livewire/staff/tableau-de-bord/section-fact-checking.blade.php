{{--
  | 05 — Performance du fact-checking (§12).
  |
  | Grand graphique à gauche (les vérifications dans le temps), répartition des
  | verdicts à droite, chiffres de synthèse au-dessus. La synthèse vient AVANT le
  | graphique : on lit le total, puis on regarde sa forme.
  |
  | « Verdicts » porte sur les vérifications SOUMISES dans la période et qui ont
  | un rapport. Une vérification encore en file n'a pas de verdict : elle
  | n'entre pas dans l'anneau, et le total de l'anneau le dit.
--}}
<div>
    @if ($enErreur)
        @include('livewire.staff.tableau-de-bord.erreur', ['bloc' => 'Performance du fact-checking'])
    @else
        @php $s = $d['stats']; @endphp

        <dl class="tdb-stats is-6">
            <div class="tdb-stat">
                <dt>Vérifications soumises</dt>
                <dd>{{ number_format($s['soumises'], 0, ',', ' ') }}</dd>
                <dd class="tdb-stat-x">
                    @include('livewire.staff.tableau-de-bord.variation', ['valeur' => $d['variation']])
                    @if ($d['variation'] === null) Pas de référence précédente @endif
                </dd>
            </div>
            <div class="tdb-stat">
                <dt>Terminées</dt>
                <dd>{{ number_format($s['terminees'], 0, ',', ' ') }}</dd>
            </div>
            <div class="tdb-stat">
                <dt>En cours</dt>
                <dd>{{ number_format($s['en_cours'], 0, ',', ' ') }}</dd>
                <dd class="tdb-stat-x">File, analyse ou reprise</dd>
            </div>
            <div class="tdb-stat">
                <dt>En revue humaine</dt>
                <dd>{{ number_format($s['revue_humaine'], 0, ',', ' ') }}</dd>
            </div>
            <div class="tdb-stat">
                <dt>Échouées</dt>
                <dd style="{{ $s['echouees'] > 0 ? 'color: #B91C1C;' : '' }}">{{ number_format($s['echouees'], 0, ',', ' ') }}</dd>
            </div>
            <div class="tdb-stat">
                <dt>Temps moyen d'analyse</dt>
                <dd>
                    @if ($d['duree'] !== null)
                        {{ $d['duree'] }}
                    @else
                        <span class="tdb-na" title="Aucune vérification terminée sur la période.">—</span>
                    @endif
                </dd>
                <dd class="tdb-stat-x">De la soumission à la fin</dd>
            </div>
        </dl>

        <div class="tdb-grid tdb-grid-21 tdb-mt">
            <article class="st-card tdb-card">
                <div class="tdb-card-h">
                    <div>
                        <h3 class="tdb-h3">Vérifications dans le temps</h3>
                        <p class="tdb-meta">Soumissions par {{ ['heure' => 'heure', 'jour' => 'jour', 'semaine' => 'semaine', 'mois' => 'mois'][$periodeCalculee->granularite()] }} · {{ $periodeCalculee->libelle() }}</p>
                    </div>
                </div>
                <div class="tdb-card-b">
                    @if ($s['soumises'] === 0)
                        <p class="tdb-vide">Aucune vérification soumise sur la période. La courbe apparaîtra à la première demande.</p>
                    @else
                        @include('livewire.staff.tableau-de-bord.graphe-courbe', [
                            'series' => [['nom' => 'Vérifications', 'couleur' => '#059669', 'valeurs' => $s['serie']]],
                            'etiquettes' => $d['etiquettes'],
                            'titre' => 'Vérifications soumises',
                        ])
                    @endif
                </div>
            </article>

            <article class="st-card tdb-card">
                <div class="tdb-card-h">
                    <div>
                        <h3 class="tdb-h3">Répartition des verdicts</h3>
                        <p class="tdb-meta">Vérifications de la période ayant un rapport</p>
                    </div>
                </div>
                <div class="tdb-card-b">
                    @if ($d['total_verdicts'] === 0)
                        <p class="tdb-vide">Aucun verdict rendu sur la période.</p>
                    @else
                        @include('livewire.staff.tableau-de-bord.graphe-anneau', [
                            'parts' => $d['verdicts'],
                            'total' => $d['total_verdicts'],
                            'legende' => 'verdicts',
                        ])
                    @endif
                </div>
            </article>
        </div>

        @if ($d['types'] !== [])
            <article class="st-card tdb-card tdb-mt">
                <div class="tdb-card-h">
                    <h3 class="tdb-h3">Types de contenus soumis</h3>
                </div>
                <div class="tdb-card-b">
                    <ul class="tdb-parts tdb-grid tdb-grid-3" style="gap: .75rem 1.5rem;">
                        @foreach ($d['types'] as $type)
                            <li>
                                <div class="tdb-part-l">
                                    <span style="display: inline-flex; align-items: center; gap: .35rem;">
                                        <span class="material-symbols-rounded" style="font-size: 17px; color: var(--st-muted);" aria-hidden="true">{{ $type['icone'] }}</span>
                                        {{ $type['libelle'] }}
                                    </span>
                                    <strong>{{ number_format($type['nombre'], 0, ',', ' ') }}</strong>
                                </div>
                                <div class="tdb-part-b" aria-hidden="true"><span style="width: {{ $type['part'] }}%;"></span></div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </article>
        @endif
    @endif
</div>
