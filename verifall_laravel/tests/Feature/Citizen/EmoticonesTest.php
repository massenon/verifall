<?php

declare(strict_types=1);

use App\Livewire\Citizen\Forum;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Le sélecteur d'émoticônes
|--------------------------------------------------------------------------
| Écrit à la main plutôt qu'emprunté. Les sélecteurs du marché pèsent de 200 Ko
| à plus d'un mégaoctet, parce qu'ils embarquent le jeu Unicode complet et ses
| noms dans plusieurs langues. Sur une connexion 3G au Bénin, c'est un temps
| d'attente réel pour un accessoire.
|
| S'y ajoutent deux contraintes du dépôt : aucune chaîne de compilation n'est
| active, et rien ne doit venir d'un CDN. Une bibliothèque tierce demanderait
| donc de ranimer l'une ou d'ouvrir l'autre, pour un bouton.
*/

beforeEach(function (): void {
    test()->seed(RolesAndPermissionsSeeder::class);
    $this->citoyen = User::factory()->create(['otp_verified_at' => now()]);
});

it('marque le champ du composeur, et charge son script', function (): void {
    $html = Livewire::actingAs($this->citoyen)->test(Forum::class)->html();

    expect($html)->toContain('data-emoticones');
});

describe('le fichier livré', function (): void {
    it('pèse quelques kilooctets, pas un mégaoctet', function (): void {
        /*
         | La raison d'être de tout ce travail. Si ce fichier venait à grossir
         | jusqu'à la taille d'une bibliothèque du marché, autant en prendre
         | une : elle serait mieux tenue.
         */
        $script = public_path('assets/js/pages/emoticones.js');

        expect(file_exists($script))->toBeTrue()
            ->and(filesize($script))->toBeLessThan(30 * 1024);
    });

    it('PRÉVIENT Livewire après chaque insertion', function (): void {
        /*
         | LE piège de ce genre de composant, et il est silencieux.
         |
         | Écrire dans la valeur d'un champ lié par `wire:model` sans émettre
         | d'événement `input` fait que le serveur ne voit jamais le
         | changement : l'émoticône s'affiche, puis disparaît au premier
         | rafraîchissement du composant. Rien n'échoue, rien n'alerte.
         */
        $source = (string) file_get_contents(public_path('assets/js/pages/emoticones.js'));

        expect($source)->toContain("new Event('input'")
            ->and($source)->toContain('bubbles: true');
    });

    it('insère AU CURSEUR, jamais à la fin', function (): void {
        // Quelqu'un qui revient corriger le début de sa phrase ne doit pas voir
        // son émoticône atterrir trois lignes plus bas.
        $source = (string) file_get_contents(public_path('assets/js/pages/emoticones.js'));

        expect($source)->toContain('setRangeText')
            ->and($source)->toContain('selectionStart');
    });

    it('se ré-équipe après un rendu Livewire', function (): void {
        /*
         | Livewire remplace des morceaux du document : sans ré-équipement, le
         | bouton disparaît après la première publication et ne revient qu'au
         | rechargement de la page.
         */
        $source = (string) file_get_contents(public_path('assets/js/pages/emoticones.js'));

        expect($source)->toContain('livewire:update')
            ->and($source)->toContain('vfEmojiPret');
    });

    it("n'appelle AUCUN service extérieur", function (): void {
        // Rien ne doit être chargé depuis un CDN, ni aucune police d'émoticônes
        // distante : la page doit rester entière hors ligne.
        $source = (string) file_get_contents(public_path('assets/js/pages/emoticones.js'));

        expect($source)->not->toContain('http://')
            ->and($source)->not->toContain('fetch(')
            ->and($source)->not->toContain('cdn.');
    });
});

it('déclare ses classes dans sa PROPRE feuille', function (): void {
    /*
     | Le build Tailwind est figé. Une classe absente ne produirait aucune
     | erreur, seulement un panneau sans largeur ni fond posé par-dessus le
     | texte — exactement le défaut qui a déjà coûté une fenêtre modale sans
     | largeur sur ce projet.
     */
    $feuille = (string) file_get_contents(public_path('assets/css/pages/emoticones.css'));

    foreach (['.vf-emoji-boite', '.vf-emoji-declencheur', '.vf-emoji-panneau', '.vf-emoji-grille'] as $classe) {
        expect($feuille)->toContain($classe);
    }
});
