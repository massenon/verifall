@props([
    'lien',
    'verdict',
    'affirmation',
    // `droite` : le panneau s'ouvre aligné sur le bord droit du bouton — le
    // cas de la barre d'actions, où le bouton est lui-même à droite de l'écran
    // et où un panneau aligné à gauche déborderait.
    'aligne' => 'droite',
    // `menu` : un bouton « Partager ▾ » qui ouvre un panneau.
    // `liste` : les canaux dépliés, sans bouton — pour une carte de colonne,
    // où un menu flottant dans un flux déjà vertical n'apporte rien.
    'forme' => 'menu',
])

@php
    $canaux = app(\App\Core\FactChecking\Services\PartageDuRapport::class)
        ->adresses($verdict, $affirmation, $lien);
@endphp

{{--
  | Le menu « Partager ».
  |
  | Alpine et non Livewire : ouvrir un menu n'est pas un changement d'état
  | métier, et un aller-retour serveur pour afficher cinq liens rendrait le
  | geste poussif. C'est le patron de `<x-staff.menu-actions>` — mais ses
  | classes `.st-menu` vivent dans `staff.css`, que ni le gabarit citoyen ni le
  | gabarit public ne chargent. D'où les `.vf-menu` de `pages/rapport.css`.
  |
  | Ouverture au CLIC, jamais au survol : sur un écran tactile, un survol est
  | un clic, et le menu s'ouvrirait en effleurant le bouton.
--}}
<div class="vf-partage" x-data="{ ouvert: {{ $forme === 'liste' ? 'true' : 'false' }}, copie: false }"
     @if($forme === 'menu') x-on:click.outside="ouvert = false" x-on:keydown.escape.window="ouvert = false" @endif>

    @if($forme === 'menu')
        <button type="button"
                class="vf-btn-secondaire inline-flex items-center gap-2 rounded-xl text-sm font-semibold px-4 py-2"
                x-on:click="ouvert = ! ouvert"
                x-bind:aria-expanded="ouvert ? 'true' : 'false'"
                aria-haspopup="true">
            <span class="material-symbols-rounded" aria-hidden="true">share</span>
            <span>{{ __('cit_rapport.partager') }}</span>
            <span class="material-symbols-rounded vf-chevron" aria-hidden="true">expand_more</span>
        </button>
    @endif

    <div class="vf-menu {{ $forme === 'liste' ? 'vf-menu--liste' : ($aligne === 'gauche' ? 'vf-menu--gauche' : 'vf-menu--droite') }}"
         @if($forme === 'menu') x-show="ouvert" x-cloak x-transition.opacity.duration.150ms style="display:none" @endif
         role="menu">

        <a href="{{ $canaux['whatsapp'] }}" target="_blank" rel="noopener noreferrer" role="menuitem"
           class="vf-menu-item" x-on:click="ouvert = false">
            <span class="vf-menu-ico vf-ico-whatsapp" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
            </span>
            WhatsApp
        </a>

        <a href="{{ $canaux['x'] }}" target="_blank" rel="noopener noreferrer" role="menuitem"
           class="vf-menu-item" x-on:click="ouvert = false">
            <span class="vf-menu-ico vf-ico-x" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 1.153h3.682l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932zm-1.293 19.482h2.039L6.486 3.24H4.298z"/></svg>
            </span>
            X
        </a>

        <a href="{{ $canaux['facebook'] }}" target="_blank" rel="noopener noreferrer" role="menuitem"
           class="vf-menu-item" x-on:click="ouvert = false">
            <span class="vf-menu-ico vf-ico-facebook" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.413c0-3.025 1.792-4.696 4.533-4.696 1.313 0 2.686.236 2.686.236v2.966h-1.513c-1.491 0-1.956.931-1.956 1.887v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
            </span>
            Facebook
        </a>

        <a href="{{ $canaux['courriel'] }}" role="menuitem" class="vf-menu-item" x-on:click="ouvert = false">
            <span class="vf-menu-ico vf-ico-courriel" aria-hidden="true">
                <span class="material-symbols-rounded">mail</span>
            </span>
            {{ __('cit_rapport.par_courriel') }}
        </a>

        <div class="vf-menu-sep" role="separator"></div>

        {{--
          | Copier le lien PUBLIC, jamais l'adresse de l'espace citoyen : celle
          | qu'on colle dans une conversation doit s'ouvrir chez le destinataire,
          | qui n'a pas de compte.
        --}}
        <button type="button" role="menuitem" class="vf-menu-item"
                x-on:click="
                    const fait = () => {
                        copie = true;
                        setTimeout(() => { copie = false; {{ $forme === 'menu' ? 'ouvert = false;' : '' }} }, 1800);
                    };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(@js($lien)).then(fait);
                    } else {
                        const zone = document.createElement('textarea');
                        zone.value = @js($lien); zone.setAttribute('readonly', '');
                        zone.style.position = 'absolute'; zone.style.left = '-9999px';
                        document.body.appendChild(zone); zone.select();
                        document.execCommand('copy'); document.body.removeChild(zone); fait();
                    }
                ">
            <span class="vf-menu-ico" aria-hidden="true">
                <span class="material-symbols-rounded" x-text="copie ? 'check' : 'link'">link</span>
            </span>
            <span x-show="!copie">{{ __('cit_rapport.copier_le_lien_public') }}</span>
            <span x-show="copie" x-cloak class="vf-copie-faite">{{ __('cit_rapport.lien_copie') }}</span>
        </button>
    </div>
</div>
