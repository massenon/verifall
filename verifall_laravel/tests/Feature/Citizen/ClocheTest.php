<?php

declare(strict_types=1);

use App\Core\Identity\Notifications\NotificationCitoyenne;
use App\Enums\CategorieNotification;
use App\Livewire\Citizen\Cloche;
use App\Models\User;
use Livewire\Livewire;

/** Une notification de test, sans dépendre d'un événement métier. */
final class NotificationDeTest extends NotificationCitoyenne
{
    public function __construct(
        private readonly string $message,
        private readonly CategorieNotification $cat,
        private readonly ?string $url = null,
    ) {}

    public function texte(): string
    {
        return $this->message;
    }

    public function icone(): string
    {
        return 'fact_check';
    }

    public function categorie(): CategorieNotification
    {
        return $this->cat;
    }

    public function lien(): ?string
    {
        return $this->url;
    }
}

beforeEach(function (): void {
    $this->citoyen = User::factory()->create(['email_verified_at' => now()]);
});

describe('la pastille', function (): void {
    it("ne s'allume pas quand il n'y a rien à lire", function (): void {
        /*
         | LE défaut corrigé ici. Le gabarit écrivait la pastille EN DUR : elle
         | était donc allumée en permanence, sur les vingt-cinq écrans citoyens
         | et pour tout le monde. Un signal permanent n'est plus un signal.
         */
        Livewire::actingAs($this->citoyen)
            ->test(Cloche::class)
            ->assertDontSeeHtml('data-notif-dot')
            ->assertSee('Aucune notification pour le moment.');
    });

    it("s'allume quand une notification arrive", function (): void {
        $this->citoyen->notify(new NotificationDeTest('Verdict : Faux', CategorieNotification::Verification));

        Livewire::actingAs($this->citoyen)
            ->test(Cloche::class)
            ->assertSeeHtml('data-notif-dot')
            ->assertSee('Verdict : Faux');
    });

    it("s'éteint quand on marque tout lu", function (): void {
        $this->citoyen->notify(new NotificationDeTest('A', CategorieNotification::Verification));
        $this->citoyen->notify(new NotificationDeTest('B', CategorieNotification::Apprentissage));

        Livewire::actingAs($this->citoyen)
            ->test(Cloche::class)
            ->assertSeeHtml('data-notif-dot')
            ->call('toutMarquerLu')
            ->assertDontSeeHtml('data-notif-dot');

        expect($this->citoyen->unreadNotifications()->count())->toBe(0);
    });

    it('marque TOUT, pas seulement l’onglet affiché', function (): void {
        /*
         | Le bouton dit « Tout marquer lu » et la pastille compte toutes les
         | catégories. Ne traiter que l'onglet courant laisserait la pastille
         | allumée après un clic censé l'éteindre — ce qui se lit comme un bug.
         */
        $this->citoyen->notify(new NotificationDeTest('Vérif', CategorieNotification::Verification));
        $this->citoyen->notify(new NotificationDeTest('Cours', CategorieNotification::Apprentissage));

        Livewire::actingAs($this->citoyen)
            ->test(Cloche::class)
            ->call('filtrer', CategorieNotification::Verification->value)
            ->call('toutMarquerLu');

        expect($this->citoyen->unreadNotifications()->count())->toBe(0);
    });
});

describe('les trois onglets', function (): void {
    it('filtre par catégorie', function (): void {
        $this->citoyen->notify(new NotificationDeTest('Une analyse', CategorieNotification::Verification));
        $this->citoyen->notify(new NotificationDeTest('Un certificat', CategorieNotification::Apprentissage));

        $cloche = Livewire::actingAs($this->citoyen)->test(Cloche::class);

        // « Tout » : les deux.
        $cloche->assertSee('Une analyse')->assertSee('Un certificat');

        $cloche->call('filtrer', CategorieNotification::Verification->value)
            ->assertSee('Une analyse')
            ->assertDontSee('Un certificat');

        $cloche->call('filtrer', CategorieNotification::Apprentissage->value)
            ->assertSee('Un certificat')
            ->assertDontSee('Une analyse');
    });

    it("n'expose que les notifications de son propre compte", function (): void {
        $autre = User::factory()->create(['email_verified_at' => now()]);
        $autre->notify(new NotificationDeTest('Secret du voisin', CategorieNotification::Verification));

        Livewire::actingAs($this->citoyen)
            ->test(Cloche::class)
            ->assertDontSee('Secret du voisin');
    });
});

describe('le contenu d’une notification', function (): void {
    it('rend un lien cliquable quand il y en a un, et rien sinon', function (): void {
        /*
         | Un lien mort est pire qu'aucun lien : il donne le sentiment d'un
         | défaut alors qu'il n'y avait rien à ouvrir.
         */
        $this->citoyen->notify(new NotificationDeTest('Avec lien', CategorieNotification::Verification, '/mon-espace'));

        Livewire::actingAs($this->citoyen)
            ->test(Cloche::class)
            ->assertSeeHtml('href="/mon-espace"');

        $autre = User::factory()->create(['email_verified_at' => now()]);
        $autre->notify(new NotificationDeTest('Sans lien', CategorieNotification::Apprentissage));

        Livewire::actingAs($autre)
            ->test(Cloche::class)
            ->assertSee('Sans lien')
            ->assertDontSeeHtml('href="/mon-espace"');
    });

    it('impose une catégorie à toute notification', function (): void {
        /*
         | `categorie()` est abstraite. Une notification qui l'oublierait
         | n'apparaîtrait dans aucun onglet thématique — présente dans « Tout »,
         | absente partout ailleurs, et personne ne le remarquerait.
         |
         | On le rend impossible plutôt qu'improbable.
         */
        $classe = new ReflectionClass(NotificationCitoyenne::class);

        expect($classe->isAbstract())->toBeTrue();

        foreach (['texte', 'icone', 'categorie'] as $methode) {
            expect($classe->getMethod($methode)->isAbstract())->toBeTrue();
        }
    });
});
