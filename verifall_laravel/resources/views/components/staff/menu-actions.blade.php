@props([
    'fait',
    // Calculé UNE fois par la vue appelante : interroger la Policy sur chaque
    // ligne d'une page de douze en ferait douze fois le travail pour la même
    // réponse.
    'peutPublier' => false,
])

{{--
  | Menu ⋮ d'une ligne — le même en tableau et en carte mobile.
  |
  | Alpine et non Livewire : ouvrir un menu n'est pas un changement d'état
  | métier, et un aller-retour serveur pour afficher cinq boutons rendrait la
  | liste poussive. Ce que le menu DÉCLENCHE, en revanche, passe intégralement
  | par le serveur, qui revérifie chaque droit — l'absence de bouton n'est pas
  | une protection.
--}}
<div class="relative inline-block" x-data="{ ouvert: false }" x-on:click.outside="ouvert = false">
    <button type="button" class="btn-icone"
            x-on:click="ouvert = ! ouvert"
            x-bind:aria-expanded="ouvert ? 'true' : 'false'"
            aria-haspopup="true"
            aria-label="Actions sur « {{ $fait->title }} »">
        <x-lucide name="ellipsis-vertical" class="w-4 h-4" />
    </button>

    <div class="menu-flottant" x-show="ouvert" x-cloak style="display:none" role="menu">
        <button type="button" role="menuitem"
                wire:click="ouvrirApercu('{{ $fait->uuid }}')" x-on:click="ouvert = false">
            <x-lucide name="eye" class="w-4 h-4" /> Voir
        </button>

        @can('update', $fait)
            <button type="button" role="menuitem"
                    wire:click="ouvrirEdition('{{ $fait->uuid }}')" x-on:click="ouvert = false">
                <x-lucide name="pencil" class="w-4 h-4" /> Modifier
            </button>
        @endcan

        @can('create', \App\Models\VerifiedFact::class)
            <button type="button" role="menuitem"
                    wire:click="dupliquer('{{ $fait->uuid }}')" x-on:click="ouvert = false">
                <x-lucide name="copy" class="w-4 h-4" /> Dupliquer
            </button>
        @endcan

        @if ($peutPublier)
            {{-- Le libellé annonce ce qui va se passer, pas l'état courant. --}}
            <button type="button" role="menuitem"
                    wire:click="basculerLaPublication('{{ $fait->uuid }}')" x-on:click="ouvert = false">
                <x-lucide :name="$fait->is_published ? 'unpublished' : 'public'" class="w-4 h-4" />
                {{ $fait->is_published ? 'Dépublier' : 'Publier' }}
            </button>
        @endif

        @can('delete', $fait)
            <div class="menu-sep" role="separator"></div>

            <button type="button" class="menu-danger" role="menuitem"
                    wire:click="confirmerSuppression('{{ $fait->uuid }}')" x-on:click="ouvert = false">
                <x-lucide name="trash-2" class="w-4 h-4" /> Supprimer
            </button>
        @endcan
    </div>
</div>
