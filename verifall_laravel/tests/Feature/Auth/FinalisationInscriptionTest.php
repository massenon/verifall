<?php

declare(strict_types=1);

use App\Core\Application\Reglages\Reglages;
use App\Core\Identity\Services\InscriptionCitoyen;
use App\Core\Marketing\Services\Parrainage;
use App\Enums\MotifMouvementUnite;
use App\Livewire\Auth\Register;
use App\Models\UnitLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

beforeEach(function (): void {
    // `InscriptionCitoyen` attribue le rôle Citoyen : sans ce semis,
    // l'inscription échoue avant d'atteindre le bonus.
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Finalisation de l'inscription
|--------------------------------------------------------------------------
| Le lot 1 avait débloqué le parcours : le code OTP ne partait pas faute de
| worker. Restait ce qui rendait l'inscription « pas finie » une fois les
| quatre étapes franchies.
|
| Le premier bloc porte LE défaut de fond : un compte neuf arrivait au tableau
| de bord avec ZÉRO unité. La première action du produit — vérifier une
| information — était fermée à qui venait de s'inscrire pour elle. Le réglage
| « Bonus de bienvenue » existait pourtant, à 5, lu par personne : troisième
| cas de l'écart É-02 après le barème de parrainage et le certificat.
*/

/** Un jeu de saisie valide pour les étapes 1 et 2. */
function inscriptionValide(string $email = 'nouvelle@verifall.com'): object
{
    return Livewire::test(Register::class)
        ->set('prenom', 'Aya')
        ->set('nom', 'Sossou')
        ->set('telephone', '+229 97 00 00 00')
        ->set('profession', 'Journaliste')
        ->call('versEtape2')
        ->set('email', $email)
        ->set('motDePasse', 'Cotonou2026!Fact')
        ->set('motDePasseConfirmation', 'Cotonou2026!Fact')
        ->set('accepteCgu', true)
        ->set('acceptePrivacy', true);
}

describe('le bonus de bienvenue', function (): void {
    it('est réellement crédité à la création du compte', function (): void {
        /*
         | LE correctif. Sans lui, `users.units` reste à sa valeur par défaut —
         | zéro — et le tableau de bord accueille quelqu'un qui ne peut rien
         | faire.
         */
        inscriptionValide()->call('versEtape3')->assertHasNoErrors();

        $compte = User::query()->where('email', 'nouvelle@verifall.com')->firstOrFail();

        expect($compte->units)->toBe(InscriptionCitoyen::BONUS_BIENVENUE_PAR_DEFAUT);
    });

    it('suit le réglage, pas une constante', function (): void {
        // 37 : une valeur qu'aucun défaut ne produirait par hasard.
        app(Reglages::class)->set('default_user_units', '37');

        inscriptionValide()->call('versEtape3');

        expect(User::query()->where('email', 'nouvelle@verifall.com')->firstOrFail()->units)->toBe(37);
    });

    it('laisse une trace au journal des unités', function (): void {
        /*
         | Un solde qui bouge sans ligne de journal est un solde qu'on ne peut
         | pas expliquer à quelqu'un qui le conteste.
         */
        inscriptionValide()->call('versEtape3');

        $ligne = UnitLog::query()->where('reason', MotifMouvementUnite::Bienvenue->value)->first();

        expect($ligne)->not->toBeNull()
            ->and((int) $ligne->delta)->toBe(InscriptionCitoyen::BONUS_BIENVENUE_PAR_DEFAUT);
    });

    it('ne crée aucun mouvement si le bonus est réglé à zéro', function (): void {
        // Un crédit nul serait refusé par `EconomyService`, et une ligne à zéro
        // unité ne raconterait rien.
        app(Reglages::class)->set('default_user_units', '0');

        inscriptionValide()->call('versEtape3')->assertHasNoErrors();

        expect(User::query()->where('email', 'nouvelle@verifall.com')->firstOrFail()->units)->toBe(0)
            ->and(UnitLog::query()->where('reason', MotifMouvementUnite::Bienvenue->value)->count())->toBe(0);
    });

    it('ne fait PAS échouer l\'inscription s\'il ne peut pas être crédité', function (): void {
        /*
         | Un solde à zéro est regrettable ; un compte qu'on ne peut pas créer
         | l'est beaucoup plus. Le crédit est donc enveloppé — et journalisé,
         | pour que l'équipe retrouve le cas sans attendre une plainte.
         */
        app(Reglages::class)->set('default_user_units', 'pas-un-nombre');

        inscriptionValide()->call('versEtape3')->assertHasNoErrors();

        expect(User::query()->where('email', 'nouvelle@verifall.com')->exists())->toBeTrue();
    });
});

describe('l\'invitation est visible', function (): void {
    it('nomme le parrain sur le formulaire', function (): void {
        /*
         | Le code déposé par `/invitation/{code}` était consommé en SILENCE :
         | quelqu'un arrivant d'un lien WhatsApp voyait une inscription
         | ordinaire. C'est le maillon le plus fragile du parrainage — celui qui
         | partage a promis quelque chose, et c'est lui qui paraît avoir menti.
         */
        $parrain = User::factory()->actif()->create(['name' => 'Rita Adjovi']);
        $code = app(Parrainage::class)->codeDe($parrain);

        Session::put(Register::CLE_PARRAINAGE, $code);

        Livewire::test(Register::class)
            ->assertSee('Rita Adjovi vous a invité')
            ->assertSee('unités vous attendent');
    });

    it('n\'affiche rien sans invitation', function (): void {
        Livewire::test(Register::class)->assertDontSee('vous a invité');
    });

    it('retient le parrain jusqu\'à l\'étape 4, après l\'oubli de la session', function (): void {
        /*
         | Le code est consommé au passage à l'étape 3. Sans copie, la mention
         | disparaîtrait au moment précis où elle sert : l'étape 4 est l'endroit
         | où l'on dit au filleul ce qui l'attend, et grâce à qui.
         */
        $parrain = User::factory()->actif()->create(['name' => 'Rita Adjovi']);
        Session::put(Register::CLE_PARRAINAGE, app(Parrainage::class)->codeDe($parrain));

        inscriptionValide()
            ->call('versEtape3')
            ->assertSet('parrainNom', 'Rita Adjovi');
    });
});

describe('les consentements renvoient aux bons documents', function (): void {
    it('ne renvoie plus la politique de confidentialité vers la FAQ', function (): void {
        /*
         | Les DEUX liens de consentement pointaient sur `public.faq`. Celui de
         | la confidentialité était corrigeable tout de suite : la page existe.
         |
         | On ne peut pas consentir à un document qu'on ne peut pas lire — et
         | c'est précisément la case dont le RGPD exige qu'elle soit éclairée.
         */
        $rendu = Livewire::test(Register::class)->set('etape', 2)->html();

        expect($rendu)->toContain(route('public.privacy'))
            ->and($rendu)->not->toContain(route('public.faq'));
    });
});

describe('les étapes sont VISIBLES', function (): void {
    it('porte « is-active » sur chacun des quatre panneaux', function (): void {
        /*
         | LE défaut signalé à l'origine, trouvé au navigateur.
         |
         | `verifall.css` pose `[data-step-panel] { display: none }` et ne montre
         | que `.is-active`. Le gabarit ne portait la classe QUE sur l'étape 1 :
         | les étapes 2, 3 et 4 étaient rendues dans le DOM et **invisibles à
         | l'écran**. On remplissait l'identité, on cliquait « Continuer », le
         | stepper avançait — et la page devenait blanche.
         |
         | « Je me suis arrêté à l'étape 2 », mot pour mot.
         |
         | Aucun test ne pouvait l'attraper : le balisage EST présent, et
         | `assertSee` le trouve. C'est du CSS qui le cachait. Ce test-ci
         | contourne la limite en vérifiant le gabarit lui-même.
         */
        $gabarit = file_get_contents(resource_path('views/livewire/auth/register.blade.php'));

        foreach (['1', '2', '3', '4'] as $etape) {
            expect($gabarit)->toContain('data-step-panel="'.$etape.'" class="is-active"');
        }
    });
});

describe('la saisie du code à six cases', function (): void {
    it('s\'accroche à un évènement Livewire qui existe', function (): void {
        /*
         | Second défaut trouvé au navigateur. Le script écoutait
         | `livewire:update` — un évènement qui N'EXISTE PAS en Livewire 3.
         |
         | Conséquence : les six cases n'étaient jamais câblées, parce que
         | l'étape 3 n'apparaît pas au chargement de la page mais après un rendu
         | Livewire. À l'écran, les six chiffres s'entassaient dans la première
         | case, le champ caché restait vide, et « Valider le code » répondait
         | « Saisissez le code reçu par courriel ».
         */
        $script = file_get_contents(public_path('assets/js/pages/saisie-code.js'));

        expect($script)->not->toContain("'livewire:update'")
            ->and($script)->toContain("Livewire.hook('morphed'")
            // Repli si le crochet n'est pas disponible.
            ->and($script)->toContain("'livewire:initialized'");
    });

    it('borne chaque case à un chiffre', function (): void {
        // Sans `maxlength`, une case accepte les six chiffres — et c'est
        // exactement ce qu'on voyait quand le script ne tournait pas.
        $gabarit = file_get_contents(resource_path('views/livewire/auth/register.blade.php'));

        expect($gabarit)->toContain('maxlength="1"')
            // `one-time-code` : les téléphones proposent alors le code du SMS
            // ou du courriel en remplissage automatique.
            ->and($gabarit)->toContain('autocomplete="one-time-code"');
    });
});

describe('l\'étape 4 dit ce que le compte a reçu', function (): void {
    it('annonce le bonus réellement crédité', function (): void {
        // Le solde RÉEL, pas le réglage : si le crédit a échoué, l'écran ne
        // promet rien.
        inscriptionValide()
            ->call('versEtape3')
            ->assertSet('bonusRecu', InscriptionCitoyen::BONUS_BIENVENUE_PAR_DEFAUT);
    });

    it('mène à la première vérification, pas seulement au tableau de bord', function (): void {
        /*
         | Le geste que toute l'inscription prépare. L'écran n'offrait que
         | « Accéder à mon espace » — un tableau de bord où il faut encore
         | trouver quoi faire.
         */
        $composant = inscriptionValide()->call('versEtape3');
        $composant->set('etape', 4);

        expect($composant->html())->toContain(route('citizen.verify'));
    });
});
