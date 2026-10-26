@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/staff-tableau-de-bord.css') }}">
@endpush
{{--
  | Courbe(s) sur la période — SVG rendu par le serveur, sans bibliothèque.
  |
  | Aucune bibliothèque de graphes n'existe dans le projet, et il n'en faut pas :
  | le build est figé, la chaîne Vite dormante, et deux polylignes s'écrivent en
  | SVG. Le graphe se dessine sans JavaScript, survit aux re-rendus Livewire
  | (rien à ré-initialiser) et s'imprime.
  |
  | `preserveAspectRatio="none"` étire le repère à la largeur de la carte ;
  | `vector-effect="non-scaling-stroke"` garde un trait de 2 px malgré l'étirement.
  |
  | Accessibilité (§49) : le SVG porte un résumé, et un tableau masqué donne
  | chaque valeur au lecteur d'écran.
  |
  | Paramètres : $series (list<{nom, couleur, valeurs: list<int>}>),
  | $etiquettes (list<string>), $titre (string).
--}}
@php
    $longueur = max(array_map(static fn (array $s): int => count($s['valeurs']), $series) ?: [0]);
    $maximum = max([1, ...array_map(static fn (array $s): int => max($s['valeurs'] ?: [0]), $series)]);
    $abscisse = static fn (int $i): float => $longueur <= 1 ? 50.0 : round($i * 100 / ($longueur - 1), 3);
    $ordonnee = static fn (int $v): float => round(38 - ($v * 34 / $maximum), 3);
    $milieu = intdiv(max(0, count($etiquettes) - 1), 2);
@endphp

<div class="tdb-graphe">
    @if (count($series) > 1)
        <ul class="tdb-legende">
            @foreach ($series as $serie)
                <li><i style="background: {{ $serie['couleur'] }};" aria-hidden="true"></i>{{ $serie['nom'] }}</li>
            @endforeach
        </ul>
    @endif

    <svg viewBox="0 0 100 40" preserveAspectRatio="none" role="img"
         aria-label="{{ $titre }} — maximum {{ number_format($maximum, 0, ',', ' ') }} sur la période">
        @foreach ([4, 21, 38] as $y)
            <line x1="0" x2="100" y1="{{ $y }}" y2="{{ $y }}" stroke="#E2E8F0" stroke-width="1" vector-effect="non-scaling-stroke" />
        @endforeach

        @foreach ($series as $rang => $serie)
            @php
                $points = collect($serie['valeurs'])->map(fn (int $v, int $i): string => $abscisse($i).','.$ordonnee($v))->implode(' ');
            @endphp
            @if ($rang === 0 && $longueur > 1)
                <polygon points="0,38 {{ $points }} 100,38" fill="{{ $serie['couleur'] }}" fill-opacity=".08" />
            @endif
            <polyline points="{{ $points }}" fill="none" stroke="{{ $serie['couleur'] }}" stroke-width="2"
                      stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
        @endforeach
    </svg>

    <span class="tdb-graphe-max" aria-hidden="true">{{ number_format($maximum, 0, ',', ' ') }}</span>

    @if ($etiquettes !== [])
        <div class="tdb-graphe-axe" aria-hidden="true">
            <span>{{ $etiquettes[0] }}</span>
            @if (count($etiquettes) > 2)
                <span>{{ $etiquettes[$milieu] }}</span>
            @endif
            <span>{{ end($etiquettes) }}</span>
        </div>
    @endif

    <table class="sr-only">
        <caption>{{ $titre }}</caption>
        <thead>
            <tr>
                <th scope="col">Tranche</th>
                @foreach ($series as $serie)
                    <th scope="col">{{ $serie['nom'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($etiquettes as $i => $etiquette)
                <tr>
                    <th scope="row">{{ $etiquette }}</th>
                    @foreach ($series as $serie)
                        <td>{{ $serie['valeurs'][$i] ?? 0 }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
