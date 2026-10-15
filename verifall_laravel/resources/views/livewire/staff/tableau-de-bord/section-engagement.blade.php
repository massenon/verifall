{{--
  | 08 — Engagement des utilisateurs (§20).
  |
  | La question : « les utilisateurs viennent-ils, et utilisent-ils réellement
  | la plateforme ? ». Huit mesures, dont une honnêtement NON MESURÉE : les
  | sessions. La table `sessions` ne garde que les sessions ouvertes, pas leur
  | historique — la compter donnerait « qui est connecté maintenant », pas
  | « combien de sessions sur 30 jours ».
--}}
<div>
    @if ($enErreur)
        @include('livewire.staff.tableau-de-bord.erreur', ['bloc' => 'Engagement des utilisateurs'])
    @else
        <div class="tdb-grid tdb-grid-21">
            <dl class="tdb-stats is-pair" style="align-content: start;">
                @foreach ($d['mesures'] as $mesure)
                    <div class="tdb-stat" wire:key="eng-{{ $loop->index }}">
                        <dt>{{ $mesure['libelle'] }}</dt>
                        <dd>
                            @if ($mesure['valeur'] === null)
                                <span class="tdb-na" title="{{ $mesure['note'] }}">Non mesuré</span>
                            @else
                                {{ number_format($mesure['valeur'], 0, ',', ' ') }}
                            @endif
                        </dd>
                        @if ($mesure['variation'] !== null || ($mesure['note'] && $mesure['valeur'] !== null))
                            <dd class="tdb-stat-x">
                                @include('livewire.staff.tableau-de-bord.variation', ['valeur' => $mesure['variation']])
                                @if ($mesure['variation'] === null && $mesure['valeur'] !== null) {{ $mesure['note'] }} @endif
                            </dd>
                        @endif
                    </div>
                @endforeach
            </dl>

            <article class="st-card tdb-card">
                <div class="tdb-card-h">
                    <div>
                        <h3 class="tdb-h3">Nouveaux utilisateurs</h3>
                        <p class="tdb-meta">Inscriptions citoyennes · {{ $periodeCalculee->libelle() }}</p>
                    </div>
                </div>
                <div class="tdb-card-b">
                    @if (array_sum($d['serie_nouveaux']) === 0)
                        <p class="tdb-vide">Aucune inscription sur la période.</p>
                    @else
                        @include('livewire.staff.tableau-de-bord.graphe-courbe', [
                            'series' => [['nom' => 'Inscriptions', 'couleur' => '#059669', 'valeurs' => $d['serie_nouveaux']]],
                            'etiquettes' => $d['etiquettes'],
                            'titre' => 'Nouveaux utilisateurs',
                        ])
                    @endif
                </div>
            </article>
        </div>
    @endif
</div>
