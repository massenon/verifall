{{--
  | Activité de Signalements — quatorze jours, une barre par jour.
  |
  | AUCUNE BIBLIOTHÈQUE DE GRAPHES. Le build Tailwind est figé et la chaîne Vite
  | dormante : charger Chart.js voudrait dire un <script> distant de plus sur
  | chaque ouverture du back-office, pour dessiner quatorze rectangles. Des
  | hauteurs en pourcentage suffisent, se rendent sans JavaScript, et
  | s'impriment.
  |
  | Les hauteurs sont en STYLE EN LIGNE, et c'est délibéré : une hauteur est une
  | donnée, pas une classe. `h-[73%]` n'existerait pas dans le build figé et
  | n'aurait donc aucun effet — la barre serait plate, sans le moindre message
  | d'erreur.
--}}
@php
    // L'échelle se cale sur le pic de la fenêtre : à échelle fixe, deux
    // signalements dans un mois calme deviendraient invisibles.
    $pic = max(1, max(array_column($signalements, 'nombre')));
    $total = array_sum(array_column($signalements, 'nombre'));
@endphp

<div class="carte">
    <div class="carte-tete flex items-center justify-between gap-3">
        <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Activité de signalements</h2>
        <a href="{{ route('staff.content-reports') }}" class="text-xs font-bold text-brand-700 dark:text-brand">Tout voir →</a>
    </div>

    <div class="carte-corps">
        @if ($total === 0)
            {{-- Dire le vide plutôt que dessiner une ligne plate qu'on prendra
                 pour une panne de l'écran. --}}
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                Aucun signalement sur les quatorze derniers jours. C'est une bonne nouvelle,
                pas un écran vide.
            </p>
        @else
            <div class="flex items-end gap-1" style="height: 120px;">
                @foreach ($signalements as $jour)
                    <div class="flex-1 flex items-end" style="height: 100%;"
                         title="{{ $jour['etiquette'] }} — {{ $jour['nombre'] }} signalement(s)">
                        <div class="w-full"
                             style="height: {{ $jour['nombre'] === 0 ? 2 : round($jour['nombre'] * 100 / $pic) }}%;
                                    min-height: 2px;
                                    border-radius: 3px 3px 0 0;
                                    background: {{ $jour['nombre'] === 0 ? '#E2E8F0' : '#059669' }};"></div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between mt-2">
                <span class="text-xs text-encre-3 dark:text-nuit-3">{{ $signalements[0]['etiquette'] }}</span>
                <span class="text-xs text-encre-3 dark:text-nuit-3">
                    {{ $total }} sur 14 jours · pic à {{ $pic }}
                </span>
                <span class="text-xs text-encre-3 dark:text-nuit-3">{{ end($signalements)['etiquette'] }}</span>
            </div>
        @endif
    </div>
</div>
