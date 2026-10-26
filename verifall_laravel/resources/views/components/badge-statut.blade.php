@props([
    'variant' => 'citizen',
    // Clé sémantique — jamais une classe CSS passée depuis l'appelant, sinon
    // le vocabulaire dérive page après page.
    'statut' => 'info',
    'label' => null,
])

@php
    $staff = $variant === 'staff';

    /*
     | Libellés de verdict EXACTS (DICTIONNAIRE_COPYWRITING) : VRAI · FAUX ·
     | TROMPEUR · NON VÉRIFIABLE. Centralisés ici pour qu'aucune vue ne puisse
     | écrire « Faux » ou « Non vérifié » à la place.
     |
     | La pastille ● accompagne toujours la couleur : un verdict ne doit jamais
     | se lire à la seule teinte (accessibilité, et règle de marque).
     */
    $map = [
        'vrai' => ['citizen' => 'vbadge vbadge-true', 'staff' => 'pastille p-bon', 'defaut' => 'VRAI'],
        'faux' => ['citizen' => 'vbadge vbadge-false', 'staff' => 'pastille p-critique', 'defaut' => 'FAUX'],
        'trompeur' => ['citizen' => 'vbadge vbadge-doubt', 'staff' => 'pastille p-alerte', 'defaut' => 'TROMPEUR'],
        'non-verifiable' => ['citizen' => 'vbadge', 'staff' => 'pastille', 'defaut' => 'NON VÉRIFIABLE'],
        'en-cours' => ['citizen' => 'vbadge vbadge-progress', 'staff' => 'pastille p-info', 'defaut' => 'En cours'],
        'publie' => ['citizen' => 'vbadge vbadge-true', 'staff' => 'pastille p-bon', 'defaut' => 'Publié'],
        'brouillon' => ['citizen' => 'vbadge', 'staff' => 'pastille p-neutre', 'defaut' => 'Brouillon'],
        'archive' => ['citizen' => 'vbadge', 'staff' => 'pastille p-neutre', 'defaut' => 'Archivé'],
        'premium' => ['citizen' => 'vbadge', 'staff' => 'pastille p-info', 'defaut' => 'Premium'],
        'danger' => ['citizen' => 'vbadge vbadge-false', 'staff' => 'pastille p-critique', 'defaut' => 'Alerte'],
        'info' => ['citizen' => 'vbadge', 'staff' => 'pastille p-info', 'defaut' => 'Information'],
    ];

    $key = $map[$statut] ?? $map['info'];
    $classes = $staff ? $key['staff'] : $key['citizen'];
    $texte = $label ?? trim($slot->toHtml()) ?: $key['defaut'];
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{-- .pastille fournit deja sa pastille via ::before ; .vbadge non.
         Sans cette distinction, le staff affichait deux points. --}}
    @unless ($staff)<span aria-hidden="true">●</span>@endunless{{ $texte }}
</span>
