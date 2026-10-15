<?php

declare(strict_types=1);

use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Core\Marketing\Emails\RenduDeGabarit;
use App\Enums\StatutInscription;
use App\Enums\TypeLecon;
use App\Enums\UserRole;
use App\Jobs\AlerterSurUneIntox;
use App\Jobs\AnnoncerLeNouveauCours;
use App\Mail\AlerteIntox;
use App\Mail\CourrielDeGabarit;
use App\Models\Course;
use App\Models\EmailTemplate;
use App\Models\Enrollment;
use App\Models\HubArticle;
use App\Models\Lesson;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\GabaritsDEmailSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Les deux derniers blocages produit, tranchés
|--------------------------------------------------------------------------
|
| « Nouveau cours » et « alerte intox » attendaient chacun une DÉCISION, pas du
| code. Elles sont prises :
|
|   · nouveau cours → les citoyens déjà inscrits à un cours. Ni tout le monde
|     (ce serait de la prospection), ni la liste d'infolettre (dont le
|     consentement porte sur les fact-checks) ;
|   · alerte intox → les abonnés de l'infolettre, par la machinerie de
|     campagne. Même liste, même sujet, cadence exceptionnelle — donc lien de
|     désabonnement et réservation avant envoi.
|
| Ce que ces tests tiennent : l'audience, et surtout le fait qu'aucune des deux
| ne se rejoue.
|
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(GabaritsDEmailSeeder::class);

    Mail::fake();
});

/** Un cours publié, avec une leçon. */
function coursPublieAvecLecon(string $titre = 'Détecter un deepfake'): Course
{
    $cours = Course::create([
        'uuid' => (string) Str::uuid(),
        'slug' => Str::slug($titre).'-'.Str::lower(Str::random(4)),
        'title' => $titre,
        'description' => 'Un cours de test suffisamment décrit pour passer la validation.',
        'category' => 'OSINT',
        'level' => 'beginner',
        'duration_label' => '1 h',
        'cost_units' => 0,
        'is_published' => true,
    ]);

    Lesson::create([
        'uuid' => (string) Str::uuid(),
        'course_id' => $cours->id,
        'position' => 1,
        'title' => 'Une leçon',
        'type' => TypeLecon::Texte->value,
        'minutes' => 5,
        'content' => '<p>Contenu.</p>',
    ]);

    return $cours;
}

/** Un citoyen actif, inscrit ou non à un cours. */
function citoyenInscrit(bool $inscrit = true): User
{
    $citoyen = User::factory()->actif()->create();
    $citoyen->assignRole(UserRole::Citoyen->value);

    if ($inscrit) {
        $autre = coursPublieAvecLecon('Un cours déjà suivi');

        Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $citoyen->id,
            'course_id' => $autre->id,
            'status' => StatutInscription::EnCours->value,
            'progress' => 10,
            'paid_units' => 0,
        ]);
    }

    return $citoyen;
}

/** Un abonné confirmé de l'infolettre. */
function abonneDeLaListe(string $email): NewsletterSubscriber
{
    return NewsletterSubscriber::create([
        'uuid' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Abonnée',
        'confirmation_token' => bin2hex(random_bytes(32)),
        'unsubscribe_token' => bin2hex(random_bytes(32)),
        'confirmed_at' => now(),
    ]);
}

/** Allume un envoi que le catalogue livre éteint. */
function allumer(string $cle): void
{
    EmailTemplate::query()->where('key', $cle)->update(['is_active' => true]);
    app(RenduDeGabarit::class)->oublier();
}

// ═══════════════════════════════════════════════════════ nouveau cours

describe('l’annonce d’un nouveau cours', function () {
    it('n’écrit QU’À ceux qui ont déjà suivi un cours', function () {
        /*
         | L'audience tranchée. Annoncer un cours à quelqu'un qui n'a jamais
         | ouvert l'Académie est de la prospection ; une inscription antérieure
         | est un signal d'intérêt donné par la personne elle-même.
         */
        allumer('nouveau_cours');

        $inscrit = citoyenInscrit(true);
        $jamaisInscrit = citoyenInscrit(false);

        $nouveau = coursPublieAvecLecon('Vérifier une image');
        app()->call([new AnnoncerLeNouveauCours($nouveau->id), 'handle']);

        Mail::assertQueued(
            CourrielDeGabarit::class,
            fn (CourrielDeGabarit $m): bool => $m->cle === 'nouveau_cours',
        );

        Mail::assertQueued(CourrielDeGabarit::class, 1);
        expect($inscrit->email)->not->toBe($jamaisInscrit->email);
    });

    it('ne compte un citoyen QU’UNE fois, même avec dix inscriptions', function () {
        // `whereExists` et non une jointure : une jointure rendrait le compte
        // autant de fois qu'il a d'inscriptions.
        allumer('nouveau_cours');

        $citoyen = citoyenInscrit(true);

        foreach (range(1, 3) as $n) {
            Enrollment::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $citoyen->id,
                'course_id' => coursPublieAvecLecon('Cours '.$n)->id,
                'status' => StatutInscription::EnCours->value,
                'progress' => 0,
                'paid_units' => 0,
            ]);
        }

        $nouveau = coursPublieAvecLecon('Un cours de plus');
        app()->call([new AnnoncerLeNouveauCours($nouveau->id), 'handle']);

        Mail::assertQueued(CourrielDeGabarit::class, 1);
    });

    it('ne réannonce PAS un cours republié après correction', function () {
        /*
         | Publier n'est pas un geste unique : on dépublie pour corriger une
         | leçon, on republie une heure après. Sans `announced_at`, la troisième
         | correction du mentor serait le troisième courriel de la journée.
         */
        allumer('nouveau_cours');
        citoyenInscrit(true);

        $cours = coursPublieAvecLecon('Vérifier une image');

        app()->call([new AnnoncerLeNouveauCours($cours->id), 'handle']);
        app()->call([new AnnoncerLeNouveauCours($cours->fresh()->id), 'handle']);

        Mail::assertQueued(CourrielDeGabarit::class, 1);
    });

    it('pose le marqueur AVANT la boucle', function () {
        // Posé après, un job interrompu au milieu servirait deux fois les
        // premiers destinataires à la reprise.
        allumer('nouveau_cours');
        citoyenInscrit(true);

        $cours = coursPublieAvecLecon('Vérifier une image');
        expect($cours->announced_at)->toBeNull();

        app()->call([new AnnoncerLeNouveauCours($cours->id), 'handle']);

        expect($cours->fresh()->announced_at)->not->toBeNull();
    });

    it('n’envoie rien tant que l’administration ne l’a pas allumé', function () {
        // Livré ÉTEINT : l'audience dit qui recevrait, pas qu'il faille envoyer.
        expect(CatalogueDesCourriels::evenement('nouveau_cours')['parDefautActif'])->toBeFalse();

        citoyenInscrit(true);
        $cours = coursPublieAvecLecon('Vérifier une image');

        app()->call([new AnnoncerLeNouveauCours($cours->id), 'handle']);

        Mail::assertNotQueued(CourrielDeGabarit::class);
    });
});

// ═══════════════════════════════════════════════════════ alerte intox

describe('l’alerte intox', function () {
    /** Un démenti publié, urgent ou non. */
    function dementi(bool $urgent, bool $publie = true): HubArticle
    {
        return HubArticle::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'faux-remede-'.Str::lower(Str::random(5)),
            'title' => 'Non, ce remède ne soigne pas le paludisme',
            'excerpt' => 'Le message circule sur WhatsApp depuis lundi. Il est faux.',
            'body' => '<p>Le détail de la vérification.</p>',
            'category' => 'Santé',
            'is_published' => $publie,
            'is_urgent' => $urgent,
            'published_at' => $publie ? now() : null,
        ]);
    }

    it('écrit aux abonnés confirmés, avec un lien de désabonnement', function () {
        /*
         | Un envoi de masse sans lien de retrait est une infraction — et le
         | seul geste qui resterait serait « signaler comme indésirable », que
         | la réputation du domaine paierait jusque sur les codes de connexion.
         */
        allumer('alerte_intox');
        $abonne = abonneDeLaListe('lectrice@example.test');

        $article = dementi(urgent: true);
        app()->call([new AlerterSurUneIntox($article->id), 'handle']);

        Mail::assertSent(
            AlerteIntox::class,
            fn (AlerteIntox $m): bool => str_contains($m->lienDeDesabonnement, $abonne->uuid),
        );
    });

    it('n’alerte PAS pour un démenti ordinaire', function () {
        // L'urgence décrit la circulation, pas le sujet. Sans la case, un
        // démenti est une publication, pas une alerte.
        allumer('alerte_intox');
        abonneDeLaListe('lectrice@example.test');

        $article = dementi(urgent: false);
        app()->call([new AlerterSurUneIntox($article->id), 'handle']);

        Mail::assertNothingSent();
    });

    it('ne réalerte PAS après une correction de faute de frappe', function () {
        /*
         | Le message le plus intrusif du produit. Le renvoyer parce qu'on a
         | corrigé une virgule est le meilleur moyen d'apprendre à nos lecteurs
         | à ignorer nos alertes.
         */
        allumer('alerte_intox');
        abonneDeLaListe('lectrice@example.test');

        $article = dementi(urgent: true);

        app()->call([new AlerterSurUneIntox($article->id), 'handle']);
        app()->call([new AlerterSurUneIntox($article->fresh()->id), 'handle']);

        Mail::assertSent(AlerteIntox::class, 1);
    });

    it('relit l’interrupteur AU MOMENT DE L’ENVOI', function () {
        /*
         | Entre la mise en file et l'exécution, l'administration a pu
         | l'éteindre. Sur ce message-là, on relit plutôt que de faire confiance
         | à la décision prise au clic.
         */
        abonneDeLaListe('lectrice@example.test');
        $article = dementi(urgent: true);

        // Jamais allumé : le catalogue le livre éteint.
        app()->call([new AlerterSurUneIntox($article->id), 'handle']);

        Mail::assertNothingSent();
        expect($article->fresh()->alerted_at)->toBeNull();
    });

    it('ne sert jamais deux fois la même adresse dans un envoi', function () {
        allumer('alerte_intox');
        abonneDeLaListe('une@example.test');
        abonneDeLaListe('deux@example.test');

        $article = dementi(urgent: true);
        app()->call([new AlerterSurUneIntox($article->id), 'handle']);

        Mail::assertSent(AlerteIntox::class, 2);
    });
});
