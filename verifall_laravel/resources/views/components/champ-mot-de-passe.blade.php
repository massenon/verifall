@props([
    'id',
    'label',
    'modele',
    'placeholder' => null,
    'autocomplete' => 'current-password',
    'requis' => false,
    // 'citoyen' | 'staff' | 'console' — voir le bloc ci-dessous.
    'variante' => 'citoyen',
])

@php($staff = $variante === 'staff')
@php($console = $variante === 'console')

{{--
  | Champ de mot de passe avec l'œil « voir / cacher ».
  |
  | Un composant plutôt que six copies : le bouton était présent sur DEUX des six
  | champs de mot de passe de l'application. Manquaient la confirmation
  | d'inscription, les deux champs de la réinitialisation, et la connexion du
  | personnel. Or c'est là qu'il sert le plus : on ne relit pas un mot de passe
  | qu'on connaît, on relit celui qu'on est en train d'inventer.
  |
  | ## La variante « staff », et pourquoi elle n'a pas coûté un second composant
  |
  | L'écran d'administration emploie `.ffield` là où le citoyen emploie
  | `.form-floating`. J'avais d'abord renoncé à y poser l'œil, en supposant que
  | la mise en page ne le supporterait pas.
  |
  | Vérification faite, elle le supporte : **`.ffield` est déjà
  | `position: relative`**, exactement ce qu'un bouton en position absolue exige,
  | et la règle vient de `citizen.css` — que la mise en page du personnel charge
  | aussi. Seule la classe du conteneur change ; le bouton, les icônes et le
  | comportement sont partagés.
  |
  | Le comportement vit dans `public/assets/js/auth-forms.js`, par délégation :
  | l'écouteur du prototype se pose au chargement, et un champ rendu plus tard
  | lui échappe.
  |
  | `aria-pressed` est la SOURCE DE VÉRITÉ, pas un reflet : c'est lui que le
  | script relit après un morph Livewire pour réaligner le `type` du champ.
--}}
{{-- Variante « console » (console premium, arbitrage n° 176) : étiquette
     au-dessus, champ du design system premium. Le bouton est le même. --}}
@if ($console)<label for="{{ $id }}" class="etiquette">{{ $label }}</label>@endif
<div class="{{ $staff ? 'ffield' : ($console ? 'relative' : 'form-floating relative') }}">
    <input type="password" class="{{ $console ? 'champ !h-11 pr-11' : 'pr-11' }}" id="{{ $id }}" wire:model.blur="{{ $modele }}"
           placeholder="{{ $staff ? ' ' : ($console ? '' : ($placeholder ?? $label)) }}" autocomplete="{{ $autocomplete }}"
           @if ($requis) required @endif
           {{ $attributes->except(['class']) }}>

    @unless ($console)<label for="{{ $id }}">{{ $label }}</label>@endunless

    <button type="button"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-body hover:text-heading {{ $console ? 'texte-3 hover:text-encre dark:hover:text-white' : '' }}"
            data-pwd-eye="{{ $id }}"
            aria-controls="{{ $id }}"
            aria-label="Afficher le mot de passe" aria-pressed="false">
        {{-- Œil ouvert : l'état actuel est « masqué », le clic montrera. --}}
        <svg data-pwd-icon="voir" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>

        {{-- Œil barré : masqué par défaut, révélé quand le mot de passe l'est.
             `hidden` et non une classe : l'attribut est ce que le script bascule,
             et il ne dépend d'aucune déclaration du build Tailwind figé. --}}
        <svg data-pwd-icon="cacher" hidden width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/><path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/></svg>
    </button>
</div>
