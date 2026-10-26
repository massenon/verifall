{{--
  | Performance Articles — Titre · Vues · Clics.
  |
  | « Clics » = départs vers la SOURCE (`source_url`), pas ouvertures de
  | l'article. C'est ce qui distingue un article lu d'un article qui a
  | réellement renvoyé quelqu'un à la preuve : sur une plateforme de
  | fact-checking, c'est la seconde chose qui compte.
  |
  | Le taux est affiché à côté du nombre. « 412 vues · 9 clics » demande un
  | calcul mental à chaque ligne ; « 2 % » se compare d'un coup d'œil, et c'est
  | l'écart entre les lignes qui informe.
--}}
<div class="carte">
    <div class="carte-tete flex items-center justify-between gap-3">
        <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Performance des articles</h2>
        <a href="{{ route('staff.hub-news') }}" class="text-xs font-bold text-brand-700 dark:text-brand">Tout voir →</a>
    </div>

    <div class="carte-corps">
        @forelse ($articles as $article)
            @php
                // Pas de taux sans dénominateur : « 0 % » sur un article jamais
                // ouvert affirmerait un échec là où il n'y a pas de mesure.
                $taux = $article->views > 0
                    ? round($article->source_clicks * 100 / $article->views, 1)
                    : null;
            @endphp
            <div class="flex items-center justify-between gap-3 py-2 border-b border-trait dark:border-nuit-trait last:border-0">
                <p class="text-sm text-encre dark:text-white mb-0 min-w-0 truncate" title="{{ $article->title }}">
                    {{ $article->title }}
                </p>
                <div class="shrink-0 text-right">
                    <p class="text-sm font-titre font-bold text-encre dark:text-white mb-0">
                        {{ number_format($article->views, 0, ',', ' ') }}
                        <span class="text-xs font-normal text-encre-3 dark:text-nuit-3">vues</span>
                    </p>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                        {{ number_format($article->source_clicks, 0, ',', ' ') }} clic{{ $article->source_clicks > 1 ? 's' : '' }}
                        @if ($taux !== null)
                            · {{ $taux }} %
                        @endif
                    </p>
                </div>
            </div>
        @empty
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                Aucun article publié. Le premier apparaîtra ici dès sa publication.
            </p>
        @endforelse
    </div>
</div>
