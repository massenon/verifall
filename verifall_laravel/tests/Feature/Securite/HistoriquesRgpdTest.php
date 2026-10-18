<?php

declare(strict_types=1);

use App\Core\Identity\Services\ExportDonneesService;
use App\Core\Identity\Services\HistoriqueDuHub;
use App\Core\Identity\Services\JournalDesConnexions;
use App\Core\Identity\Services\SecurityLogService;
use App\Core\Identity\Services\SuppressionCompteService;
use App\Models\ConsultationHub;
use App\Models\HistoriqueConnexion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Arbitrage n° 178 — les deux historiques rattachés à un compte
|--------------------------------------------------------------------------
| Connexions : intérêt légitime de sécurité, minimisées, 12 mois.
| Lectures du Hub : consentement explicite, 90 jours, effacées au retrait.
*/

describe('historique des connexions', function (): void {
    it('journalise une connexion réussie sans IP entière ni User-Agent complet', function (): void {
        $utilisateur = User::factory()->create();

        $this->withServerVariables([
            'REMOTE_ADDR' => '41.85.12.204',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14; SM-A145F) AppleWebKit/537.36 Chrome/129.0 Mobile Safari/537.36',
        ]);
        app('request')->server->set('REMOTE_ADDR', '41.85.12.204');
        app('request')->headers->set('User-Agent', 'Mozilla/5.0 (Linux; Android 14; SM-A145F) AppleWebKit/537.36 Chrome/129.0 Mobile Safari/537.36');

        Auth::guard('web')->login($utilisateur);

        $ligne = HistoriqueConnexion::query()->sole();

        expect($ligne->user_id)->toBe($utilisateur->id)
            ->and($ligne->resultat)->toBe('reussie')
            ->and($ligne->ip_tronquee)->toBe('41.85.12.0')
            ->and($ligne->ip_hash)->not->toContain('41.85')
            ->and($ligne->navigateur)->toBe('Chrome')
            ->and($ligne->systeme)->toBe('Android')
            ->and($ligne->appareil)->toBe('mobile');

        // Aucune colonne ne garde la chaîne complète.
        expect(json_encode($ligne->toArray()))->not->toContain('SM-A145F');
    });

    it('rattache un échec au compte visé, et à personne si l\'adresse est inconnue', function (): void {
        $utilisateur = User::factory()->create(['email' => 'afi@exemple.bj']);
        $journal = app(SecurityLogService::class);

        $journal->loginEchoue('AFI@exemple.bj ', 1);
        $journal->loginVerrouille('afi@exemple.bj', 900);
        $journal->loginEchoue('personne@exemple.bj', 1);

        expect(HistoriqueConnexion::query()->where('user_id', $utilisateur->id)->pluck('resultat')->sort()->values()->all())
            ->toBe(['bloquee', 'echouee'])
            ->and(HistoriqueConnexion::query()->count())->toBe(2);
    });

    it('tronque les adresses IPv4 et IPv6', function (): void {
        expect(JournalDesConnexions::tronquer('197.234.219.45'))->toBe('197.234.219.0')
            ->and(JournalDesConnexions::tronquer('2001:db8:85a3:8d3:1319:8a2e:370:7348'))->toBe('2001:db8:85a3::')
            ->and(JournalDesConnexions::tronquer('pas-une-ip'))->toBeNull();
    });

    it('efface ce qui dépasse 12 mois', function (): void {
        $utilisateur = User::factory()->create();
        $vieille = HistoriqueConnexion::query()->create(['user_id' => $utilisateur->id, 'espace' => 'citoyen', 'resultat' => 'reussie', 'methode' => 'mot_de_passe']);
        $vieille->forceFill(['created_at' => now()->subMonths(13)])->save();
        HistoriqueConnexion::query()->create(['user_id' => $utilisateur->id, 'espace' => 'citoyen', 'resultat' => 'reussie', 'methode' => 'mot_de_passe']);

        expect(app(JournalDesConnexions::class)->purger())->toBe(1)
            ->and(HistoriqueConnexion::query()->count())->toBe(1);
    });
});

describe('historique des lectures du Hub', function (): void {
    it('n\'enregistre rien sans consentement', function (): void {
        $utilisateur = User::factory()->create();

        expect(app(HistoriqueDuHub::class)->noter($utilisateur, 'actualite', 1, 'Une brève', 'lu'))->toBeNull()
            ->and(ConsultationHub::query()->count())->toBe(0);
    });

    it('enregistre avec consentement, une fois par jour et par contenu', function (): void {
        $utilisateur = User::factory()->create();
        $historique = app(HistoriqueDuHub::class);
        $historique->definir($utilisateur, true, '127.0.0.1', 'test');

        $historique->noter($utilisateur, 'actualite', 7, 'Une brève', 'lu');
        $historique->noter($utilisateur, 'actualite', 7, 'Une brève', 'lu');
        $historique->noter($utilisateur, 'actualite', 8, 'Une autre', 'lu');

        expect(ConsultationHub::query()->where('user_id', $utilisateur->id)->count())->toBe(2);
    });

    it('efface l\'historique au retrait du consentement', function (): void {
        $utilisateur = User::factory()->create();
        $historique = app(HistoriqueDuHub::class);
        $historique->definir($utilisateur, true, null, null);
        $historique->noter($utilisateur, 'actualite', 7, 'Une brève', 'lu');

        $historique->definir($utilisateur, false, null, null);

        expect(ConsultationHub::query()->count())->toBe(0)
            ->and($historique->noter($utilisateur, 'actualite', 9, 'Encore', 'lu'))->toBeNull();
    });

    it('efface ce qui dépasse 90 jours', function (): void {
        $utilisateur = User::factory()->create();
        $ligne = ConsultationHub::query()->create(['user_id' => $utilisateur->id, 'rubrique' => 'actualite', 'cible_id' => 1, 'titre' => 'x', 'action' => 'lu']);
        $ligne->forceFill(['created_at' => now()->subDays(91)])->save();

        expect(app(HistoriqueDuHub::class)->purger())->toBe(1);
    });
});

describe('droits de la personne', function (): void {
    it('retrouve les deux historiques dans son export', function (): void {
        $utilisateur = User::factory()->create();
        HistoriqueConnexion::query()->create(['user_id' => $utilisateur->id, 'espace' => 'citoyen', 'resultat' => 'reussie', 'methode' => 'mot_de_passe', 'ip_tronquee' => '41.85.12.0']);
        ConsultationHub::query()->create(['user_id' => $utilisateur->id, 'rubrique' => 'actualite', 'cible_id' => 1, 'titre' => 'Une brève', 'action' => 'lu']);

        $sections = (fn (User $u): array => $this->sections($u))->call(app(ExportDonneesService::class), $utilisateur);

        expect($sections['connexions'][0]['reseau'])->toBe('41.85.12.0')
            ->and($sections['lectures_du_hub'][0]['titre'])->toBe('Une brève');
    });

    it('voit les deux historiques effacés à l\'anonymisation du compte', function (): void {
        $utilisateur = User::factory()->create();
        HistoriqueConnexion::query()->create(['user_id' => $utilisateur->id, 'espace' => 'citoyen', 'resultat' => 'reussie', 'methode' => 'mot_de_passe']);
        ConsultationHub::query()->create(['user_id' => $utilisateur->id, 'rubrique' => 'actualite', 'cible_id' => 1, 'titre' => 'x', 'action' => 'lu']);

        app(SuppressionCompteService::class)->anonymiser($utilisateur);

        expect(HistoriqueConnexion::query()->count())->toBe(0)
            ->and(ConsultationHub::query()->count())->toBe(0);
    });
});
