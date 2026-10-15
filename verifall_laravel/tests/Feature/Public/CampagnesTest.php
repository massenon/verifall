<?php

declare(strict_types=1);

use App\Enums\TeinteCampagne;
use App\Enums\TypeCampagne;
use App\Models\MarketingCampaign;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function (): void {
    // Le compositeur met les campagnes en cache cinq minutes : sans purge, un
    // test verrait la liste du précédent.
    Cache::forget('campagnes-diffusables');
});

function campagne(array $extra = []): MarketingCampaign
{
    return MarketingCampaign::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'slug' => 'une-campagne',
        'title' => 'Un titre de campagne',
        'text' => 'Le texte de la campagne.',
        'cta' => 'Voir',
        'url' => '/verifier-une-information',
        'image' => 'assets/images/campaigns/whatsapp-factcheck.webp',
        'color' => 'success',
        'type' => TypeCampagne::Fenetre->value,
        'is_active' => true,
        'delay_seconds' => 8,
    ], $extra));
}

describe('ce qui s’affiche', function (): void {
    it('rend une fenêtre active', function (): void {
        campagne();

        $html = (string) $this->get(route('public.home'))->getContent();

        expect($html)->toContain('vf-campaign-overlay')
            ->and($html)->toContain('Un titre de campagne')
            ->and($html)->toContain('data-campagne-delai="8"');
    });

    it('rend un bandeau dans le flux, pas par-dessus', function (): void {
        campagne(['slug' => 'un-bandeau', 'type' => TypeCampagne::Bandeau->value]);

        $html = (string) $this->get(route('public.home'))->getContent();

        expect($html)->toContain('vf-campaign-banner')
            ->and($html)->not->toContain('vf-campaign-overlay');
    });

    it('apparaît sur toutes les pages publiques, pas seulement l’accueil', function (): void {
        campagne();

        foreach (['public.home', 'public.faq', 'public.blog'] as $route) {
            expect((string) $this->get(route($route))->getContent())
                ->toContain('Un titre de campagne');
            Cache::forget('campagnes-diffusables');
        }
    });
});

describe('ce qui ne s’affiche PAS', function (): void {
    it('ignore une campagne éteinte', function (): void {
        campagne(['is_active' => false]);

        expect((string) $this->get(route('public.home'))->getContent())
            ->not->toContain('Un titre de campagne');
    });

    it("ignore une campagne dont l'échéance est passée", function (): void {
        /*
         | Le cas qui coûte le plus cher. Une campagne d'échéance laissée
         | visible après coup décrédibilise le site davantage qu'elle ne l'a
         | servi avant : elle prouve que personne ne regarde.
         */
        campagne(['ends_at' => now()->subDay()]);

        expect((string) $this->get(route('public.home'))->getContent())
            ->not->toContain('Un titre de campagne');
    });

    it("ignore une campagne qui n'a pas encore commencé", function (): void {
        campagne(['starts_at' => now()->addWeek()]);

        expect((string) $this->get(route('public.home'))->getContent())
            ->not->toContain('Un titre de campagne');
    });

    it('charge sa feuille de style, et AVANT le balisage qui en dépend', function (): void {
        /*
         | Le défaut le plus coûteux de ce composant, et le plus trompeur.
         |
         | La feuille était poussée par `@push('styles')` depuis le composant.
         | Or `@stack('styles')` vit dans le `<head>` et Blade rend de haut en
         | bas : la pile est vidée AVANT que le corps ne s'exécute. La feuille
         | n'arrivait donc jamais — tandis que le script, poussé vers une pile
         | de fin de corps, arrivait bien.
         |
         | Résultat : le comportement fonctionnait, la mise en forme non.
         | `.vf-campaign-overlay` sans styles n'est plus une fenêtre modale mais
         | un `<div>` ordinaire en tête de corps — qui passe sous l'en-tête,
         | lequel est en `position: absolute`.
         |
         | Aucune erreur, aucun avertissement. Juste une page cassée.
         */
        campagne();

        $html = (string) $this->get(route('public.home'))->getContent();

        expect($html)->toContain('pages/campagnes.css');

        $feuille = strpos($html, 'pages/campagnes.css');
        $balisage = strpos($html, 'vf-campaign');

        expect($feuille)->toBeLessThan($balisage);
    });

    it("n'ajoute ni feuille ni script quand il n'y a aucune campagne", function (): void {
        // Zéro campagne doit coûter zéro octet : sinon chaque page traîne un
        // fichier pour une fonction inutilisée.
        $html = (string) $this->get(route('public.home'))->getContent();

        expect($html)->not->toContain('pages/campagnes.css')
            ->and($html)->not->toContain('pages/campagnes.js');
    });
});

describe('la sûreté du lien', function (): void {
    it('protège un lien sortant par rel="noopener"', function (): void {
        /*
         | Sans lui, la page de destination reçoit une référence vers la nôtre
         | par `window.opener` et peut la rediriger — sur un site dont le métier
         | est la confiance, c'est exactement l'attaque à ne pas laisser passer.
         */
        campagne(['url' => 'https://whatsapp.com/channel/verifall-benin']);

        $html = (string) $this->get(route('public.home'))->getContent();

        expect($html)->toContain('rel="noopener noreferrer"')
            ->and($html)->toContain('target="_blank"');
    });

    it("n'ouvre PAS un lien interne dans un nouvel onglet", function (): void {
        campagne(['url' => '/verifier-une-information']);

        $html = (string) $this->get(route('public.home'))->getContent();

        // Un lien interne qui s'ouvre ailleurs casse le bouton « retour ».
        preg_match('/<a href="\/verifier-une-information"[^>]*>/', $html, $lien);
        expect($lien[0] ?? '')->not->toContain('target="_blank"');
    });
});

describe('la barre supérieure', function (): void {
    it('porte le vert de marque et un texte blanc', function (): void {
        /*
         | La charte, vérifiée dans la feuille plutôt que de confiance. Le vert
         | vient de `--vf-primary` (#09B850) ; le texte et le titre sont blancs.
         | Le bouton prend l'INVERSE — fond blanc, texte vert — parce qu'un
         | bouton vert sur fond vert ne se distingue pas.
         */
        $css = file_get_contents(public_path('assets/css/pages/campagnes.css'));

        expect($css)->toContain('background: var(--vf-primary)')
            ->and($css)->toContain('.vf-campaign-banner-cta')
            ->and($css)->toContain('color: var(--vf-primary)');
    });

    it("se place EN HAUT et décale l'en-tête d'autant", function (): void {
        /*
         | L'en-tête du prototype est en `position: absolute; top: 0`. Une barre
         | au-dessus de lui doit le décaler, sinon les deux se superposent —
         | c'est exactement le défaut signalé sur la version précédente.
         |
         | Le décalage passe par une variable CSS que le script renseigne après
         | MESURE : une hauteur écrite en dur serait fausse dès que le texte
         | passe à la ligne, ce qui arrive sur tout téléphone.
         */
        $css = file_get_contents(public_path('assets/css/pages/campagnes.css'));
        $js = file_get_contents(public_path('assets/js/pages/campagnes.js'));

        expect($css)->toContain('.vf-has-topbar [data-navbar] { top: var(--vf-topbar-h')
            ->and($css)->toContain('.vf-has-topbar #contenu { padding-top: var(--vf-topbar-h')
            ->and($js)->toContain('--vf-topbar-h')
            ->and($js)->toContain('getBoundingClientRect');
    });

    it('retire le décalage quand la barre est fermée', function (): void {
        // Sans cela, l'en-tête et le contenu resteraient poussés vers le bas
        // d'une barre qui n'existe plus — un vide inexpliqué en haut de page.
        $js = file_get_contents(public_path('assets/js/pages/campagnes.js'));

        expect($js)->toContain("classList.remove('vf-has-topbar')");
    });

    it('déclare les trois paliers responsives', function (): void {
        /*
         | Mesuré avant ces règles : 155 px de haut sur un écran de 605 px —
         | un tiers de la hauteur utile, en-tête et contenu décalés d'autant.
         | Après : 48 px sur un écran de 390.
         |
         | Chaque palier retire quelque chose, dans l'ordre de ce qui informe
         | le moins : la vignette, puis le texte descriptif.
         */
        $css = file_get_contents(public_path('assets/css/pages/campagnes.css'));

        expect($css)->toContain('@media (max-width: 900px)')
            ->and($css)->toContain('@media (max-width: 640px)')
            ->and($css)->toContain('@media (max-width: 380px)');
    });
});

describe('les teintes', function (): void {
    it('ne rend QUE des classes présentes dans le build figé', function (): void {
        /*
         | Le vocabulaire des campagnes est celui de Bootstrap — `success`,
         | `info`. Le build Tailwind du prototype ne connaît ni `bg-success` ni
         | `bg-info` : les écrire aurait produit des boutons sans couleur, et le
         | défaut serait resté invisible en relecture puisque la classe EST là.
         */
        /*
         | Les classes de teinte vivent désormais dans `campagnes.css` et non
         | dans le build Tailwind — précisément parce que le build est figé et
         | ne les contient pas. C'est ce fichier-là qu'il faut vérifier.
         */
        $css = file_get_contents(public_path('assets/css/pages/campagnes.css'));

        foreach (TeinteCampagne::cases() as $teinte) {
            expect($css)->toContain('.'.$teinte->classe());
        }
    });

    it('se rabat sur la teinte principale devant un nom inconnu', function (): void {
        expect(TeinteCampagne::depuis('chartreuse'))->toBe(TeinteCampagne::Principale)
            ->and(TeinteCampagne::depuis(null))->toBe(TeinteCampagne::Principale);
    });
});

describe('la fenêtre à deux colonnes', function (): void {
    it("montre l'image ENTIÈRE, sans la recadrer", function (): void {
        /*
         | La feuille imposait `height: 180px; object-fit: cover`. Sur une
         | affiche — le format que ces visuels prennent presque toujours — cela
         | découpait une bande au milieu : titre au-dessus du cadre, appel à
         | l'action en dessous, il ne restait que le fond.
         |
         | Le défaut était discret parce que l'image se chargeait bien. Rien ne
         | signalait qu'on n'en voyait qu'un tiers.
         */
        $css = file_get_contents(public_path('assets/css/pages/campagnes.css'));

        // PAS `Str::between` : il découpe jusqu'à la DERNIÈRE accolade du
        // fichier, donc jusqu'à la fin de la feuille. La règle voisine, qui
        // porte légitimement `object-fit`, se retrouvait dans l'extrait et
        // faisait échouer un test pourtant juste sur le fond.
        $bloc = Str::before(Str::after($css, '.vf-campaign-media {'), '}');

        expect($bloc)->toContain('object-fit: contain')
            ->and($bloc)->not->toContain('object-fit: cover')
            ->and($bloc)->not->toContain('height: 180px');
    });

    it('passe en deux colonnes UNIQUEMENT quand il y a une image', function (): void {
        /*
         | Sans image, la grille à deux colonnes laisserait une colonne vide et
         | le texte serré dans l'autre moitié. Le gabarit décide, parce qu'il
         | est le seul à savoir.
         */
        campagne(['slug' => 'avec-image']);

        $html = (string) $this->get(route('public.home'))->getContent();
        expect($html)->toContain('vf-campaign-dialog--avec-media');

        Cache::forget('campagnes-diffusables');
        MarketingCampaign::query()->delete();
        campagne(['slug' => 'sans-image', 'image' => null]);

        $html = (string) $this->get(route('public.home'))->getContent();
        expect($html)->toContain('vf-campaign-overlay')
            ->and($html)->not->toContain('vf-campaign-dialog--avec-media');
    });

    it('ne déclare plus un format que ces images n’ont pas', function (): void {
        /*
         | `width="440" height="180"` affirmait un rapport que des visuels
         | téléversés depuis l'administration n'ont aucune raison de respecter.
         | Le navigateur s'en sert pour réserver la place : un rapport faux
         | déforme la réservation, donc l'image.
         */
        campagne();

        $html = (string) $this->get(route('public.home'))->getContent();

        expect($html)->toContain('class="vf-campaign-media"')
            ->and($html)->not->toContain('width="440" height="180"');
    });

    it('traduit son bouton secondaire', function (): void {
        // « Plus tard » était en dur dans le gabarit : il restait français sur
        // l'interface anglaise, comme les autres boutons que l'extraction avait
        // sautés à cause de leurs attributs dynamiques.
        campagne();

        expect((string) $this->get(route('public.home'))->getContent())
            ->toContain('Plus tard');

        app()->setLocale('en');

        expect(__('campagnes.plus_tard'))->toBe('Later');
    });
});
