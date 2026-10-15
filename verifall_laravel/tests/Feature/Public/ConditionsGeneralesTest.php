<?php

declare(strict_types=1);

use App\Core\Application\Reglages\Reglages;
use App\Core\Identity\Services\SuppressionCompteService;
use App\Livewire\Auth\Register;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Les conditions générales existent enfin
|--------------------------------------------------------------------------
| Le formulaire d'inscription faisait cocher « j'accepte les conditions
| générales d'utilisation » et enregistrait le consentement `ConsentType::Cgu`
| — alors qu'AUCUNE page de conditions n'existait. Le lien menait à la FAQ.
|
| Ce n'était pas un lien cassé mais un défaut de fond : on ne peut pas consentir
| à un document qu'on ne peut pas lire, et sans conditions la plateforme n'a
| aucune règle opposable — ni pour écarter un compte abusif, ni pour borner sa
| responsabilité sur des verdicts.
|
| Les tests les plus utiles ici ne portent pas sur la présence du texte mais sur
| sa COHÉRENCE AVEC LE PRODUIT : un document juridique qui décrit un service
| qui n'existe pas engage sur du vide. C'est déjà arrivé sur ce projet — les
| mentions légales citaient un verdict « DOUTEUX » que la plateforme n'a jamais
| rendu.
*/

describe('la page', function (): void {
    it('répond et porte les seize sections', function (): void {
        $reponse = $this->get(route('public.terms'))->assertOk();

        foreach ((array) __('cgu.sections') as $section) {
            $reponse->assertSee($section['titre']);
        }
    });

    it('affiche une date d\'entrée en vigueur FIXE, pas celle du jour', function (): void {
        /*
         | `mentions-legales` affiche `now()` : la page prétend avoir été révisée
         | chaque jour. Sur un document juridique c'est une fausse information —
         | et c'est justement la date qu'on invoque pour savoir quelle version
         | on a acceptée.
         */
        $aujourdhui = (string) $this->get(route('public.terms'))->getContent();

        /*
         | Le voyage dans le temps est l'assertion, pas un décor. Écrire
         | « ce n'est pas la date du jour » ne prouverait rien : la version en
         | vigueur a précisément été écrite aujourd'hui. Ce qu'il faut établir,
         | c'est que la date NE BOUGE PAS — c'est là que `now()` se trahit.
         */
        $this->travel(3)->months();

        $troisMoisPlusTard = (string) $this->get(route('public.terms'))->getContent();

        expect($troisMoisPlusTard)->toContain('31 août 2026')
            ->and($troisMoisPlusTard)->not->toContain(now()->translatedFormat('j F Y'));

        // Et le rendu du jour disait déjà la même chose.
        expect($aujourdhui)->toContain('31 août 2026');
    });

    it('ne publie AUCUN espace réservé', function (): void {
        // Le prototype publiait « [Votre Adresse Complète] ». On ne recommence
        // pas : `<x-mention-legale>` dit « Non communiqué » au visiteur.
        $rendu = (string) $this->get(route('public.terms'))->getContent();

        // Un crochet ouvrant suivi d'un mot en capitale : la forme exacte des
        // espaces réservés du prototype.
        expect($rendu)->not->toMatch('/\[[A-ZÀ-Ü][^\]]{4,}\]/');

        /*
         | Et l'AUTRE forme d'espace réservé, celle que le crochet ne voit pas :
         | un jeton `:age` que personne n'a substitué. Le premier rendu de cette
         | page publiait « éditée par :entite » en clair — le texte portait un
         | jeton dont le contrôleur, à dessein, ne se chargeait pas. Un jeton
         | orphelin est un trou, au même titre qu'un crochet.
         |
         | Deux-points précédé d'une espace et suivi de minuscules : la forme des
         | jetons de Laravel en prose. Les `https://` et les `localhost:8199` du
         | gabarit n'y répondent pas.
         */
        expect($rendu)->not->toMatch('/\s:[a-z]{3,}\b/');
    });
});

describe('le texte reste d\'accord avec le produit', function (): void {
    it('cite les quatre verdicts RÉELS', function (): void {
        /*
         | Les mentions légales ont déjà annoncé un verdict « DOUTEUX » que la
         | plateforme n'a jamais rendu. Un document qui décrit un service
         | inexistant engage sur du vide.
         */
        $rendu = (string) $this->get(route('public.terms'))->getContent();

        foreach (['VRAI', 'FAUX', 'TROMPEUR', 'NON VÉRIFIABLE'] as $verdict) {
            expect($rendu)->toContain($verdict);
        }

        expect($rendu)->not->toContain('DOUTEUX');
    });

    it('cite le délai de rétractation du service, pas un nombre écrit à la main', function (): void {
        /*
         | Le texte lit `SuppressionCompteService::JOURS_RETRACTATION`. Écrire
         | « 30 jours » en dur le ferait mentir au premier changement — et c'est
         | la clause qu'on invoquerait pour contester une suppression.
         */
        $rendu = (string) $this->get(route('public.terms'))->getContent();

        expect($rendu)->toContain(SuppressionCompteService::JOURS_RETRACTATION.' jours');
    });

    it('suit l\'âge minimum réglé', function (): void {
        // 16 : une valeur qu'aucun défaut ne produirait par hasard.
        app(Reglages::class)->set('legal_minimum_age', '16');

        $this->get(route('public.terms'))->assertOk()->assertSee('16 ans');
    });
});

describe('le document est atteignable', function (): void {
    it('est lié depuis la case de consentement de l\'inscription', function (): void {
        // Le point de départ de tout : c'est là qu'on l'accepte.
        Livewire::test(Register::class)
            ->set('etape', 2)
            ->assertSee(route('public.terms'), escape: false);
    });

    it('est lié depuis le pied de page public', function (): void {
        $this->get(route('public.home'))->assertOk()->assertSee(route('public.terms'), escape: false);
    });

    it('figure au plan du site', function (): void {
        // Un document contractuel doit être indexable : c'est ce qu'on cherche
        // quand on veut savoir à quoi l'on s'est engagé.
        $this->get(route('public.sitemap'))->assertOk()->assertSee('conditions-generales');
    });
});
