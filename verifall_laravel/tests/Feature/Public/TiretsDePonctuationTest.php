<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Aucun tiret de ponctuation sur les pages publiques
|--------------------------------------------------------------------------
| Consigne éditoriale : ni tiret cadratin (—), ni demi-cadratin (–) dans le
| texte que lit un visiteur.
|
| ── LA NUANCE, ET ELLE COMPTE ────────────────────────────────────────────
|
| Seuls les tirets de PONCTUATION sont visés. Ceux qui vivent DANS un mot
| restent : « fact-checking », « Porto-Novo », « e-services », « au-delà ».
| Les retirer produirait des fautes d'orthographe, pas un texte plus propre.
|
| ── CE QUI N'EST PAS COMPTÉ ──────────────────────────────────────────────
|
| Les commentaires du code et du JavaScript embarqué : ce sont des notes pour
| les développeurs, elles ne s'affichent nulle part. La consigne porte sur ce
| que le visiteur lit.
|
| ── POURQUOI SUR LE RENDU, ET NON SUR LES FICHIERS ───────────────────────
|
| Parce qu'un tiret peut venir d'ailleurs que d'une vue : un titre de cours en
| base, une description SEO, un libellé de route. Trois d'entre eux ont été
| trouvés exactement comme cela, alors que toutes les vues étaient propres.
*/

/** Le texte réellement lu : balises retirées, scripts et commentaires exclus. */
function texteVisible(string $html): string
{
    $html = (string) preg_replace('#<script.*?</script>#s', ' ', $html);
    $html = (string) preg_replace('#<style.*?</style>#s', ' ', $html);
    $html = (string) preg_replace('#<!--.*?-->#s', ' ', $html);

    // Les attributs lus à voix haute comptent autant que le texte : quelqu'un
    // qui emploie un lecteur d'écran les entend.
    preg_match_all('/(?:alt|title|aria-label|content)="([^"]*)"/', $html, $attributs);

    return preg_replace('/<[^>]+>/', ' ', $html).' '.implode(' ', $attributs[1] ?? []);
}

it('ne publie AUCUN tiret de ponctuation, sur aucune page', function (string $route): void {
    $visible = texteVisible((string) test()->get(route($route))->assertOk()->getContent());

    $trouves = [];

    foreach (['—', '–'] as $tiret) {
        if (str_contains($visible, $tiret)) {
            preg_match('/.{0,60}'.preg_quote($tiret, '/').'.{0,60}/u', $visible, $extrait);
            $trouves[] = trim((string) ($extrait[0] ?? $tiret));
        }
    }

    expect($trouves)->toBe([], "Tiret de ponctuation trouvé sur {$route} : ".implode(' | ', $trouves));
})->with([
    'public.home',
    'public.why',
    'public.features',
    'public.how',
    'public.team',
    'public.contact',
    'public.faq',
    'public.media',
    'public.legal',
    'public.privacy',
    'public.cookies',
    'public.funding',
    'public.referral',
    'public.methodology',
    'public.corrections',
    'public.facts',
    'public.blog',
    'public.tutorials',
    'public.verify',
]);

it('GARDE les tirets qui sont dans les mots', function (): void {
    /*
     | Le pendant du test précédent, et il est nécessaire : sans lui, la façon
     | la plus simple de faire passer le premier serait de retirer tous les
     | tirets, ce qui écrirait « fact checking » et « Porto Novo ».
     */
    $visible = texteVisible((string) test()->get(route('public.methodology'))->getContent());

    expect($visible)->toContain('fact-checking');
});
