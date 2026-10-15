<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Purification du HTML éditorial
|--------------------------------------------------------------------------
|
| ## Pourquoi ce fichier existe
|
| La configuration par défaut du paquet autorise ceci, et rien d'autre :
|
|     div, b, strong, i, em, u, a[href|title], ul, ol, li, p[style], br,
|     span[style], img[width|height|alt|src]
|
| Ce qui veut dire qu'elle SUPPRIME, sans le dire, `h2`, `h3`, `h4`,
| `figure`, `figcaption`, `blockquote`, `table`, l'attribut `title` d'une
| image et son `loading="lazy"`.
|
| Vérifié le 25 août : un article écrit avec des sous-titres et des figures
| ressortait de la purification en UN SEUL paragraphe. Aucune erreur, aucune
| trace — le contenu passait, sa structure disparaissait.
|
| C'est doublement gênant. Pour la personne qui lit, un article de quatre mille
| mots sans respiration. Pour le référencement, la hiérarchie `h1 > h2 > h3`
| est précisément ce qu'un moteur lit pour comprendre un document — et c'est
| l'exigence explicite du cahier des charges.
|
| ## Ce qui reste interdit, et pourquoi
|
| Tout ce qui exécute ou charge : `script`, `iframe`, `object`, `embed`,
| `form`, `input`, et les attributs `on*`. HTMLPurifier les écarte par
| construction — il ne filtre pas une liste noire, il n'autorise QUE ce qui est
| nommé ici. Un attribut oublié est un attribut refusé, jamais l'inverse.
|
| `style` disparaît aussi de `p` et `span`, alors que le défaut du paquet
| l'accepte : le design system du prototype fait foi, et du style en ligne
| déposé dans un article est exactement ce qui le contourne.
|
*/

/** Les éléments autorisés dans un contenu éditorial. */
$editorial = implode(',', [
    // — structure
    'p', 'br', 'hr',
    // Pas de `h1` : il y en a UN par page, c'est le titre de l'article, et il
    // est posé par le gabarit. Deux `h1` dans un document brouillent le sujet.
    'h2', 'h3', 'h4', 'h5', 'h6',
    'ul', 'ol', 'li',
    'blockquote', 'cite',
    'figure', 'figcaption',
    'section', 'div[class]',

    // — texte
    'strong', 'b', 'em', 'i', 'u', 's', 'mark', 'small', 'sup', 'sub',
    'span[class]', 'abbr[title]', 'time[datetime]',
    'code', 'pre',

    // — liens et médias
    'a[href|title|rel|target]',
    // `title` sert l'infobulle au survol, `alt` sert l'accessibilité et Google
    // Images : les deux, et pas l'un pour l'autre.
    // `loading` permet le chargement différé — sans lui, dix images d'article
    // partent toutes en même temps sur une connexion 3G.
    'img[src|alt|title|width|height|loading|class]',

    // — tableaux
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'th[scope|colspan|rowspan]',
    'td[colspan|rowspan]', 'caption',
]);

return [
    'encoding' => 'UTF-8',
    'finput' => '',
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [
        /*
         | Profil par défaut : celui qu'emploie `clean()` sans argument, donc
         | celui qui rend les articles, les brèves du Hub et les fiches de
         | cours.
         */
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => $editorial,
            'CSS.AllowedProperties' => '',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,

            /*
             | Un lien sortant qui s'ouvre dans un nouvel onglet donne à la page
             | de destination une référence vers la nôtre, par `window.opener` :
             | elle peut nous rediriger. `rel="noopener noreferrer"` coupe ce
             | lien, et HTMLPurifier peut le poser lui-même sur tout `target`.
             */
            'HTML.TargetBlank' => true,
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,

            /*
             | Un `id` déposé dans un article peut entrer en collision avec un
             | `id` du gabarit — et casser une ancre, un libellé de formulaire
             | ou un `aria-labelledby`. On les préfixe plutôt que de les
             | interdire : les sommaires d'article en ont besoin.
             */
            'Attr.EnableID' => true,
            'Attr.IDPrefix' => 'contenu-',
        ],

        /*
         | Profil restreint, pour ce qu'écrivent les gens : messages du forum,
         | commentaires, signalements. Ni titres, ni images, ni tableaux — un
         | message n'est pas un document, et chaque élément autorisé est une
         | surface de plus.
         */
        'message' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,b,em,i,u,s,ul,ol,li,blockquote,code,a[href|rel|target]',
            'CSS.AllowedProperties' => '',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty' => true,
            'HTML.TargetBlank' => true,
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            // Un lien déposé par un inconnu ne transmet aucun crédit de
            // référencement : c'est la contrepartie normale d'un espace ouvert.
            'HTML.Nofollow' => true,
            'Attr.EnableID' => false,
        ],

        /*
         |----------------------------------------------------------------
         | Les éléments HTML5 que HTMLPurifier ne connaît pas
         |----------------------------------------------------------------
         |
         | HTMLPurifier travaille sur un doctype HTML 4.01 : `figure`,
         | `figcaption` et `time` n'y existent pas, et `loading` n'existe pas
         | sur `img`. Sans cette déclaration, il ne les refuse pas en silence —
         | il émet un avertissement PHP et les supprime, ce qui remplit les
         | journaux tout en cassant la mise en page.
         |
         | On les lui apprend. `figure` porte l'image ET sa légende ensemble :
         | c'est ce lien-là que Google Images exploite, et qu'un `div` ne dit
         | pas. `loading="lazy"` diffère le chargement des images situées plus
         | bas — sur une connexion 3G, c'est la différence entre une page qui
         | s'affiche et une page qui rame.
         |
         | `rev` s'incrémente à chaque changement de cette section : c'est ce
         | numéro qui périme la définition mise en cache.
         */
        'custom_definition' => [
            'id' => 'verifall-editorial',
            'rev' => 2,
            'debug' => false,
            'elements' => [
                // [nom, jeu de contenu, enfants admis, collection d'attributs]
                ['figure', 'Block', 'Flow', 'Common'],
                ['figcaption', 'Block', 'Flow', 'Common'],
                ['section', 'Block', 'Flow', 'Common'],
                ['time', 'Inline', 'Inline', 'Common', ['datetime' => 'Text']],
                // `mark` surligne un passage. Sur un fact-check, c'est
                // exactement le geste utile : montrer LE fragment de la citation
                // qui pose problème, plutôt que de le décrire.
                ['mark', 'Inline', 'Inline', 'Common'],
            ],
            'attributes' => [
                // [élément, attribut, définition]
                ['img', 'loading', 'Enum#lazy,eager'],
            ],
        ],

        /*
         | Titres seuls : là où l'on veut de l'emphase et rien de plus.
         */
        'titre' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'strong,b,em,i,br',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
        ],
    ],
];
