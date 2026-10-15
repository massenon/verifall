{{--
  | Une variation, avec son sens.
  |
  | La couleur dépend de ce que mesure l'indicateur : plus de vérifications est
  | une bonne nouvelle, plus de dossiers en attente une mauvaise. Une flèche
  | verte systématique sur « hausse » mentirait une fois sur deux.
  |
  | Sans variation calculable (référence nulle), on n'affiche RIEN plutôt que
  | « +100 % » : un chiffre exact peut tromper.
  |
  | Paramètres : $valeur (?float), $unite ('%'|'pts'), $sens ('hausse'|'baisse').
--}}
@php
    $unite ??= '%';
    $sens ??= 'hausse';
@endphp
@if ($valeur !== null)
    @php
        $ton = $valeur == 0 ? 'is-neutre' : (($valeur > 0) === ($sens === 'hausse') ? 'is-bon' : 'is-mauvais');
        $fleche = $valeur == 0 ? 'trending_flat' : ($valeur > 0 ? 'trending_up' : 'trending_down');
    @endphp
    <span class="tdb-var {{ $ton }}">
        <span class="material-symbols-rounded" style="font-size: 16px;" aria-hidden="true">{{ $fleche }}</span>
        {{ ($valeur > 0 ? '+' : '').number_format($valeur, 1, ',', ' ') }} {{ $unite }}
    </span>
@endif
