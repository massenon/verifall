{{--
  | 02 — La bande des indicateurs clés (§8-9).
  |
  | Hiérarchie typographique imposée : VALEUR, puis libellé, puis variation,
  | puis contexte. La valeur est écrite en premier dans le flux visuel (`order`
  | dans la feuille), le libellé reste en premier pour le lecteur d'écran.
  |
  | Une valeur `null` n'est jamais un zéro : elle s'affiche « Non mesuré », avec
  | la raison en note.
--}}
<div>
    @if ($enErreur)
        @include('livewire.staff.tableau-de-bord.erreur', ['bloc' => 'Indicateurs clés'])
    @else
        <div class="tdb-kpis">
            @foreach ($d['kpis'] as $kpi)
                @php
                    $valeur = $kpi['valeur'];
                    // Un taux sans dénominateur est MESURÉ et vide : « — », pas
                    // « Non mesuré », qui dirait que personne ne compte.
                    $affiche = $valeur === null
                        ? ($kpi['format'] === 'pourcent' ? '—' : 'Non mesuré')
                        : ($kpi['format'] === 'pourcent'
                            ? number_format((float) $valeur, 1, ',', ' ').' %'
                            : number_format((float) $valeur, 0, ',', ' '));
                    $serie = $kpi['serie'];
                @endphp

                <article class="tdb-kpi" wire:key="kpi-{{ $kpi['cle'] }}">
                    <div class="tdb-kpi-top">
                        <h3 class="tdb-kpi-l" title="{{ $kpi['libelle'] }}">{{ $kpi['libelle'] }}</h3>
                        <span class="tdb-kpi-ico" aria-hidden="true">
                            <span class="material-symbols-rounded">{{ $kpi['icone'] }}</span>
                        </span>
                    </div>

                    <p class="tdb-kpi-v {{ $valeur === null ? 'is-na' : '' }}">{{ $affiche }}</p>

                    <div class="tdb-kpi-var">
                        @include('livewire.staff.tableau-de-bord.variation', [
                            'valeur' => $kpi['variation'],
                            'unite' => $kpi['unite_variation'],
                            'sens' => $kpi['sens'],
                        ])
                        <span class="tdb-kpi-ctx">{{ $kpi['comparaison'] }}</span>
                    </div>

                    @if ($serie !== null && count($serie) > 1)
                        @php
                            $pic = max(1, max($serie));
                            $n = count($serie);
                            $points = collect($serie)
                                ->map(fn (int $v, int $i): string => round($i * 100 / ($n - 1), 2).','.round(26 - $v * 24 / $pic, 2))
                                ->implode(' ');
                        @endphp
                        {{-- Micro-tendance : décorative, le chiffre et la variation portent l'information. --}}
                        <svg class="tdb-kpi-spark" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                            <polyline points="{{ $points }}" fill="none" stroke="#059669" stroke-width="1.6"
                                      stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                        </svg>
                    @endif

                    @if ($kpi['note'])
                        <p class="tdb-kpi-note">{{ $kpi['note'] }}</p>
                    @endif

                    @if ($kpi['lien'])
                        <a class="tdb-kpi-lien" href="{{ route($kpi['lien']) }}">
                            <span class="sr-only">Ouvrir le détail : {{ $kpi['libelle'] }}</span>
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
