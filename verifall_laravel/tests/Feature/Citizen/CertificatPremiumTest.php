<?php

declare(strict_types=1);

use App\Core\Application\Reglages\Reglages;
use App\Core\Lms\Services\GabaritCertificat;
use App\Enums\UserRole;
use App\Livewire\Citizen\Certificat as CertificatCitoyen;
use App\Livewire\Public\VerifierCertificat;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Lot 4 — le certificat premium, et son gabarit administrable
|--------------------------------------------------------------------------
| Le document était une carte simple : bordure verte, nom, cours, date, code.
| Aucun décor, pas de QR, pas de signature. Et le groupe de réglages
| « Certificats » existait depuis des mois SANS AUCUN LECTEUR — l'écart É-02
| relevé au lot 0 : on réglait, l'écran confirmait, rien ne changeait.
|
| Deux contraintes dures gouvernent ce lot, et ce sont elles que la plupart de
| ces tests éprouvent :
|
|  1. le build Tailwind est FIGÉ — le décor doit venir d'une feuille servie à
|     part, sans quoi il est silencieusement inerte ;
|  2. le document est fait pour être IMPRIMÉ — sans `print-color-adjust`, les
|     navigateurs retirent fonds et dégradés, et le certificat sort blanc.
|
| Ce que ces tests NE prouvent pas : qu'une feuille sorte correctement d'une
| imprimante. Voir la note de fin de lot dans `docs/JOURNAL_DES_LOTS.md`.
*/

/** Un certificat valide et son titulaire. */
function certificatDe(?User $titulaire = null): array
{
    $titulaire ??= User::factory()->create(['otp_verified_at' => now()]);

    $cours = Course::query()->create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'cours-'.Str::lower(Str::random(6)),
        'title' => 'Reconnaître une image sortie de son contexte',
        'description' => 'Description.',
        'category' => 'investigation',
        'level' => 'beginner',
        'duration_label' => '30 min',
        'cost_units' => 0,
        'outcomes' => ['Un acquis'],
        'delivers_certificate' => true,
        'is_published' => true,
        'published_at' => now()->subMonth(),
    ]);

    $certificat = Certificate::query()->create([
        'uuid' => (string) Str::uuid(),
        'code' => 'VERIF-A-'.Str::upper(Str::random(4)),
        'user_id' => $titulaire->id,
        'course_id' => $cours->id,
        'holder_name' => 'Aya Sossou',
        'course_title' => $cours->title,
        'issued_at' => now()->subDay(),
    ]);

    return [$titulaire, $certificat];
}

describe('les réglages sont enfin lus', function (): void {
    it('applique la couleur réglée au décor', function (): void {
        /*
         | LE test du lot. Le groupe « Certificats » existait et n'était lu par
         | personne. On règle une teinte qu'aucun défaut ne produirait — un
         | magenta franc — et on la cherche dans le document.
         */
        app(Reglages::class)->set('cert_accent_color', '#B5179E');

        [$titulaire, $certificat] = certificatDe();

        Livewire::actingAs($titulaire)
            ->test(CertificatCitoyen::class, ['certificate' => $certificat->code])
            ->assertSee('#B5179E', escape: false);
    });

    it('imprime le nom et la qualité du signataire', function (): void {
        // Un diplôme non signé ne vaut rien : même sans signature numérisée,
        // le nom et la fonction qui engagent la plateforme sont imprimés.
        app(Reglages::class)->set('cert_signature_name', 'Rita Adjovi');
        app(Reglages::class)->set('cert_signature_role', 'Directrice de la publication');

        [$titulaire, $certificat] = certificatDe();

        Livewire::actingAs($titulaire)
            ->test(CertificatCitoyen::class, ['certificate' => $certificat->code])
            ->assertSee('Rita Adjovi')
            ->assertSee('Directrice de la publication');
    });

    it('refuse une couleur qui n\'est pas un hexadécimal', function (): void {
        /*
         | Cette valeur est interpolée dans un attribut `style`. Le formulaire la
         | valide déjà, mais il n'est pas le seul chemin d'écriture : un
         | `Reglages::set()` depuis une commande ou un seeder ne passe par
         | aucune validation. Le service revalide donc, et retombe sur la teinte
         | du produit.
         */
        app(Reglages::class)->set('cert_accent_color', 'red; background:url(//pirate)');

        $habillage = app(GabaritCertificat::class)->habillage();

        expect($habillage['couleur'])->toBe(GabaritCertificat::COULEUR_PAR_DEFAUT);
    });

    it('ne met pas le certificat à terre si les réglages sont illisibles', function (): void {
        /*
         | La leçon du lot 3, appliquée d'avance. La page PUBLIQUE de
         | vérification est atteinte par un lien imprimé sur un diplôme, souvent
         | par quelqu'un qui ne connaît pas la plateforme : une table `settings`
         | momentanément illisible ne doit pas lui rendre un 500.
         */
        Schema::drop('settings');

        $habillage = app(GabaritCertificat::class)->habillage();

        expect($habillage['couleur'])->toBe(GabaritCertificat::COULEUR_PAR_DEFAUT)
            ->and($habillage['signatureNom'])->not->toBeEmpty();
    });
});

describe('le QR de vérification', function (): void {
    it('mène à la page PUBLIQUE, avec le code pré-rempli', function (): void {
        /*
         | Jamais vers l'espace citoyen : le recruteur qui scanne n'a pas de
         | compte, et ne doit pas en avoir besoin pour contrôler un diplôme.
         */
        [, $certificat] = certificatDe();

        $svg = app(GabaritCertificat::class)->qrCode($certificat);

        expect($svg)->toContain('<svg');
        expect(route('public.verify-certificate', ['code' => $certificat->code]))
            ->toContain($certificat->code);
    });

    it('n\'apparaît pas sur un certificat révoqué', function (): void {
        // Il mènerait à une page qui dit « révoqué » — ce que le document
        // affiche déjà, en plus clair.
        [$titulaire, $certificat] = certificatDe();
        $certificat->forceFill(['revoked_at' => now(), 'revoked_reason' => 'Erreur de saisie'])->save();

        Livewire::actingAs($titulaire)
            ->test(CertificatCitoyen::class, ['certificate' => $certificat->code])
            ->assertDontSee('cert-qr', escape: false);
    });
});

describe('les deux écrans montrent le même document', function (): void {
    it('sert le document sur la page publique de vérification', function (): void {
        /*
         | Le relevé de faits répond à « ce code existe-t-il ? ». Le document
         | répond à « est-ce bien celui que je tiens ? ». Sans lui, le recruteur
         | n'a rien à comparer à la feuille qu'on vient de lui remettre.
         */
        app(Reglages::class)->set('cert_accent_color', '#B5179E');

        [, $certificat] = certificatDe();

        Livewire::test(VerifierCertificat::class)
            ->set('code', $certificat->code)
            ->call('verifier')
            ->assertSee('Aya Sossou')
            ->assertSee('#B5179E', escape: false);
    });
});

describe('le décor tient aux deux contraintes du projet', function (): void {
    it('porte sa géométrie dans le document, pas dans le build figé', function (): void {
        /*
         | LA leçon de ce lot, apprise au passage navigateur.
         |
         | La première version composait le certificat en HTML avec des classes
         | utilitaires. SEPT d'entre elles n'existaient pas dans le build
         | Tailwind — qu'aucune chaîne d'outils ne régénère — et étaient donc
         | silencieusement inertes : le QR sortait au double de sa taille, sans
         | erreur, sans avertissement.
         |
         | Un SVG à `viewBox` ne peut pas subir cela : sa géométrie est dans le
         | document. C'est ce que ce test garde — pas un détail de mise en page,
         | mais l'indépendance vis-à-vis d'une feuille qu'on ne maîtrise pas.
         */
        [$titulaire, $certificat] = certificatDe();

        $rendu = Livewire::actingAs($titulaire)
            ->test(CertificatCitoyen::class, ['certificate' => $certificat->code])
            ->html();

        expect($rendu)->toContain('viewBox="0 0 1600 1100"')
            ->and($rendu)->toContain('<svg');
    });

    it('donne des identifiants uniques à ses dégradés', function (): void {
        /*
         | En SVG, un `id` en double est silencieusement gagné par le premier
         | venu. Deux certificats sur une même page — l'aperçu d'administration
         | aujourd'hui, une liste demain — verraient le second hériter des
         | couleurs du premier.
         */
        [$titulaire, $premier] = certificatDe();
        [, $second] = certificatDe($titulaire);

        $rendu = Livewire::actingAs($titulaire)
            ->test(CertificatCitoyen::class, ['certificate' => $premier->code])
            ->html();

        $idPremier = 'c'.substr(md5($premier->code), 0, 8);
        $idSecond = 'c'.substr(md5($second->code), 0, 8);

        expect($idPremier)->not->toBe($idSecond)
            ->and($rendu)->toContain($idPremier.'-or');
    });

    it('force l\'impression des fonds, sans quoi la feuille sort blanche', function (): void {
        /*
         | La règle sans laquelle tout le décor ne sert à rien : les navigateurs
         | retirent fonds et dégradés à l'impression pour épargner l'encre. Le
         | défaut ne se voit PAS à l'écran — seulement une fois la feuille
         | sortie.
         */
        $css = file_get_contents(public_path('assets/css/pages/user-certificat.css'));

        expect($css)->toContain('print-color-adjust: exact')
            // Le préfixe reste nécessaire pour les moteurs WebKit.
            ->and($css)->toContain('-webkit-print-color-adjust: exact')
            // Un certificat tient sur UNE page : une signature seule sur une
            // seconde feuille ferait douter du document.
            ->and($css)->toContain('page-break-inside: avoid');
    });

    it('s\'imprime en paysage', function (): void {
        /*
         | Le gabarit est en 1600 × 1100. Imposé en portrait — le défaut d'un
         | navigateur — il sortirait au tiers de la largeur de la feuille, avec
         | un nom de titulaire illisible et un QR trop petit pour être scanné.
         |
         | Ce test remplace celui du repli `color-mix` : les vagues en dégradé
         | CSS ont disparu avec la version HTML, et un SVG n'emploie pas cette
         | fonction.
         */
        $css = file_get_contents(public_path('assets/css/pages/user-certificat.css'));

        expect($css)->toContain('size: A4 landscape');
    });
});

describe('l\'écran d\'administration', function (): void {
    beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'otp_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->admin->assignRole(UserRole::Admin->value);
    });

    it('montre le document sur un exemple', function (): void {
        $this->actingAs($this->admin, 'staff')
            ->get(route('staff.certificate-template'))
            ->assertOk()
            ->assertSee('Nom du titulaire')
            ->assertSee('VERIF-EXEMPLE-0000');
    });

    it('n\'enregistre AUCUN certificat pour son aperçu', function (): void {
        /*
         | Créer une ligne réelle pour un aperçu polluerait la table des
         | diplômes — et un aperçu oublié en base finirait par ressembler à une
         | fraude.
         */
        $this->actingAs($this->admin, 'staff')->get(route('staff.certificate-template'))->assertOk();

        expect(Certificate::query()->count())->toBe(0);
    });

    it('ne valide rien sur la page publique avec le code d\'exemple', function (): void {
        Livewire::test(VerifierCertificat::class)
            ->set('code', 'VERIF-EXEMPLE-0000')
            ->call('verifier')
            ->assertDontSee('Nom du titulaire');
    });

    it('reste fermé à un modérateur', function (): void {
        $moderateur = User::factory()->create([
            'otp_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        $moderateur->assignRole(UserRole::Moderateur->value);

        $this->actingAs($moderateur, 'staff')
            ->get(route('staff.certificate-template'))
            ->assertForbidden();
    });
});
