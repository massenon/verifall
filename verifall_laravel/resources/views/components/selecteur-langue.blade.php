@props(['variante' => 'public'])

@php
    $courante = \App\Enums\Langue::depuis(app()->getLocale());
    $sombre = $variante === 'sombre';
@endphp

{{--
  | Le sélecteur FR | EN.
  |
  | DEUX BOUTONS PLUTÔT QU'UNE LISTE DÉROULANTE. Avec deux langues, une liste
  | demande deux gestes — ouvrir, choisir — là où deux boutons n'en demandent
  | qu'un. Elle masque en outre la langue disponible derrière un clic, ce qui
  | fait qu'on ne découvre pas qu'elle existe.
  |
  | CHAQUE BOUTON EST UN FORMULAIRE POST. Changer la langue modifie l'état de
  | la session : un GET qui modifie l'état se déclenche par un préchargement de
  | navigateur ou une image distante posée sur un autre site, et quelqu'un
  | verrait sa langue changer sans avoir rien cliqué.
  |
  | La langue courante est marquée par `aria-current`, et non par la seule
  | couleur : au clavier comme au lecteur d'écran, la teinte ne se perçoit pas.
--}}
<div class="vf-langues" role="group" aria-label="{{ __('commun.langue.choisir') }}">
    @foreach (\App\Enums\Langue::cases() as $langue)
        @if ($langue === $courante)
            <span class="vf-langue is-active {{ $sombre ? 'vf-langue-sombre' : '' }}"
                  aria-current="true"
                  lang="{{ $langue->balise() }}"
                  title="{{ $langue->libelle() }}">{{ $langue->code() }}</span>
        @else
            <form method="POST" action="{{ route('langue', $langue->value) }}" class="vf-langue-form">
                @csrf
                <button type="submit"
                        class="vf-langue {{ $sombre ? 'vf-langue-sombre' : '' }}"
                        lang="{{ $langue->balise() }}"
                        title="{{ $langue->libelle() }}">{{ $langue->code() }}</button>
            </form>
        @endif
    @endforeach
</div>
