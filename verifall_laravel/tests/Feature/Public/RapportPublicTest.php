<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\PartageDuRapport;
use App\Core\FactChecking\Services\QrCodeRapport;
use App\Core\Marketing\Seo\Referencement;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use App\Models\AnalysisReport;
use App\Models\ReportEvidence;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| `/rapport/{uuid}` — la page que scanne quelqu'un qui n'a pas de compte
|--------------------------------------------------------------------------
|
| Elle porte quatre gardes, et chacune protège une chose différente. Ces tests
| les éprouvent une par une, parce qu'une seule qui cède suffit : la page est
| ouverte à tout l'internet, et son adresse circule sur des feuilles imprimées
| qu'on ne peut plus rappeler.
|
*/

/**
 * Une vérification avec son rapport, publié ou non.
 */
function verificationAvecRapport(bool $publie = true, ?string $contexte = 'Reçu sur WhatsApp par mon oncle Koffi'): Verification
{
    $auteur = User::factory()->actif()->create([
        'first_name' => 'Amandine',
        'last_name' => 'Hounkpatin',
        // Une adresse par appel : deux rapports dans un même test sinon
        // heurtent la contrainte d'unicité.
        'email' => 'amandine.'.Str::random(8).'@example.bj',
    ]);

    $verification = Verification::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $auteur->id,
        'type' => TypeContenu::Texte->value,
        'status' => StatutVerification::Terminee->value,
        'claim' => 'La taxe sur les transferts double lundi prochain.',
        'context' => $contexte,
        'cost_units' => 1,
    ]);

    $verification->forceFill(['completed_at' => now()])->save();

    AnalysisReport::create([
        'uuid' => (string) Str::uuid(),
        'verification_id' => $verification->id,
        'verdict' => VerdictFactCheck::Faux->value,
        'confidence' => 96,
        'summary' => 'Aucun texte officiel ne prévoit un tel doublement.',
        'published_at' => $publie ? now() : null,
    ]);

    ReportEvidence::create([
        'verification_id' => $verification->id,
        'source_url' => 'https://gouv.bj/communique-taxes',
        'source_name' => 'Gouvernement du Bénin',
        'snippet' => 'Aucune modification du barème n’est à l’ordre du jour.',
        'stance' => 'refutes',
        'reliability' => 95,
    ]);

    return $verification->fresh();
}

describe('accès', function (): void {
    it('sert le rapport SANS compte, sans redirection vers la connexion', function (): void {
        $verification = verificationAvecRapport();

        $this->get(route('public.report', ['verification' => $verification->uuid]))
            ->assertOk()
            ->assertSee('La taxe sur les transferts double lundi prochain.')
            ->assertSee($verification->reference());
    });

    /*
     | 404 et non 403 : un 403 confirmerait qu'un rapport existe à cette
     | adresse. Sur une page ouverte à tout l'internet, la différence entre
     | « rien ici » et « quelque chose ici, mais pas pour vous » est une fuite.
     */
    it('rend 404 tant que le rapport n’est pas PUBLIÉ', function (): void {
        $verification = verificationAvecRapport(publie: false);

        $this->get(route('public.report', ['verification' => $verification->uuid]))
            ->assertNotFound();
    });

    it('rend 404 sur une vérification sans aucun rapport', function (): void {
        $auteur = User::factory()->actif()->create();

        $verification = Verification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $auteur->id,
            'type' => TypeContenu::Texte->value,
            'status' => StatutVerification::EnCours->value,
            'claim' => 'Analyse encore en cours.',
            'cost_units' => 1,
        ]);

        $this->get(route('public.report', ['verification' => $verification->uuid]))
            ->assertNotFound();
    });

    it('rend 404 sur un identifiant inventé, sans rien révéler', function (): void {
        $this->get(route('public.report', ['verification' => (string) Str::uuid()]))
            ->assertNotFound();
    });
});

describe('confidentialité', function (): void {
    it('ne nomme JAMAIS la personne qui a demandé l’analyse', function (): void {
        $verification = verificationAvecRapport();
        $auteur = $verification->user;

        $reponse = $this->get(route('public.report', ['verification' => $verification->uuid]));

        $reponse->assertDontSee('Amandine');
        $reponse->assertDontSee('Hounkpatin');
        $reponse->assertDontSee($auteur->email);

        /*
         | Pas d'assertion sur l'identifiant NUMÉRIQUE : « 1 » se trouve dans
         | n'importe quelle page HTML, et la chercher ne prouverait rien. Ce
         | qui fuirait vraiment, c'est un nom ou une adresse.
         */
    });

    /*
     | « Où avez-vous vu cette information ? » est un champ LIBRE. Les gens y
     | écrivent le prénom d'un proche, un numéro, le nom d'un groupe privé.
     | Rien de cela n'appartient au démenti.
     */
    it('ne publie pas le contexte, où les gens nomment des tiers', function (): void {
        $verification = verificationAvecRapport();

        $this->get(route('public.report', ['verification' => $verification->uuid]))
            ->assertDontSee('Koffi')
            ->assertDontSee('Reçu sur WhatsApp');
    });

    it('montre ce même contexte à l’auteur, sur SA page', function (): void {
        $verification = verificationAvecRapport();

        $this->actingAs($verification->user)
            ->get(route('citizen.report', ['verification' => $verification->uuid]))
            ->assertOk()
            ->assertSee('Koffi', escape: false);
    });
});

describe('référencement', function (): void {
    it('porte l’en-tête X-Robots-Tag ET la balise noindex', function (): void {
        $verification = verificationAvecRapport();

        $reponse = $this->get(route('public.report', ['verification' => $verification->uuid]));

        expect($reponse->headers->get('X-Robots-Tag'))->toBe('noindex, follow');
        $reponse->assertSee('name="robots"', escape: false);
        $reponse->assertSee('noindex', escape: false);
    });

    /*
     | Le « sandwich de vérité » appliqué à l'aperçu de partage : le verdict
     | AVANT l'affirmation. La plupart des gens ne liront que ce titre en
     | voyant passer la carte dans une conversation — commencer par la rumeur
     | la propagerait auprès d'eux tous.
     */
    it('titre en énonçant le verdict AVANT la rumeur', function (): void {
        $verification = verificationAvecRapport();
        $titre = app(Referencement::class)
            ->pourRapportPublic($verification)
            ->titre;

        expect($titre)->toStartWith(VerdictFactCheck::Faux->libelleRapport());
        expect(mb_strpos($titre, 'Information fausse'))
            ->toBeLessThan(mb_strpos($titre, 'La taxe'));
    });

    it('n’entre pas dans le plan du site', function (): void {
        $verification = verificationAvecRapport();

        $this->get(route('public.sitemap'))
            ->assertDontSee($verification->uuid);
    });
});

describe('QR de contrôle', function (): void {
    it('pointe vers la page PUBLIQUE, jamais vers l’espace citoyen', function (): void {
        $verification = verificationAvecRapport();

        expect(app(QrCodeRapport::class)->adresse($verification))
            ->toBe(route('public.report', ['verification' => $verification->uuid]))
            ->not->toContain('mon-espace');
    });

    it('rend un SVG, lisible une fois imprimé', function (): void {
        $svg = app(QrCodeRapport::class)->svg(verificationAvecRapport());

        expect($svg)->toContain('<svg');
    });

    /*
     | Un code qui mène à une page introuvable vaut moins que pas de code : le
     | lecteur en conclut que la plateforme ne répond pas.
     */
    it('ne s’imprime pas tant que le rapport n’est pas publié', function (): void {
        expect(app(QrCodeRapport::class)->siPublie(verificationAvecRapport(publie: false)))->toBeNull();
        expect(app(QrCodeRapport::class)->siPublie(verificationAvecRapport()))->toContain('<svg');
    });
});

describe('partage', function (): void {
    it('énonce le VERDICT avant l’affirmation, et finit par le lien', function (): void {
        $message = app(PartageDuRapport::class)->message(
            VerdictFactCheck::Faux,
            'La taxe sur les transferts double lundi.',
            'https://verifall.com/rapport/abc',
        );

        expect(mb_strpos($message, 'INFORMATION FAUSSE'))
            ->toBeLessThan(mb_strpos($message, 'La taxe'));
        expect($message)->toEndWith('https://verifall.com/rapport/abc');
    });

    it('encode chaque canal, pour qu’une affirmation à esperluette ne tronque rien', function (): void {
        $adresses = app(PartageDuRapport::class)->adresses(
            VerdictFactCheck::Faux,
            'Taxe & douane : 50 % dès #lundi',
            'https://verifall.com/rapport/abc',
        );

        expect($adresses)->toHaveKeys(['whatsapp', 'x', 'facebook', 'courriel']);

        foreach ($adresses as $canal => $url) {
            expect($url)->not->toContain(' ', "Le canal {$canal} laisse passer une espace brute.");
            expect($url)->not->toContain('#lundi', "Le canal {$canal} laisse passer un # non encodé.");
        }
    });

    it('partage le lien PUBLIC depuis la page citoyenne, pas l’adresse du compte', function (): void {
        $verification = verificationAvecRapport();

        $public = route('public.report', ['verification' => $verification->uuid]);
        $prive = route('citizen.report', ['verification' => $verification->uuid]);

        $html = $this->actingAs($verification->user)->get($prive)->getContent();

        /*
         | Le lien voyage ENCODÉ dans les `href` de partage — c'est sa forme
         | réelle, et la chercher en clair ne prouverait rien.
         */
        expect($html)->toContain(rawurlencode($public));
        expect($html)->not->toContain(rawurlencode($prive));
    });
});
