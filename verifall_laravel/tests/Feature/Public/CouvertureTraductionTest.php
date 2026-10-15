<?php

declare(strict_types=1);

/*
 |============================================================================
 | LA COUVERTURE DE TRADUCTION, VÉRIFIÉE PLUTÔT QUE SUPPOSÉE
 |============================================================================
 |
 | `MultilingueTest` prouvait que la bascule de langue fonctionne : l'attribut
 | `lang` change, la session retient le choix. Tout cela était vrai, et tout
 | cela restait vrai pendant que MILLE CENT DOUZE chaînes s'affichaient en
 | français sur l'interface anglaise.
 |
 | Le trou n'était pas dans le mécanisme, il était dans ce que le mécanisme
 | atteint. Aucun test ne regardait le CONTENU.
 |
 | Les deux contrôles ci-dessous sont ceux qui manquaient. Ils partagent une
 | propriété qui justifie à eux seuls leur existence : LES DÉFAUTS QU'ILS
 | ATTRAPENT NE PRODUISENT AUCUNE ERREUR. Une clé absente s'affiche telle
 | quelle à l'écran — `cit_citizen.mes_evaluations` en pleine barre latérale —
 | sans exception, sans journal, sans page blanche. Rien ne la signale, sauf
 | quelqu'un qui regarde. C'est exactement ce qui est arrivé pendant ce
 | chantier, et c'est ce contrôle-ci qui l'a rattrapé.
 */

/** Toutes les clés déclarées dans une langue, sous la forme `fichier.chemin`. */
function clesDeclarees(string $langue): array
{
    $clefs = [];

    foreach (glob(base_path("lang/{$langue}/*.php")) as $fichier) {
        $contenu = require $fichier;

        if (! is_array($contenu)) {
            continue;
        }

        $aplatir = static function (array $tableau, string $prefixe) use (&$aplatir, &$clefs): void {
            foreach ($tableau as $cle => $valeur) {
                $chemin = $prefixe.'.'.$cle;

                if (is_array($valeur)) {
                    /*
                     | Le NŒUD lui-même compte comme clé, pas seulement ses
                     | feuilles : `__('cit_mes_tutoriels.guides')` renvoie
                     | légitimement un tableau, que la vue parcourt au
                     | `@foreach`. Ne retenir que les feuilles faisait passer
                     | cet appel pour un orphelin.
                     */
                    $clefs[$chemin] = $valeur;
                    $aplatir($valeur, $chemin);

                    continue;
                }

                $clefs[$chemin] = $valeur;
            }
        };

        $aplatir($contenu, basename($fichier, '.php'));
    }

    return $clefs;
}

/** Toutes les clés RÉELLEMENT appelées par une vue Blade. */
function clesAppeleesParLesVues(): array
{
    $appels = [];

    $iterateur = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'))
    );

    foreach ($iterateur as $fichier) {
        if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
            continue;
        }

        $source = file_get_contents($fichier->getPathname());

        /*
         | Seules les clés LITTÉRALES ET ENTIÈRES sont vérifiables.
         |
         | D'où le `[,)]` final, qui n'est pas un détail : sans lui,
         | `__('cit_outils.categorie.'.$suffixe)` faisait lire le PRÉFIXE comme
         | s'il était la clé, et ce préfixe seul ne désigne évidemment rien. Le
         | test signalait alors une clé absente là où le code est correct.
         |
         | Une clé calculée ne se vérifie pas statiquement — c'est le prix de
         | l'indirection, et il est assumé là où elle est utile. Le fichier de
         | langue correspondant est alors couvert autrement : voir
         | `OutilsTest`, qui parcourt les catégories et exige un libellé pour
         | chacune, dans les deux langues.
         */
        if (preg_match_all("/__\(\s*'([a-z0-9_]+\.[a-z0-9_.]*[a-z0-9_])'\s*[,)]/i", $source, $trouves)) {
            foreach ($trouves[1] as $cle) {
                $appels[$cle] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $fichier->getPathname());
            }
        }
    }

    return $appels;
}

describe('les clés appelées existent', function (): void {
    it("n'affiche jamais une clé brute à la place d'un texte", function (): void {
        /*
         | LE DÉFAUT QUE CE TEST EXISTE POUR ATTRAPER.
         |
         | Un lot de traduction a réécrit la barre latérale citoyenne puis s'est
         | interrompu avant d'écrire ses clés. La navigation affichait
         | `cit_citizen.mes_evaluations` en toutes lettres. La compilation
         | passait, les tests passaient, la page se rendait — parce qu'une clé
         | manquante n'est pas une erreur pour Laravel : c'est une chaîne.
         |
         | Un contrôle de parité FR/EN n'y voyait rien non plus, les deux
         | fichiers ayant exactement les mêmes clés manquantes.
         */
        $declarees = clesDeclarees('fr');

        $manquantes = [];

        foreach (clesAppeleesParLesVues() as $cle => $vue) {
            if (! array_key_exists($cle, $declarees)) {
                $manquantes[] = "{$cle}  (appelée par {$vue})";
            }
        }

        expect($manquantes)->toBe([],
            "Clés appelées par une vue mais absentes de lang/fr :\n  ".implode("\n  ", $manquantes));
    });
});

describe('le français et l’anglais se répondent', function (): void {
    it('déclare les mêmes clés dans les deux langues', function (): void {
        // Une clé présente d'un seul côté s'affiche brute dans l'autre langue.
        $fr = clesDeclarees('fr');
        $en = clesDeclarees('en');

        $ecarts = [];

        foreach (array_diff_key($fr, $en) as $cle => $_) {
            $ecarts[] = "absente de lang/en : {$cle}";
        }

        foreach (array_diff_key($en, $fr) as $cle => $_) {
            $ecarts[] = "absente de lang/fr : {$cle}";
        }

        expect($ecarts)->toBe([], "Déséquilibre entre les langues :\n  ".implode("\n  ", $ecarts));
    });

    it('traduit vraiment, au lieu de recopier le français', function (): void {
        /*
         | Une valeur anglaise IDENTIQUE à la française est le symptôme d'un
         | fichier créé pour satisfaire la parité sans avoir été traduit. La
         | parité seule ne le voit pas : les clés sont bien là des deux côtés.
         |
         | Toutes les identités ne sont pas des fautes, et les exceptions sont
         | NOMMÉES plutôt que devinées :
         |
         |   - les ligatures d'icônes Material Symbols sont des identifiants,
         |     pas des mots. Traduire `link` en `lien` afficherait un carré vide.
         |   - les noms propres — la plateforme, les membres de l'équipe, les
         |     opérateurs de paiement — ne se traduisent dans aucune langue.
         |   - certains mots s'écrivent pareil dans les deux langues.
         |
         | Le seuil de trois caractères écarte le bruit : « min », « XP », les
         | initiales de médaillon, les nombres.
         */
        $ligatures = [
            'logout', 'close', 'expand_more', 'print', 'add', 'send', 'science',
            'flag', 'bolt', 'download', 'tune', 'refresh', 'link', 'menu',
            'format_list_bulleted', 'format_list_numbered', 'picture_as_pdf', 'check_circle',
        ];

        /*
         | Des mots RÉELLEMENT identiques dans les deux langues, des noms
         | propres, et des abréviations. Chacun a été regardé un par un : y
         | forcer une traduction différente pour satisfaire ce test produirait
         | un anglais faux, ce qui est pire que pas de traduction du tout.
         |
         | La liste est explicite plutôt que devinée par une règle. Une règle
         | tolérante finirait par absorber de vrais oublis en silence, et c'est
         | exactement ce que ce test refuse.
         */
        $identiquesLegitimes = [
            'Score', 'Type', 'Date', 'Action', 'Notifications', 'Curriculum',
            'Badges', 'Verdict', 'Verdicts', 'Question', 'questions', 'Organisation',
            'Message', 'message', 'Contact', 'Module 1', 'MISSION', 'FAQ', 'minimum',
            'Live Classes', 'Live class', 'Community Fact-Checking Hub',
            'participant|participants', 'discussion|discussions',
            '/ min', '/ page', 'pt|pts', 'PREMIUM ·', '7. Contact', 'code',
            '🔔 Notifications',
            // Les separateurs portent une espace insecable REELLE, notee ici
            // en \u{A0} : ecrite « &nbsp; », l'entite ressortait telle quelle a
            // l'ecran, la valeur etant rendue par {{ }} donc deja echappee.
            "✓ Evidence \u{A0}·\u{A0} ✓ Sources \u{A0}·\u{A0} ✓ Context \u{A0}·\u{A0} ✓ Verification",
        ];

        $fr = clesDeclarees('fr');
        $en = clesDeclarees('en');

        $suspectes = [];

        foreach ($fr as $cle => $valeur) {
            if (! is_string($valeur) || ! isset($en[$cle]) || $en[$cle] !== $valeur) {
                continue;
            }

            $nu = trim(strip_tags($valeur));

            /*
             | Les noms de ligatures sont retirés AVANT le jugement, et non
             | comparés à la valeur entière : un libellé comme
             | `<span>picture_as_pdf</span> PDF` porte une icône et un acronyme,
             | donc rien de traduisible — mais la valeur entière ne ressemble à
             | aucune ligature prise isolément.
             |
             | Ce qui reste après ce retrait est la seule chose qui pourrait se
             | traduire. Si c'est vide, ou trop court, ou sans minuscule, il n'y
             | a rien à traduire et rien à signaler.
             */
            $nu = trim(str_replace($ligatures, '', $nu));
            $nu = trim(preg_replace('/\s+/', ' ', $nu) ?? $nu);

            if (mb_strlen($nu) < 4
                || in_array($valeur, $ligatures, true)
                || in_array($nu, $identiquesLegitimes, true)
                || str_contains($valeur, "Vérif'All")
                || ! preg_match('/[a-zà-ÿ]/i', $nu)) {
                continue;
            }

            // Un nom propre : deux mots capitalisés et rien d'autre.
            if (preg_match('/^(\p{Lu}[\p{L}\'’.-]*\s?){1,3}$/u', $nu)) {
                continue;
            }

            $suspectes[] = "{$cle} = ".mb_substr($nu, 0, 60);
        }

        expect($suspectes)->toBe([],
            "Valeurs anglaises identiques au français (traduction oubliée ?) :\n  ".implode("\n  ", $suspectes));
    });
});
