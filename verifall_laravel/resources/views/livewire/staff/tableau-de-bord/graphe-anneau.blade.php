{{--
  | Anneau de répartition — SVG, sans bibliothèque.
  |
  | Le cercle a une circonférence de 100 (r = 15,9155) : chaque arc se dessine
  | donc directement en pourcentage, sans trigonométrie dans la vue. La légende
  | porte les nombres ET les parts : l'anneau seul ne se lit pas au lecteur
  | d'écran, ni à la précision d'un point.
  |
  | Paramètres : $parts (list<{libelle, couleur, nombre, part}>), $total (int),
  | $legende (string, sous le total).
--}}
<div class="tdb-anneau">
    <svg viewBox="0 0 42 42" role="img" aria-label="Répartition sur {{ $total }} — détail dans la légende">
        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#EEF2F6" stroke-width="5" />
        @php $cumul = 0.0; @endphp
        @foreach ($parts as $part)
            @if ($part['part'] > 0)
                <circle cx="21" cy="21" r="15.9155" fill="none" stroke="{{ $part['couleur'] }}" stroke-width="5"
                        stroke-dasharray="{{ $part['part'] }} {{ 100 - $part['part'] }}"
                        stroke-dashoffset="{{ 25 - $cumul }}" />
                @php $cumul += $part['part']; @endphp
            @endif
        @endforeach
        <text x="21" y="21.5" text-anchor="middle" class="tdb-anneau-centre" font-size="8">{{ number_format($total, 0, ',', ' ') }}</text>
        <text x="21" y="27" text-anchor="middle" class="tdb-anneau-sous">{{ $legende }}</text>
    </svg>

    <ul class="tdb-parts">
        @foreach ($parts as $part)
            <li>
                <div class="tdb-part-l">
                    <span style="display: inline-flex; align-items: center; gap: .4rem;">
                        <i style="display: inline-block; width: 10px; height: 10px; border-radius: 3px; background: {{ $part['couleur'] }};" aria-hidden="true"></i>
                        {{ $part['libelle'] }}
                    </span>
                    <strong>{{ number_format($part['nombre'], 0, ',', ' ') }} · {{ number_format($part['part'], 1, ',', ' ') }} %</strong>
                </div>
            </li>
        @endforeach
    </ul>
</div>
