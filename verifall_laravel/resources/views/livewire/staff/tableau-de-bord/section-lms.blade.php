{{--
  | 09 — L'Académie (§21).
  |
  | « Examens passés » compte les copies REMISES sur la période ; le taux de
  | réussite ne porte que sur les copies CORRIGÉES — une copie en attente de
  | correction n'est ni un succès ni un échec, et la compter ferait baisser le
  | taux à chaque session d'examen.
--}}
<div>
    @if ($enErreur)
        @include('livewire.staff.tableau-de-bord.erreur', ['bloc' => 'Académie'])
    @else
        @php $l = $d['stats']; @endphp

        <dl class="tdb-stats is-6">
            <div class="tdb-stat">
                <dt>Apprenants actifs</dt>
                <dd>{{ number_format($l['apprenants_actifs'], 0, ',', ' ') }}</dd>
                <dd class="tdb-stat-x">Au moins une leçon terminée</dd>
            </div>
            <div class="tdb-stat">
                <dt>Inscriptions</dt>
                <dd>{{ number_format($l['inscriptions'], 0, ',', ' ') }}</dd>
                <dd class="tdb-stat-x">@include('livewire.staff.tableau-de-bord.variation', ['valeur' => $d['variation_inscriptions']])</dd>
            </div>
            <div class="tdb-stat">
                <dt>Cours terminés</dt>
                <dd>{{ number_format($l['cours_termines'], 0, ',', ' ') }}</dd>
                <dd class="tdb-stat-x">@include('livewire.staff.tableau-de-bord.variation', ['valeur' => $d['variation_termines']])</dd>
            </div>
            <div class="tdb-stat">
                <dt>Examens passés</dt>
                <dd>{{ number_format($l['examens_soumis'], 0, ',', ' ') }}</dd>
                <dd class="tdb-stat-x">{{ number_format($l['examens_corriges'], 0, ',', ' ') }} corrigé(s)</dd>
            </div>
            <div class="tdb-stat">
                <dt>Certifications délivrées</dt>
                <dd>{{ number_format($l['certificats'], 0, ',', ' ') }}</dd>
                <dd class="tdb-stat-x">@include('livewire.staff.tableau-de-bord.variation', ['valeur' => $d['variation_certificats']])</dd>
            </div>
            <div class="tdb-stat">
                <dt>Taux de réussite</dt>
                <dd>
                    @if ($d['taux_reussite'] === null)
                        <span class="tdb-na" title="Aucune copie corrigée sur la période.">—</span>
                    @else
                        {{ number_format($d['taux_reussite'], 1, ',', ' ') }} %
                    @endif
                </dd>
                <dd class="tdb-stat-x">Sur les copies corrigées</dd>
            </div>
        </dl>

        <article class="st-card tdb-card tdb-mt">
            <div class="tdb-card-h">
                <div>
                    <h3 class="tdb-h3">Inscriptions et complétions</h3>
                    <p class="tdb-meta">{{ $periodeCalculee->libelle() }}</p>
                </div>
            </div>
            <div class="tdb-card-b">
                @if (array_sum($l['serie_inscriptions']) + array_sum($l['serie_completions']) === 0)
                    <p class="tdb-vide">Aucune inscription ni complétion de cours sur la période.</p>
                @else
                    @include('livewire.staff.tableau-de-bord.graphe-courbe', [
                        'series' => [
                            ['nom' => 'Inscriptions', 'couleur' => '#059669', 'valeurs' => $l['serie_inscriptions']],
                            ['nom' => 'Cours terminés', 'couleur' => '#2563EB', 'valeurs' => $l['serie_completions']],
                        ],
                        'etiquettes' => $d['etiquettes'],
                        'titre' => 'Inscriptions et complétions de cours',
                    ])
                @endif
            </div>
        </article>
    @endif
</div>
