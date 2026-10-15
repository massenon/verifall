<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LE BUILD TAILWIND EST FIGÉ — et ce test le prouve
|--------------------------------------------------------------------------
| `public/assets/css/tailwind.css` n'est pas engendré par ce dépôt : il est
| repris tel quel du prototype, et il EST la référence visuelle absolue. Aucun
| `npm run build` ne tourne ici — il n'existe ni `package-lock.json`, ni
| `node_modules`, et aucun gabarit n'appelle `@vite`.
|
| ── LE PIÈGE QUE CE FICHIER DÉSAMORCE ────────────────────────────────────────
|
| Un relevé de conformité a signalé cinq classes utilitaires employées dans les
| vues et ABSENTES du build : `py-2.5`, `px-3.5`, `disabled:opacity-40`,
| `!text-[16px]`, `hover:text-vfalse`. Écrites dans le balisage, elles ne
| produisent aucun effet et aucune erreur. La conclusion naturelle — « il faut
| les déclarer » — est FAUSSE, et il a fallu comparer les deux dépôts pour
| l'établir :
|
|   · le CSS du prototype et celui-ci sont IDENTIQUES AU BIT PRÈS
|     (même empreinte MD5) ;
|   · le prototype emploie lui-même `py-2.5` dans 95 de ses pages et `px-3.5`
|     dans 44 ;
|   · son propre CSS ne les définit pas davantage.
|
| Autrement dit, **le prototype s'affiche déjà sans ces classes**. La
| transposition les reproduit à l'identique, ce qui est exactement ce qu'on lui
| demande. Les déclarer changerait le rendu de dizaines d'écrans EXISTANTS —
| ce serait une refonte, pas un correctif.
|
| ── LE RISQUE RÉEL, CELUI QU'IL FAUT SURVEILLER ──────────────────────────────
|
| Il est à l'inverse de celui qu'on croyait. Le jour où quelqu'un ranime la
| chaîne Vite et REGÉNÈRE ce fichier depuis la configuration du prototype, ces
| classes apparaîtront — puisqu'elles sont bien présentes dans le balisage — et
| l'espacement de tout le site se déplacera d'un coup, sans qu'aucun test
| fonctionnel ne bronche.
|
| C'est ce que l'empreinte ci-dessous empêche de passer inaperçu.
*/

it('reste identique au build repris du prototype', function (): void {
    /*
     | Si ce test échoue, la question n'est pas « comment le faire passer » mais
     | « qui a modifié le CSS, et les 107 pages ont-elles été revues ? »
     |
     | Regénérer le build est une décision légitime — c'est un chantier à part
     | entière, qui exige de prouver que le rendu n'a pas bougé. Mettre à jour
     | l'empreinte SANS cette vérification transformerait le garde-fou en
     | formalité.
     */
    $chemin = public_path('assets/css/tailwind.css');

    expect(file_exists($chemin))->toBeTrue('Le build Tailwind figé est introuvable.')
        ->and(md5_file($chemin))->toBe(
            '4faab83a85800cd68a938190d4e43b88',
            'Le build Tailwind a changé. Il EST la référence visuelle : voir l’en-tête de ce fichier.',
        );
});

it('ne déclare toujours PAS les cinq classes signalées', function (): void {
    /*
     | Le pendant du test précédent, et le plus explicite des deux : il dit
     | qu'aucune de ces cinq classes ne doit être ajoutée, et pourquoi.
     |
     | Sans lui, quelqu'un lisant le relevé de conformité les déclarerait dans
     | une feuille annexe — ce qui contournerait l'empreinte ci-dessus tout en
     | produisant exactement l'effet qu'on veut éviter.
     */
    $feuilles = glob(public_path('assets/css/*.css')) ?: [];
    $feuilles = array_merge($feuilles, glob(public_path('assets/css/pages/*.css')) ?: []);

    $tout = '';

    foreach ($feuilles as $feuille) {
        $tout .= (string) file_get_contents($feuille);
    }

    foreach ([
        '.py-2\.5',
        '.px-3\.5',
        '.disabled\:opacity-40',
        '.hover\:text-vfalse',
    ] as $selecteur) {
        expect($tout)->not->toContain(
            $selecteur,
            "La classe {$selecteur} a été déclarée. Le prototype s’affiche SANS elle : la déclarer déplace le rendu de dizaines d’écrans existants.",
        );
    }
});
