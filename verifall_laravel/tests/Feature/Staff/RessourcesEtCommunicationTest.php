<?php

declare(strict_types=1);

use App\Core\Marketing\Services\GabaritsDEmail;
use App\Enums\OtpPurpose;
use App\Enums\TypeCampagne;
use App\Livewire\Staff\BadgesDeProgression;
use App\Livewire\Staff\CampagnesPublicitaires;
use App\Livewire\Staff\GabaritsEmails;
use App\Livewire\Staff\GestionFaq;
use App\Livewire\Staff\Partenaires;
use App\Livewire\Staff\Temoignages;
use App\Mail\OtpCodeMail;
use App\Models\Badge;
use App\Models\EmailTemplate;
use App\Models\Faq;
use App\Models\MarketingCampaign;
use App\Models\Partner;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\Rbac\MenuDuBackOffice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Ressources et communication — lot 9
|--------------------------------------------------------------------------
| Six écrans, quatre tables neuves, et un principe tenu jusqu'au bout :
| PAS D'ÉCRAN QUI ÉCRIT DES LIGNES QUE RIEN NE LIT.
|
| Le cas le plus net est celui des gabarits d'e-mails. Un écran « sujet + corps »
| aurait été une fausse promesse — les six courriels portent de la logique dans
| leur corps. L'écran ne surcharge donc que le SUJET, et un test envoie un
| courriel pour vérifier que la surcharge s'applique réellement.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

describe('la FAQ', function (): void {
    it('a enfin un écran, et le menu n\'envoie plus sur la page publique', function (): void {
        /*
         | LE DÉFAUT SIGNALÉ. « Gestion FAQ » pointait sur `route('public.faq')` :
         | on cliquait pour gérer et on atterrissait en lecture seule. Le test
         | lit le GABARIT, pas une page rendue, pour attraper aussi une entrée
         | cachée derrière un `@can`.
         */
        /*
         | Lu dans la DÉCLARATION depuis le 3 septembre 2026. Le défaut d'origine
         | tient toujours : « Gestion FAQ » pointait sur `public.faq`, on
         | cliquait pour gérer et on atterrissait en lecture seule.
         |
         | La déclaration ne peut d'ailleurs plus porter une route publique : un
         | test de `MenuDuBackOfficeTest` exige que chaque entrée soit gardée,
         | et `public.faq` ne l'est pas.
         */
        $routes = collect(MenuDuBackOffice::GROUPES)
            ->flatMap(static fn (array $g): array => array_keys($g));

        expect($routes)->not->toContain('public.faq')
            ->and($routes)->toContain('staff.faq');

        $this->actingAs($this->admin, 'staff')->get(route('staff.faq'))->assertOk();
    });

    it('écrit les deux langues d\'un seul geste', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(GestionFaq::class)
            ->call('ouvrirCreation')
            ->set('questionFr', 'Comment signaler une intox ?')
            ->set('reponseFr', 'Depuis le bouton « signaler » de chaque contenu.')
            ->set('questionEn', 'How do I report false information?')
            ->set('reponseEn', 'From the report button on any content.')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $lignes = Faq::query()->get();

        expect($lignes)->toHaveCount(2)
            // Reliées par la même clé : sans elle, corriger la française ne
            // permettrait pas de retrouver l'anglaise.
            ->and($lignes->pluck('group_key')->unique())->toHaveCount(1);
    });

    it('supprime la traduction anglaise quand on la vide', function (): void {
        /*
         | Garder une traduction devenue vide laisserait une question anglaise
         | SANS RÉPONSE — pire qu'un repli sur le français, qui dit au moins
         | quelque chose.
         */
        $composant = Livewire::actingAs($this->admin, 'staff')->test(GestionFaq::class);

        $composant->call('ouvrirCreation')
            ->set('questionFr', 'Une question')
            ->set('reponseFr', 'Une réponse suffisamment longue.')
            ->set('questionEn', 'A question')
            ->set('reponseEn', 'An answer long enough.')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $cle = Faq::query()->where('locale', 'fr')->value('group_key');

        $composant->call('ouvrirEdition', $cle)
            ->set('questionEn', '')
            ->set('reponseEn', '')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(Faq::query()->where('locale', 'en')->count())->toBe(0)
            ->and(Faq::query()->where('locale', 'fr')->count())->toBe(1);
    });

    it('refuse une question anglaise sans sa réponse', function (): void {
        // Elle s'afficherait vide, alors que l'absence des DEUX fait proprement
        // replier la page sur le français.
        Livewire::actingAs($this->admin, 'staff')
            ->test(GestionFaq::class)
            ->call('ouvrirCreation')
            ->set('questionFr', 'Une question')
            ->set('reponseFr', 'Une réponse suffisamment longue.')
            ->set('questionEn', 'A question without an answer')
            ->set('reponseEn', '')
            ->call('enregistrer')
            ->assertHasErrors('reponseEn');
    });

    it('sert la page publique depuis la TABLE', function (): void {
        Faq::query()->create([
            'uuid' => (string) Str::uuid(),
            'locale' => 'fr',
            'group_key' => 'question-de-test',
            'question' => 'Une question venue de la base',
            'answer' => 'Et sa réponse.',
            'sort_order' => 10,
            'is_published' => true,
        ]);

        $this->get(route('public.faq'))
            ->assertOk()
            ->assertSee('Une question venue de la base');
    });

    it('masque de la page publique une question dépubliée', function (): void {
        Faq::query()->create([
            'uuid' => (string) Str::uuid(),
            'locale' => 'fr',
            'group_key' => 'brouillon',
            'question' => 'Une question pas encore prête',
            'answer' => 'Et sa réponse.',
            'sort_order' => 10,
            'is_published' => false,
        ]);

        $this->get(route('public.faq'))
            ->assertOk()
            ->assertDontSee('Une question pas encore prête');
    });
});

describe('les gabarits d\'e-mails', function (): void {
    it('CHANGE RÉELLEMENT le sujet du courriel envoyé', function (): void {
        /*
         | LE TEST QUI DISTINGUE CET ÉCRAN D'UNE DÉCORATION. Sans lui, on
         | pourrait écrire des lignes en base et croire qu'elles servent —
         | c'est le défaut que le chantier a refusé deux fois.
         */
        $avant = (new OtpCodeMail('123456', OtpPurpose::Registration))
            ->envelope()->subject;

        EmailTemplate::query()->create([
            'uuid' => (string) Str::uuid(),
            'key' => 'otp',
            'subject' => 'Votre code Vérif\'All',
        ]);

        app(GabaritsDEmail::class)->oublier();

        $apres = (new OtpCodeMail('123456', OtpPurpose::Registration))
            ->envelope()->subject;

        expect($apres)->toBe('Votre code Vérif\'All')
            ->and($apres)->not->toBe($avant);
    });

    it('se replie sur le sujet écrit en code quand rien n\'est surchargé', function (): void {
        // Une ligne absente n'est PAS une anomalie : la plateforme doit
        // fonctionner table vide, et elle le fait.
        expect(EmailTemplate::query()->count())->toBe(0)
            ->and(app(GabaritsDEmail::class)->sujet('otp', 'Le sujet du code'))
            ->toBe('Le sujet du code');
    });

    it('oublie le cache à l\'écriture', function (): void {
        $service = app(GabaritsDEmail::class);

        // Amorce le cache sur une table vide.
        expect($service->sujet('export_pret', 'défaut'))->toBe('défaut');

        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->call('ouvrirEdition', 'export_pret')
            ->set('sujet', 'Vos données sont prêtes')
            // Le formulaire porte désormais le corps, pas seulement l'objet :
            // un enregistrement sans lui n'est plus valide.
            ->set('titre', 'Votre archive est prête')
            ->set('corps', 'Bonjour, votre export de données est prêt au téléchargement.')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($service->sujet('export_pret', 'défaut'))->toBe('Vos données sont prêtes');
    });

    it('refuse une clé qui ne correspond à aucun envoi', function (): void {
        // Une ligne dont la clé ne sert à rien laisserait croire qu'on ajoute
        // un courriel en ajoutant une ligne.
        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->call('ouvrirEdition', 'clef_inventee')
            ->assertNotFound();
    });

    it('réécrit le texte d\'origine plutôt que de supprimer la ligne', function (): void {
        /*
         | Ce test disait l'inverse, et il avait raison de le dire : tant que
         | seul l'OBJET était surchargeable, « pas de surcharge » se disait par
         | l'absence de ligne.
         |
         | Ce n'est plus tenable depuis que la ligne porte AUSSI le corps —
         | la supprimer laisserait le courriel sans texte du tout. La ligne
         | existe donc toujours, et c'est `updated_by` qui dit si un être humain
         | y a touché.
         */
        EmailTemplate::query()->create([
            'uuid' => (string) Str::uuid(),
            'key' => 'otp',
            'subject' => 'Un sujet bricolé',
            'heading' => 'Un titre bricolé',
            'body' => 'Un corps bricolé, suffisamment long pour être valide.',
            'updated_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(GabaritsEmails::class)
            ->call('reinitialiser', 'otp');

        $gabarit = EmailTemplate::query()->where('key', 'otp')->firstOrFail();

        expect($gabarit->subject)->not->toBe('Un sujet bricolé')
            ->and($gabarit->body)->toContain('code de vérification')
            // La signature s'efface : la ligne redevient « texte d'origine ».
            ->and($gabarit->updated_by)->toBeNull();
    });
});

describe('les partenaires', function (): void {
    it('alimente le carrousel de l\'accueil', function (): void {
        Partner::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bailleur de test',
            'logo_path' => 'assets/images/partners/ofaj.png',
            'url' => 'https://exemple-bailleur.org/',
            'sort_order' => 10,
            'is_published' => true,
        ]);

        Cache::forget('partenaires-accueil');

        $this->get(route('public.home'))->assertOk()->assertSee('Bailleur de test');
    });

    it('refuse un chemin de logo qui remonte l\'arborescence', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(Partenaires::class)
            ->call('ouvrirCreation')
            ->set('nom', 'Partenaire piégé')
            ->set('logo', '../../.env')
            ->call('enregistrer')
            ->assertHasErrors('logo');
    });

    it('oublie le cache de l\'accueil à l\'écriture', function (): void {
        // Une heure, c'est long quand on retire un partenaire parce que la
        // convention a pris fin.
        $partenaire = Partner::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'À retirer', 'sort_order' => 10, 'is_published' => true,
        ]);

        Cache::put('partenaires-accueil', collect([$partenaire]), now()->addHour());

        Livewire::actingAs($this->admin, 'staff')
            ->test(Partenaires::class)
            ->call('basculerLaPublication', $partenaire->uuid);

        expect(Cache::has('partenaires-accueil'))->toBeFalse();
    });
});

describe('les témoignages', function (): void {
    it('naît NON publié — un témoignage se relit', function (): void {
        /*
         | L'erreur n'a pas la même conséquence dans les deux sens : un
         | témoignage qui attend un jour ne coûte rien ; un témoignage publié
         | par inadvertance engage la plateforme sur des propos non relus.
         */
        Livewire::actingAs($this->admin, 'staff')
            ->test(Temoignages::class)
            ->call('ouvrirCreation')
            ->set('auteur', 'Awa Gbaguidi')
            ->set('citation', 'La plateforme m\'a appris à vérifier avant de partager.')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(Testimonial::query()->value('is_published'))->toBeFalse();
    });

    it('laisse la note VIDE plutôt que de mettre cinq étoiles', function (): void {
        // Mettre 5 par défaut fabriquerait des avis élogieux que personne n'a
        // donnés — et une moyenne calculée dessus serait un chiffre inventé.
        Livewire::actingAs($this->admin, 'staff')
            ->test(Temoignages::class)
            ->call('ouvrirCreation')
            ->set('auteur', 'Sans note')
            ->set('citation', 'Un témoignage sans note attribuée par la personne.')
            ->set('note', '')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect(Testimonial::query()->value('rating'))->toBeNull();
    });
});

describe('les campagnes', function (): void {
    it('naît INACTIVE, et oublie le cache à l\'activation', function (): void {
        Livewire::actingAs($this->admin, 'staff')
            ->test(CampagnesPublicitaires::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Alerte WhatsApp')
            ->set('texte', 'Rejoignez notre chaîne pour les alertes en temps réel.')
            // Bouton et destination sont obligatoires : le schéma les veut
            // ainsi, et une campagne sans eux n'aurait rien à proposer.
            ->set('bouton', 'Rejoindre')
            ->set('lien', 'https://whatsapp.com/channel/verifall')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $campagne = MarketingCampaign::query()->firstOrFail();

        expect($campagne->is_active)->toBeFalse();

        Cache::put('campagnes-diffusables', collect(), now()->addMinutes(5));

        Livewire::actingAs($this->admin, 'staff')
            ->test(CampagnesPublicitaires::class)
            ->call('basculerLActivation', $campagne->uuid);

        expect(Cache::has('campagnes-diffusables'))->toBeFalse();
    });

    it('refuse une fenêtre qui se termine avant de commencer', function (): void {
        // La campagne ne paraîtrait JAMAIS — et le scope ne dirait pas pourquoi.
        Livewire::actingAs($this->admin, 'staff')
            ->test(CampagnesPublicitaires::class)
            ->call('ouvrirCreation')
            ->set('titre', 'Campagne impossible')
            ->set('texte', 'Un message de campagne suffisamment long.')
            ->set('bouton', 'Voir')
            ->set('lien', '/verifier-une-information')
            ->set('debut', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('fin', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('enregistrer')
            ->assertHasErrors('fin');
    });

    it('rouvre une fenêtre à l\'édition, et l\'enregistre sous la valeur de l\'enum', function (): void {
        /*
         | Régression de production du 14 septembre 2026 : `(string)` sur le cast
         | enum faisait planter « Modifier » sur TOUTES les campagnes, et l'écran
         | proposait `overlay` quand l'enum n'en connaît que `popup` — créer une
         | modale plantait aussi.
         */
        $campagne = MarketingCampaign::query()->forceCreate([
            'uuid' => (string) Str::uuid(),
            'slug' => 'une-fenetre',
            'title' => 'Une fenêtre',
            'text' => 'Un message de campagne suffisamment long.',
            'cta' => 'Voir',
            'url' => '/verifier-une-information',
            'type' => TypeCampagne::Fenetre->value,
            // Le vocabulaire du seeder et du site public : l'écran exigeait un
            // code hexadécimal, et refusait d'enregistrer cette campagne.
            'color' => 'info',
            'delay_seconds' => 5,
            'position' => 0,
            'is_active' => false,
        ]);

        Livewire::actingAs($this->admin, 'staff')
            ->test(CampagnesPublicitaires::class)
            ->assertSee('Fenêtre')
            ->call('ouvrirEdition', $campagne->uuid)
            ->assertSet('type', TypeCampagne::Fenetre->value)
            ->assertSet('couleur', 'info')
            ->set('titre', 'Une fenêtre modifiée')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($campagne->refresh()->type)->toBe(TypeCampagne::Fenetre)
            ->and($campagne->color)->toBe('info')
            ->and($campagne->title)->toBe('Une fenêtre modifiée');
    });
});

describe('les badges', function (): void {
    it('fige le slug, dont dépend l\'attribution', function (): void {
        /*
         | Le laisser suivre un nom corrigé ARRÊTERAIT l'attribution :
         | `AttributionDesBadges` reconnaît les badges par leur slug. Ceux qui
         | l'ont le gardent, les suivants ne l'auront jamais, et rien ne le
         | signalera.
         */
        $composant = Livewire::actingAs($this->admin, 'staff')->test(BadgesDeProgression::class);

        $composant->call('ouvrirCreation')
            ->set('nom', 'Premier pas')
            ->set('descriptif', 'Votre première vérification.')
            ->set('condition', 'Vérifier une information')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $badge = Badge::query()->latest('id')->firstOrFail();
        $slugOrigine = $badge->slug;

        $composant->call('ouvrirEdition', $badge->uuid)
            ->set('nom', 'Premier pas vers la vérité')
            ->call('enregistrer')
            ->assertHasNoErrors();

        expect($badge->refresh()->name)->toBe('Premier pas vers la vérité')
            ->and($badge->slug)->toBe($slugOrigine);
    });

    it('borne les unités à 100', function (): void {
        // Un badge à 500 unités vaudrait plus que tous les packs vendus, et se
        // débloquerait sans encaissement.
        Livewire::actingAs($this->admin, 'staff')
            ->test(BadgesDeProgression::class)
            ->call('ouvrirCreation')
            ->set('nom', 'Badge démesuré')
            ->set('descriptif', 'Une description valide.')
            ->set('condition', 'Une condition')
            ->set('unites', '5000')
            ->call('enregistrer')
            ->assertHasErrors('unites');
    });
});
