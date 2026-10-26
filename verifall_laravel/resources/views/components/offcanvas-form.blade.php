@props([
    'variant' => 'staff',
    'id' => 'offcanvas',
    // Le libelle par defaut est resolu a l'affichage, pas ici : `@props` est
    // evalue avant que la locale de la requete soit forcement connue, et un
    // defaut fige rendrait « Formulaire » sur une page anglaise.
    'title' => null,
    /*
     | Ouverture pilotée par le SERVEUR quand l'appelant est un composant
     | Livewire. Le prototype ouvre le tiroir en JavaScript ; avec Livewire, ce
     | serait un second état parallèle à celui du composant, et les deux
     | finiraient par diverger — tiroir visible sur un formulaire déjà remis à
     | zéro, ou l'inverse après un échec de validation.
     */
    'open' => false,
    // Méthode Livewire appelée par la croix et par le fond. Sans elle, les
    // fermetures restent purement décoratives et le serveur croit le tiroir
    // encore ouvert.
    'close' => null,
])

@php $staff = $variant === 'staff'; @endphp

<div class="{{ $staff ? 'fond-tiroir' : 'modal-backdrop' }} {{ $open ? 'is-open' : '' }}"
     data-oc-backdrop
     @if ($close) wire:click="{{ $close }}" @endif
     aria-hidden="true"></div>

<aside class="{{ $staff ? 'tiroir' : 'modal' }} {{ $open ? 'is-open' : '' }}"
       data-oc
       id="{{ $id }}"
       role="dialog"
       aria-modal="true"
       @unless ($open) aria-hidden="true" @endunless
       aria-labelledby="{{ $id }}-title">
    <div class="{{ $staff ? 'tiroir-tete' : 'px-5 py-4 border-b border-line' }}">
        <h2 id="{{ $id }}-title"
            class="text-lg font-display font-bold {{ $staff ? 'text-encre dark:text-white' : 'text-ink' }} mb-0"
            data-oc-title>{{ $title ?? __('composants.offcanvas_formulaire') }}</h2>
        <button type="button" class="{{ $staff ? 'btn-icone' : 'p-2' }}"
                data-oc-close
                @if ($close) wire:click="{{ $close }}" @endif
                aria-label="{{ __('composants.offcanvas_fermer') }}">
            <x-lucide name="x" class="w-4 h-4" />
        </button>
    </div>

    <div class="{{ $staff ? 'tiroir-corps' : 'px-5 py-4' }}">{{ $slot }}</div>

    @isset($footer)
        <div class="{{ $staff ? 'tiroir-pied' : 'px-5 py-4 border-t border-line' }}">{{ $footer }}</div>
    @endisset
</aside>
