<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('rend les composants premium sans classe inventée', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-premium.entete titre="Mentors" sous="La liste" :fil="['Utilisateurs']"><x-slot:actions><a href="#">Nouveau</a></x-slot:actions></x-premium.entete>
        <x-premium.carte titre="Résumé" aide="7 jours">Corps</x-premium.carte>
        <x-premium.pastille ton="bon" icone="check">Publié</x-premium.pastille>
        <x-premium.chiffre valeur="12" libelle="Comptes" aide="1 au personnel" icone="users" />
        <x-premium.vide titre="Rien ici" texte="Créez le premier." />
    BLADE);

    expect($html)->toContain('Mentors', 'Utilisateurs', 'Nouveau', 'carte-titre', 'p-bon', '12', 'Rien ici')
        ->and($html)->not->toContain('{{');
});

it('échappe ce qu’on lui passe', function (): void {
    $html = Blade::render('<x-premium.carte :titre="$t">x</x-premium.carte>', ['t' => '<script>alert(1)</script>']);

    expect($html)->not->toContain('<script>alert(1)</script>');
});
