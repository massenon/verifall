<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\TypeContenu;
use App\Enums\UserRole;
use App\Enums\VerdictFactCheck;
use App\Livewire\Citizen\MesVerifications;
use App\Models\User;
use App\Models\Verification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Mes Vérifications — l'historique personnel
|--------------------------------------------------------------------------
| C'est la page où l'on revient chercher un rapport fermé trop vite : elle doit
| montrer les analyses EN COURS autant que les terminées.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->assignRole(UserRole::Citoyen->value);
    $this->citoyen->forceFill(['units' => 40])->save();

    Queue::fake();
});

function soumission(User $utilisateur, string $texte, TypeContenu $type = TypeContenu::Texte): Verification
{
    return app(SoumissionVerificationService::class)->soumettre($utilisateur, $type, $texte);
}

describe('accès', function (): void {
    it('refuse un visiteur anonyme', function (): void {
        $this->get(route('citizen.verifications'))->assertRedirect(route('login'));
    });

    it('ne montre que les vérifications du propriétaire', function (): void {
        soumission($this->citoyen, 'Ma propre rumeur à vérifier ici même.');

        $autre = User::factory()->actif()->create();
        $autre->forceFill(['units' => 10])->save();
        soumission($autre, 'La rumeur confidentielle de quelqu\'un d\'autre.');

        $html = Livewire::actingAs($this->citoyen)->test(MesVerifications::class)->html();

        expect($html)->toContain('Ma propre rumeur')
            ->and($html)->not->toContain('confidentielle');
    });
});

describe('historique', function (): void {
    it('affiche les analyses en cours autant que les terminées', function (): void {
        $terminee = soumission($this->citoyen, 'Une rumeur déjà analysée sur les taxes.');
        app(OrchestrateurVerification::class)->executer($terminee);

        soumission($this->citoyen, 'Une rumeur qui attend encore son analyse.');

        $html = Livewire::actingAs($this->citoyen)->test(MesVerifications::class)->html();

        // Sans les analyses en cours, la page ne servirait pas à retrouver un
        // rapport qu'on attend.
        expect(substr_count($html, 'data-row-el'))->toBe(2)
            ->and($html)->toContain('En file');
    });

    it('mène au rapport quand il existe, au suivi sinon', function (): void {
        $terminee = soumission($this->citoyen, 'Une rumeur déjà analysée entièrement.');
        app(OrchestrateurVerification::class)->executer($terminee);

        $enCours = soumission($this->citoyen, 'Une rumeur toujours en cours de traitement.');

        $html = Livewire::actingAs($this->citoyen)->test(MesVerifications::class)->html();

        expect($html)->toContain(route('citizen.report', ['verification' => $terminee->uuid]))
            ->and($html)->toContain(route('citizen.verification', ['verification' => $enCours->uuid]));
    });

    it('cherche dans l\'affirmation et le contexte', function (): void {
        soumission($this->citoyen, 'Une rumeur au sujet des transports urbains.');
        soumission($this->citoyen, 'Une rumeur au sujet de la santé publique.');

        $html = Livewire::actingAs($this->citoyen)->test(MesVerifications::class)
            ->set('recherche', 'transports')->html();

        expect(substr_count($html, 'data-row-el'))->toBe(1);
    });

    it('filtre par type de contenu', function (): void {
        soumission($this->citoyen, 'Une rumeur textuelle à vérifier ici.');
        soumission($this->citoyen, 'https://exemple-douteux.test/article', TypeContenu::Lien);

        $html = Livewire::actingAs($this->citoyen)->test(MesVerifications::class)
            ->set('filtreType', TypeContenu::Lien->value)->html();

        expect(substr_count($html, 'data-row-el'))->toBe(1);
    });

    it('filtre par verdict', function (): void {
        $verification = soumission($this->citoyen, 'La taxe WhatsApp entre en vigueur lundi.');
        app(OrchestrateurVerification::class)->executer($verification);

        $verdict = $verification->fresh()->report->verdict;

        $html = Livewire::actingAs($this->citoyen)->test(MesVerifications::class)
            ->set('filtreVerdict', $verdict->value)->html();

        expect(substr_count($html, 'data-row-el'))->toBe(1);
    });

    it('filtre les analyses en cours, qui ne sont pas un verdict', function (): void {
        $terminee = soumission($this->citoyen, 'Une rumeur déjà tranchée par les moteurs.');
        app(OrchestrateurVerification::class)->executer($terminee);

        soumission($this->citoyen, 'Une rumeur encore en file d\'attente ici.');

        $html = Livewire::actingAs($this->citoyen)->test(MesVerifications::class)
            ->set('filtreVerdict', 'en_cours')->html();

        // « En cours » est un état, pas un verdict ; le prototype les mélange
        // dans le même filtre, ce qui est juste pour qui cherche « où en est ma
        // demande ».
        expect(substr_count($html, 'data-row-el'))->toBe(1);
    });

    it('distingue « aucun résultat » de « rien fait »', function (): void {
        $composant = Livewire::actingAs($this->citoyen)->test(MesVerifications::class);

        // Les deux vides n'appellent pas la même action.
        expect($composant->html())->toContain('laboratoire est encore vide');

        soumission($this->citoyen, 'Une rumeur au sujet des transports urbains.');

        expect($composant->set('recherche', 'introuvable')->html())
            ->toContain('Aucun résultat');
    });

    it('réinitialise les filtres', function (): void {
        Livewire::actingAs($this->citoyen)->test(MesVerifications::class)
            ->set('recherche', 'quelque chose')
            ->set('filtreType', TypeContenu::Lien->value)
            ->call('reinitialiser')
            ->assertSet('recherche', '')
            ->assertSet('filtreType', '');
    });
});

describe('indicateurs', function (): void {
    it('compte sur tout l\'historique, pas sur la page filtrée', function (): void {
        foreach (['Une rumeur numéro un sur les taxes.', 'Une rumeur numéro deux sur la santé.'] as $texte) {
            app(OrchestrateurVerification::class)->executer(soumission($this->citoyen, $texte));
        }

        soumission($this->citoyen, 'Une troisième rumeur qui attend encore.');

        $composant = Livewire::actingAs($this->citoyen)->test(MesVerifications::class)
            ->set('recherche', 'introuvable');

        // Ce sont des repères sur son activité : ils changeraient de sens en
        // suivant les filtres.
        expect($composant->instance()->indicateurs['total'])->toBe(3)
            ->and($composant->instance()->indicateurs['en_cours'])->toBe(1);
    });

    it('réunit faux et trompeur sous « intox détectées »', function (): void {
        $verification = soumission($this->citoyen, 'La taxe WhatsApp entre en vigueur lundi.');
        app(OrchestrateurVerification::class)->executer($verification);

        $composant = Livewire::actingAs($this->citoyen)->test(MesVerifications::class);
        $verdict = $verification->fresh()->report->verdict;

        // Du point de vue de quelqu'un qui a failli partager, les deux sont
        // des pièges.
        if (in_array($verdict, [VerdictFactCheck::Faux, VerdictFactCheck::Trompeur], true)) {
            expect($composant->instance()->indicateurs['intox'])->toBe(1);
        } else {
            expect($composant->instance()->indicateurs['intox'])->toBe(0);
        }
    });

    it('n\'agrège jamais l\'activité de quelqu\'un d\'autre', function (): void {
        $autre = User::factory()->actif()->create();
        $autre->forceFill(['units' => 10])->save();
        soumission($autre, 'Trois rumeurs qui ne me concernent pas du tout.');
        soumission($autre, 'Une autre rumeur qui ne me concerne pas non plus.');

        $composant = Livewire::actingAs($this->citoyen)->test(MesVerifications::class);

        expect($composant->instance()->indicateurs['total'])->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| NEWINSTRUCTION29 — un rapport INSUFFISANT ne doit plus planter la page
|--------------------------------------------------------------------------
| Observé en production : « Call to a member function badge() on null »
| sur cette vue. La garde ne testait que `estMixte()` avant de déréférencer
| `verdict` — sans voir que `composition_status = Insuffisante` porte, lui
| aussi, un `verdict` NULL (contrat V-8). Un rapport insuffisant est le
| chemin le plus ORDINAIRE qui soit : n'importe quelle opinion ou question
| soumise en obtient un.
*/
describe('rapport INSUFFISANT — contrat V-8, non-régression NEWINSTRUCTION29', function (): void {
    it('affiche la page sans planter quand un rapport est INSUFFISANT (aucun claim factuel)', function (): void {
        $verification = soumission($this->citoyen, 'À mon avis, cette politique est une honte.');
        app(OrchestrateurVerification::class)->executer($verification);

        expect($verification->fresh()->report->composition_status->value)->toBe('insuffisante')
            ->and($verification->fresh()->report->verdict)->toBeNull();

        $this->actingAs($this->citoyen)
            ->get(route('citizen.verifications'))
            ->assertOk()
            ->assertSee($verification->fresh()->report->composition_status->libelle());
    });
});
