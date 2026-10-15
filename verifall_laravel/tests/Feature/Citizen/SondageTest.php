<?php

declare(strict_types=1);

use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\Sondages;
use App\Enums\UserRole;
use App\Livewire\Citizen\SondageDuSujet;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Thread;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Sondage d'un sujet — les résultats n'apparaissent qu'après le vote
|--------------------------------------------------------------------------
| Ce n'est pas une coquetterie d'interface : montrer les chiffres avant le vote
| oriente tous les votes suivants. Sur un forum où l'on discute de rumeurs
| politiques, cela transforme un sondage en machine à fabriquer du consensus.
| La règle est donc portée par le service, pas par la vue — une vue qui
| l'oublierait n'aurait rien à oublier, elle n'a pas les chiffres.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->auteur = citoyenDeSondage('auteur');

    $this->sujet = app(AnimationDuForum::class)->publier(
        $this->auteur,
        'Faut-il rendre obligatoire l\'étiquetage des contenus générés par IA ?',
        'La question revient à chaque publication virale.',
    );

    $this->sondage = app(Sondages::class)->creer(
        $this->auteur,
        $this->sujet,
        'Faut-il un étiquetage obligatoire ?',
        ['Oui, systématiquement', 'Oui, pour les contenus politiques', 'Non'],
    );
});

function citoyenDeSondage(string $nom): User
{
    $utilisateur = User::factory()->actif()->create(['name' => $nom]);
    $utilisateur->assignRole(UserRole::Citoyen->value);

    return $utilisateur;
}

/** Le libellé de la première option, pour lire ce que l'écran affiche. */
function premiereOption(Poll $sondage): string
{
    return (string) $sondage->options()->first()?->uuid;
}

describe('création', function (): void {
    it('refuse un sondage à une seule réponse', function (): void {
        $autreSujet = app(AnimationDuForum::class)->publier($this->auteur, 'Un autre fil', 'Corps.');

        // Une question à une seule réponse n'est pas une question.
        expect(fn () => app(Sondages::class)->creer($this->auteur, $autreSujet, 'Une question ?', ['Oui', ' ']))
            ->toThrow(ValidationException::class);
    });

    it('dédoublonne les réponses identiques', function (): void {
        $autreSujet = app(AnimationDuForum::class)->publier($this->auteur, 'Encore un fil', 'Corps.');

        // Deux options au même texte donneraient un sondage où l'on ne peut
        // pas savoir ce qu'on a choisi.
        expect(fn () => app(Sondages::class)->creer($this->auteur, $autreSujet, 'Une question ?', ['Oui', 'Oui']))
            ->toThrow(ValidationException::class);
    });

    it('n\'accepte qu\'un sondage par sujet', function (): void {
        expect(fn () => app(Sondages::class)->creer($this->auteur, $this->sujet, 'Une seconde question ?', ['Oui', 'Non']))
            ->toThrow(ValidationException::class);
    });

    it('refuse qu\'un tiers pose un sondage sur le fil de quelqu\'un d\'autre', function (): void {
        $intrus = citoyenDeSondage('intrus');
        $sujetDuTiers = app(AnimationDuForum::class)->publier($this->auteur, 'Le fil de l\'auteur', 'Corps.');

        Livewire::actingAs($intrus)
            ->test(SondageDuSujet::class, ['slug' => $sujetDuTiers->slug])
            ->call('creer')
            ->assertForbidden();
    });
});

describe('résultats', function (): void {
    it('ne montre AUCUN chiffre à qui n\'a pas voté', function (): void {
        $votant = citoyenDeSondage('votant');
        $curieux = citoyenDeSondage('curieux');

        $option = app(Sondages::class)->optionParUuid($this->sondage, premiereOption($this->sondage));
        app(Sondages::class)->voter($votant, $this->sondage, $option);

        // Le service ne rend rien : ce n'est pas une erreur, c'est la réponse
        // juste à quelqu'un qui ne s'est pas prononcé.
        expect(app(Sondages::class)->resultats($this->sondage->refresh(), $curieux))->toBeNull();

        Livewire::actingAs($curieux)
            ->test(SondageDuSujet::class, ['slug' => $this->sujet->slug])
            ->assertSee('Oui, systématiquement')
            ->assertDontSee('100 %')
            ->assertDontSee('participant');
    });

    it('les montre dès que la personne a voté', function (): void {
        $votant = citoyenDeSondage('votant');

        Livewire::actingAs($votant)
            ->test(SondageDuSujet::class, ['slug' => $this->sujet->slug])
            ->call('voter', premiereOption($this->sondage))
            ->assertSee('100 %')
            ->assertSee('votre réponse');
    });

    it('les montre à tout le monde une fois le sondage clos', function (): void {
        $votant = citoyenDeSondage('votant');
        $curieux = citoyenDeSondage('curieux');

        $option = app(Sondages::class)->optionParUuid($this->sondage, premiereOption($this->sondage));
        app(Sondages::class)->voter($votant, $this->sondage, $option);

        app(Sondages::class)->fermer($this->sondage);

        // Plus aucun vote à orienter : cacher les chiffres reviendrait à
        // effacer ce que des gens ont pris le temps de dire.
        expect(app(Sondages::class)->resultats($this->sondage->refresh(), $curieux))->not->toBeNull();
    });
});

describe('vote', function (): void {
    it('n\'enregistre qu\'un vote par personne, et le second remplace le premier', function (): void {
        $votant = citoyenDeSondage('votant');
        $options = $this->sondage->options()->get();

        app(Sondages::class)->voter($votant, $this->sondage, $options[0]);
        app(Sondages::class)->voter($votant, $this->sondage, $options[1]);

        expect(PollVote::where('poll_id', $this->sondage->id)->count())->toBe(1)
            ->and($this->sondage->refresh()->votes_count)->toBe(1)
            ->and($options[0]->refresh()->votes_count)->toBe(0)
            ->and($options[1]->refresh()->votes_count)->toBe(1);
    });

    it('refuse une option qui appartient à un autre sondage', function (): void {
        $autreSujet = app(AnimationDuForum::class)->publier($this->auteur, 'Un fil voisin', 'Corps.');
        $autreSondage = app(Sondages::class)->creer($this->auteur, $autreSujet, 'Autre question ?', ['A', 'B']);

        expect(fn () => app(Sondages::class)->voter(
            citoyenDeSondage('votant'),
            $this->sondage,
            $autreSondage->options()->first(),
        ))->toThrow(ValidationException::class);
    });

    it('refuse le vote sur un sondage clos', function (): void {
        app(Sondages::class)->fermer($this->sondage);

        expect(fn () => app(Sondages::class)->voter(
            citoyenDeSondage('votant'),
            $this->sondage->refresh(),
            $this->sondage->options()->first(),
        ))->toThrow(ValidationException::class);
    });

    it('n\'affiche aucune part avant le premier vote', function (): void {
        // Règle §5 du chantier : pas de « 0 % » là où la vérité est « pas
        // encore mesuré », et surtout pas trois barres égales qui laisseraient
        // croire à un partage d'opinions.
        $vierge = app(AnimationDuForum::class)->publier($this->auteur, 'Un fil tout neuf', 'Corps.');
        $sondage = app(Sondages::class)->creer($this->auteur, $vierge, 'Une question neuve ?', ['A', 'B']);

        expect($sondage->votes_count)->toBe(0)
            ->and($sondage->options()->get()->every(fn ($option): bool => $option->part(0) === 0))->toBeTrue();
    });
});

describe('sujet', function (): void {
    it('ne porte aucun sondage tant que personne n\'en a posé', function (): void {
        $nu = app(AnimationDuForum::class)->publier($this->auteur, 'Un fil sans sondage', 'Corps.');

        expect(app(Sondages::class)->pourSujet($nu))->toBeNull()
            ->and(Poll::where('thread_id', $nu->id)->exists())->toBeFalse();
    });

    it('disparaît avec le sujet', function (): void {
        // Cascade : un sondage orphelin n'a plus de question à poser.
        Thread::where('id', $this->sujet->id)->forceDelete();

        expect(Poll::where('thread_id', $this->sujet->id)->exists())->toBeFalse();
    });
});
