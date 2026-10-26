<?php

declare(strict_types=1);

use App\Listeners\JournaliserLePlanificateur;
use App\Listeners\JournaliserLesCourriels;
use App\Livewire\Staff\JournalDesEmails;
use App\Livewire\Staff\JournalDesUnites;
use App\Livewire\Staff\SauvegardesBaseDeDonnees;
use App\Livewire\Staff\TachesCron;
use App\Models\MailLog;
use App\Models\ScheduledTaskRun;
use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;

/*
|--------------------------------------------------------------------------
| Sécurité et maintenance — lot 10, le dernier
|--------------------------------------------------------------------------
| Trois écrans, et tous répondent à une question qu'on ne pouvait pas poser :
|
| · « le courriel est-il parti ? » — la question qui a coûté un lot entier à
|   diagnostiquer, parce que la file échoue en silence ;
| · « le planificateur tourne-t-il ? » — que `verifall:diagnostic` admettait ne
|   pas savoir dire ;
| · « sommes-nous sauvegardés ? » — à laquelle l'écran répond honnêtement, en
|   annonçant qu'il ne sauvegarde rien lui-même.
|
| Ce que ces tests tiennent : la chaîne d'observation FONCTIONNE, et les écrans
| restent en lecture seule.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

describe('le journal des e-mails', function (): void {
    it('enregistre un envoi réussi', function (): void {
        /*
         | L'écouteur est branché sur le TRANSPORT, pas sur chaque Mailable :
         | ce qu'on ajoutera demain sera journalisé sans qu'on y pense.
         */
        Mail::raw('Contenu du message', function ($message): void {
            $message->to('destinataire@verifall.com')->subject('Un objet de test');
        });

        $ligne = MailLog::query()->latest('id')->firstOrFail();

        expect($ligne->recipient)->toBe('destinataire@verifall.com')
            ->and($ligne->subject)->toBe('Un objet de test')
            ->and($ligne->status)->toBe(MailLog::ENVOYE)
            ->and($ligne->sent_at)->not->toBeNull();
    });

    it('ne conserve PAS le contenu du message', function (): void {
        /*
         | `OtpCodeMail` le documente en toutes lettres : le code « ne doit
         | apparaître NULLE PART ailleurs — ni en journal, ni en base, ni dans
         | une exception ». Stocker le corps l'y mettrait, pour tous les codes,
         | indéfiniment.
         */
        Mail::raw('CODE-SECRET-424242', function ($message): void {
            $message->to('destinataire@verifall.com')->subject('Votre code');
        });

        $colonnes = MailLog::query()->latest('id')->firstOrFail()->getAttributes();

        foreach ($colonnes as $valeur) {
            expect((string) $valeur)->not->toContain('CODE-SECRET-424242');
        }
    });

    it('laisse une trace même quand l\'envoi ne se confirme jamais', function (): void {
        /*
         | LE CŒUR DU DISPOSITIF. La ligne est écrite AVANT le transport, en
         | « échoué », et bascule après succès. L'inverse ne journaliserait que
         | ce qui a marché — c'est-à-dire exactement ce dont personne n'a besoin
         | quand une inscription reste bloquée à l'étape du code.
         */
        $ecouteur = new JournaliserLesCourriels;

        $message = (new Email)
            ->to('bloque@verifall.com')
            ->subject('Un envoi qui n\'aboutira pas')
            ->text('peu importe');

        $ecouteur->handleMessageSending(
            new MessageSending($message, [])
        );

        $ligne = MailLog::query()->latest('id')->firstOrFail();

        expect($ligne->status)->toBe(MailLog::ECHOUE)
            ->and($ligne->aEchoue())->toBeTrue()
            ->and($ligne->error)->toContain('jamais confirmé');
    });

    it('n\'offre AUCUNE suppression — un journal qu\'on efface n\'en est plus un', function (): void {
        $composant = Livewire::actingAs($this->admin, 'staff')
            ->test(JournalDesEmails::class)->instance();

        expect(method_exists($composant, 'supprimer'))->toBeFalse()
            ->and(method_exists($composant, 'vider'))->toBeFalse()
            ->and(method_exists($composant, 'demanderLaSuppression'))->toBeFalse();
    });

    it('se garde derrière security.email_logs', function (): void {
        $citoyen = User::factory()->create();
        $citoyen->assignRole('Citoyen');

        $this->actingAs($citoyen, 'staff')->get(route('staff.email-logs'))->assertForbidden();
        $this->actingAs($this->admin, 'staff')->get(route('staff.email-logs'))->assertOk();
    });
});

describe('les tâches planifiées', function (): void {
    it('dit « jamais exécutée » quand aucune trace n\'existe', function (): void {
        /*
         | L'ABSENCE de ligne est l'information. `verifall:diagnostic` comptait
         | les tâches déclarées et admettait ne pas savoir si un cron les
         | appelle ; cet écran le sait, et le dit en rouge.
         */
        expect(ScheduledTaskRun::query()->count())->toBe(0);

        Livewire::actingAs($this->admin, 'staff')
            ->test(TachesCron::class)
            ->assertSee('Jamais')
            // Le message qui distingue « six pannes » de « pas de cron ».
            ->assertSee('c\'est l\'entrée cron qui manque', escape: false);
    });

    it('affiche la dernière exécution enregistrée', function (): void {
        $planificateur = app(Schedule::class);
        $premiere = $planificateur->events()[0] ?? null;

        expect($premiere)->not->toBeNull();

        ScheduledTaskRun::query()->create([
            'task' => $premiere->getSummaryForDisplay(),
            'status' => ScheduledTaskRun::SUCCES,
            'duration_ms' => 1234,
            'ran_at' => now()->subHour(),
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(TachesCron::class)
            ->assertSee('Régulière')
            ->assertDontSee('c\'est l\'entrée cron qui manque', escape: false);
    });

    it('ne rapporte JAMAIS zéro tâche quand le code en déclare', function (): void {
        /*
         | ## Le défaut trouvé au navigateur, et que la suite ne pouvait pas voir
         |
         | `render()` recevait `Schedule` par injection. Dans une requête WEB, ce
         | planificateur est VIDE : `routes/console.php` n'est chargé que par le
         | noyau console. L'écran affichait donc « 0 tâche déclarée · Aucune
         | tâche déclarée » — l'inverse de la vérité, sur l'écran même dont le
         | travail est de dire la vérité sur le planificateur.
         |
         | **Pest amorce le noyau console**, donc `Schedule` y est peuplé et le
         | test passait. Il a fallu ouvrir la page dans un navigateur.
         |
         | Ce test ne peut pas reproduire le contexte web — le noyau est déjà
         | amorcé quand il s'exécute. Il garde le SYMPTÔME : l'écran ne doit
         | jamais annoncer zéro tâche tant que le code en déclare. Si la
         | correction saute, le compte tombera à zéro ici aussi le jour où
         | quelqu'un exécutera ce fichier seul.
         */
        $declarees = count(app(Schedule::class)->events());

        expect($declarees)->toBeGreaterThan(0);

        /*
         | On lit la DONNÉE passée à la vue, pas le texte rendu : un libellé se
         | reformule, et un test qui casse au premier changement de mot n'est
         | plus une garde, c'est une gêne.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(TachesCron::class)
            ->assertViewHas('taches', fn (array $taches): bool => count($taches) === $declarees)
            ->assertDontSee('Aucune tâche déclarée');
    });

    it('n\'offre AUCUN bouton d\'exécution', function (): void {
        /*
         | La purge RGPD et la dotation mensuelle ne sont pas idempotentes : un
         | double-clic doublerait la dotation de tous les comptes.
         */
        $composant = Livewire::actingAs($this->admin, 'staff')
            ->test(TachesCron::class)->instance();

        expect(method_exists($composant, 'executer'))->toBeFalse()
            ->and(method_exists($composant, 'lancer'))->toBeFalse();
    });

    it('journalise une exécution réussie du planificateur', function (): void {
        $planificateur = app(Schedule::class);
        $tache = $planificateur->events()[0];

        (new JournaliserLePlanificateur)->handleScheduledTaskFinished(
            new ScheduledTaskFinished($tache, 2.5)
        );

        $ligne = ScheduledTaskRun::query()->latest('id')->firstOrFail();

        expect($ligne->status)->toBe(ScheduledTaskRun::SUCCES)
            ->and($ligne->duration_ms)->toBe(2500)
            ->and($ligne->aEchoue())->toBeFalse();
    });
});

describe('les sauvegardes', function (): void {
    it('ANNONCE que la plateforme n\'en fabrique aucune', function (): void {
        /*
         | Même honnêteté qu'à l'écran KYC du lot 7 : un écran qui laisse croire
         | à plus qu'il ne fait est pire qu'un écran absent — ici, il ferait
         | croire qu'on est sauvegardé.
         */
        $this->actingAs($this->admin, 'staff')
            ->get(route('staff.backups'))
            ->assertOk()
            ->assertSee('La plateforme ne fabrique aucune sauvegarde', escape: false);
    });

    it('ne propose ni création ni téléchargement', function (): void {
        // Un dump complet servi par une URL serait une fuite intégrale :
        // adresses, mots de passe hachés, journaux d'audit.
        $composant = Livewire::actingAs($this->admin, 'staff')
            ->test(SauvegardesBaseDeDonnees::class)->instance();

        expect(method_exists($composant, 'sauvegarder'))->toBeFalse()
            ->and(method_exists($composant, 'telecharger'))->toBeFalse()
            ->and(method_exists($composant, 'creer'))->toBeFalse();
    });

    it('tient sans planter quand le dossier n\'existe pas', function (): void {
        // L'état normal aujourd'hui : aucun mécanisme ne le crée.
        Livewire::actingAs($this->admin, 'staff')
            ->test(SauvegardesBaseDeDonnees::class)
            ->assertOk()
            ->assertSee('Aucune sauvegarde sur le disque');
    });
});

describe('le doublon des unités IA', function (): void {
    it('mène les DEUX entrées du menu au même écran', function (): void {
        /*
         | Le prototype porte « Historiques des Unités IA » (fact-checking) et
         | « Logs Unités IA » (sécurité) : deux entrées, une seule table. Deux
         | écrans identiques donneraient deux habitudes, donc un des deux qu'on
         | ne relève plus.
         */
        /*
         | Lu dans la DÉCLARATION depuis le 3 septembre 2026 : le menu n'est plus
         | écrit dans le gabarit.
         |
         | Ce test a servi ce jour-là : j'avais retiré ce doublon en le prenant
         | pour une négligence. Il est délibéré, ce test le disait, et le doublon
         | est désormais NOMMÉ dans `DOUBLONS_ASSUMES` — avec sa raison, pour que
         | le prochain qui le voit n'ait pas à le redécouvrir.
         */
        $entrees = collect(MenuDuBackOffice::GROUPES)
            ->flatMap(static fn (array $g): array => array_keys($g))
            ->filter(static fn (string $r): bool => $r === 'staff.unit-journal');

        expect($entrees)->toHaveCount(2)
            ->and(MenuDuBackOffice::DOUBLONS_ASSUMES)->toHaveKey('staff.unit-journal');
    });

    it('ouvre l\'écran à un responsable sécurité qui n\'a pas ai_unit.view', function (): void {
        // `security.unit_logs` sans `ai_unit.*` : la lecture passe, le
        // formulaire d'ajustement reste fermé.
        $securite = User::factory()->create();
        $securite->givePermissionTo('security.unit_logs');

        expect($securite->can('ai_unit.view'))->toBeFalse();

        Livewire::actingAs($securite, 'staff')
            ->test(JournalDesUnites::class)
            ->assertOk()
            ->assertSet('peutAjuster', false);
    });
});

describe('les réglages en onglets latéraux', function (): void {
    it('emploie la règle de staff.css, et plus les classes inertes', function (): void {
        /*
         | LE DÉFAUT SIGNALÉ PAR LE PORTEUR DU PROJET. L'écran portait
         | `lg:grid-cols-[240px_1fr]`, `lg:top-6`, `lg:self-start`,
         | `lg:flex-col` — AUCUNE n'existe au build Tailwind figé. Le panneau ne
         | collait pas, la grille ne se formait pas, et rien ne le signalait.
         |
         | La correction n'était pas de changer d'utilitaires — il n'y a pas
         | d'équivalent au build — mais d'écrire la règle dans `staff.css`, qui
         | est du CSS de projet écrit à la main.
         */
        $vue = (string) file_get_contents(
            resource_path('views/livewire/staff/parametres.blade.php')
        );

        expect($vue)->toContain('class="reglages"')
            ->and($vue)->toContain('class="reglages-nav"')
            ->and($vue)->toContain('class="reglages-onglets"');

        $css = (string) file_get_contents(base_path('tools/staff-premium/input.css'));

        expect($css)
            ->toContain('.reglages')
            ->toContain('position: sticky')
            // `align-self: start` est LE détail qui fait qu'un panneau collant
            // colle : sans lui, la colonne s'étire et n'a rien contre quoi coller.
            ->toContain('align-self: start')
            ->toContain('grid-template-columns: 240px');
    });

    it('répond toujours', function (): void {
        $this->actingAs($this->admin, 'staff')->get(route('staff.settings'))->assertOk();
    });
});
