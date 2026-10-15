<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Une classe absente du build figé est INERTE, et rien ne le dit
|--------------------------------------------------------------------------
| `public/assets/css/tailwind.css` est un build STATIQUE de 48 Ko, repris du
| prototype et jamais regénéré : il n'y a ni `package-lock.json`, ni
| `node_modules/`, et aucun gabarit n'appelle `@vite`. Écrire une classe que ce
| fichier ne contient pas ne produit ni erreur, ni avertissement, ni test rouge.
| Le style ne s'applique simplement pas.
|
| C'est le défaut le plus coûteux du chantier. Il a été trouvé trois fois de
| suite EN REGARDANT, jamais en exécutant : sept classes absentes au lot 4, et
| au lot 5 la découverte que `st-icon-btn` — utilisé dans six écrans — n'existe
| pas (la vraie classe est `st-icnbtn`), et que `min-w-[220px]` n'existait nulle
| part. Tous les boutons d'action du back-office étaient sans style.
|
| ## Un cliquet, pas un mur
|
| Douze vues portaient des classes inertes héritées quand ce test a été écrit
| (lot 5). Les corriger toutes d'un coup aurait mêlé des chantiers sans rapport.
|
| Ce test fige donc l'existant dans `detteConnue()` et refuse TOUT AJOUT. Le
| jour où une vue est nettoyée, sa ligne quitte la liste et ne peut plus
| revenir. La dette ne grandit pas ; elle se rembourse.
|
| **Le cliquet a servi.** `parametres.blade.php`, la plus lourde des douze avec
| ses neuf classes, est sortie de la liste au lot 10 : elle portait la mise en
| page en onglets latéraux demandée par le porteur du projet, et cette mise en
| page ne fonctionnait pas. Onze vues restent.
*/

/**
 * Les feuilles réellement chargées par les gabarits.
 */
function feuillesChargees(): string
{
    static $css = null;

    if ($css === null) {
        $css = '';
        /*
         | `verifall-app` a été ajouté le 25 septembre 2026 : le gabarit
         | citoyen le charge (`layouts/citizen.blade.php`, l. 34) au même titre
         | que `citizen.css`, et l'omettre faisait ressortir « absentes » des
         | classes qui s'appliquent bel et bien.
         */
        foreach (['tailwind', 'staff', 'staff-shell', 'citizen', 'verifall-app'] as $nom) {
            $chemin = public_path('assets/css/'.$nom.'.css');
            if (is_file($chemin)) {
                $css .= (string) file_get_contents($chemin)."\n";
            }
        }
    }

    return $css;
}

/**
 * Les classes écrites dans une vue.
 *
 * Le découpage est le point délicat. Un attribut `class` de Blade mêle trois
 * choses : des classes nues, des expressions `{{ }}`, et — dans ces
 * expressions — des classes en littéral de chaîne, qui comptent tout autant
 * (`{{ $x ? 'text-[#059669]' : 'text-[#DC2626]' }}`).
 *
 * On extrait donc les littéraux des expressions, puis on retire les expressions
 * avant de découper le reste. Sans cette précaution, les NOMS DE VARIABLES des
 * ternaires ressortiraient comme des classes manquantes et noieraient le
 * signal.
 *
 * @return list<string>
 */
function classesDeLaVue(string $chemin): array
{
    $source = (string) file_get_contents($chemin);
    $trouvees = [];

    /*
     | `(?<![-:\w])` écarte `x-bind:class` et `:class`, qui portent du JavaScript
     | Alpine et non des classes CSS. Sans cette garde, `x-bind:class="ouvert &&
     | 'is-open'"` ferait de la VARIABLE `ouvert` une classe manquante.
     */
    preg_match_all('/(?<![-:\w])class="([^"]*)"/s', $source, $attributs);

    foreach ($attributs[1] as $valeur) {
        /*
         | 1. Les littéraux des expressions Blade — mais SEULEMENT ceux qui
         |    suivent un `?` ou un `:`, c'est-à-dire les BRANCHES d'un ternaire.
         |
         |    Prendre tous les littéraux ramènerait aussi les opérandes de
         |    comparaison : `{{ $onglet === 'abonnes' ? 'is-active' : '' }}`
         |    ferait de « abonnes » une classe manquante. Ce bruit-là noie le
         |    signal — il a produit cinq faux positifs sur trois vues au premier
         |    essai, pour zéro défaut réel.
         */
        if (preg_match_all('/\{\{.*?\}\}/s', $valeur, $expressions) > 0) {
            foreach ($expressions[0] as $expression) {
                preg_match_all("/[?:]\s*'([^']*)'/", $expression, $litteraux);
                foreach ($litteraux[1] as $litteral) {
                    foreach (preg_split('/\s+/', trim($litteral)) ?: [] as $c) {
                        if ($c !== '') {
                            $trouvees[] = $c;
                        }
                    }
                }
            }
        }

        // 2. Le reste, expressions retirées.
        $nu = preg_replace('/\{\{.*?\}\}|@\w+(\([^)]*\))?/s', ' ', $valeur) ?? '';

        foreach (preg_split('/\s+/', trim($nu)) ?: [] as $c) {
            if ($c !== '' && preg_match('/^[a-zA-Z!]/', $c) === 1) {
                $trouvees[] = $c;
            }
        }
    }

    /*
     | 3. Les classes rangées dans un TABLEAU PHP en tête de vue.
     |
     | Ajouté le 25 septembre 2026, et c'est le trou par lequel le défaut le
     | plus coûteux du rapport citoyen est passé. `rapport.blade.php` composait
     | son thème de verdict ainsi :
     |
     |     $theme = match (true) {
     |         … => ['bg' => 'bg-red-50/80', 'badge_bg' => 'bg-red-600', …],
     |     };
     |
     | puis l'appliquait par `class="{{ $theme['bg'] }}"`. Les DIX-HUIT classes
     | du tableau ne sont écrites dans aucun attribut `class` : l'extracteur
     | ci-dessus n'en voyait pas une seule, et aucune n'existait au build. Le
     | Hero du rapport s'affichait sans fond, sans bordure et sans badge
     | coloré, sans qu'aucun test ne puisse le dire.
     |
     | Le filtre est volontairement ÉTROIT, pour ne pas confondre une classe
     | avec un libellé, une clé de tableau ou un fragment concaténé :
     |   · le littéral doit être une VALEUR de tableau, c'est-à-dire suivre un
     |     `=>`. Sans cette garde, `$identifiant = 'reglage-'.$reglage->cle` de
     |     `parametres.blade.php` — un préfixe d'identifiant DOM — ressortait
     |     comme une classe manquante ;
     |   · tout jeton doit commencer par une minuscule, un `!` ou un `[` —
     |     ce qui écarte « INFORMATION FAUSSE » et « Ce contenu a été… » ;
     |   · au moins un jeton doit porter un `-`, un `:` ou un `[` — ce qui
     |     écarte « cancel », « check_circle » et « staff.faq ».
     */
    if (preg_match_all('/@php\b(.*?)@endphp/s', $source, $blocs) > 0) {
        foreach ($blocs[1] as $bloc) {
            preg_match_all("/=>\s*'([^'\\\\]*)'/", $bloc, $litteraux);

            foreach ($litteraux[1] as $litteral) {
                $jetons = preg_split('/\s+/', trim($litteral)) ?: [];

                if ($jetons === [] || $litteral === '') {
                    continue;
                }

                $plausibles = array_filter(
                    $jetons,
                    static fn (string $j): bool => preg_match('/^[a-z!\[]/', $j) === 1,
                );

                if (count($plausibles) !== count($jetons)) {
                    continue;
                }

                $porteUnSeparateur = array_filter(
                    $jetons,
                    static fn (string $j): bool => preg_match('/[-:\[]/', $j) === 1,
                );

                if ($porteUnSeparateur === []) {
                    continue;
                }

                foreach ($jetons as $jeton) {
                    $trouvees[] = $jeton;
                }
            }
        }
    }

    return array_values(array_unique($trouvees));
}

/**
 * Les feuilles de PAGE que cette vue charge elle-même.
 *
 * Ajouté à la refonte du tableau de bord (14 septembre 2026). Une grille
 * responsive nouvelle ne peut pas s'écrire dans le build figé ; elle s'écrit
 * dans une feuille `assets/css/pages/*.css`, que la vue pousse dans
 * `@stack('styles')`. Sans cette fonction, chaque classe de cette feuille
 * ressortirait « absente » alors qu'elle s'applique.
 *
 * La portée reste ÉTROITE, pour ne pas vider le contrôle : une classe n'est
 * acceptée que si la feuille qui la définit est chargée par la vue elle-même,
 * ou — pour une sous-vue rangée dans `staff/<écran>/` — par `staff/<écran>.blade.php`.
 * Une feuille chargée par un AUTRE écran ne compte pas.
 */
function feuillesDeLaVue(string $chemin): string
{
    static $memo = [];

    $vues = [$chemin];
    $dossier = dirname($chemin);

    if (realpath($dossier) !== realpath(resource_path('views/livewire/staff'))) {
        $vues[] = $dossier.'.blade.php';
    }

    $cle = implode('|', $vues);

    if (! isset($memo[$cle])) {
        $css = '';

        foreach ($vues as $vue) {
            if (! is_file($vue)) {
                continue;
            }

            preg_match_all('#assets/css/(pages/[\w.-]+\.css)#', (string) file_get_contents($vue), $feuilles);

            foreach (array_unique($feuilles[1]) as $feuille) {
                $fichier = public_path('assets/css/'.$feuille);

                if (is_file($fichier)) {
                    $css .= (string) file_get_contents($fichier)."\n";
                }
            }
        }

        $memo[$cle] = $css;
    }

    return $memo[$cle];
}

/**
 * La classe existe-t-elle dans le CSS chargé ?
 *
 * Tailwind ÉCHAPPE les valeurs arbitraires dans le CSS produit :
 * `text-[#64748B]` s'y écrit `.text-\[\#64748B\]`. Chercher la forme non
 * échappée ne trouve jamais rien et déclarerait tout absent — c'est le premier
 * piège de ce contrôle, et il fait croire à une catastrophe inexistante.
 */
function classePresente(string $classe, string $cssDeLaPage = ''): bool
{
    $motif = '';

    foreach (mb_str_split($classe) as $caractere) {
        $motif .= str_contains('[]#().%!,/:', $caractere)
            // L'antislash d'échappement est OPTIONNEL : Tailwind n'échappe pas
            // tous les caractères dans tous les contextes.
            ? '\\\\?'.preg_quote($caractere, '/')
            : preg_quote($caractere, '/');
    }

    return preg_match('/\.'.$motif.'(?![\w-])/', feuillesChargees()."\n".$cssDeLaPage) === 1;
}

/**
 * L'existant, figé au 31 août 2026 (lot 5).
 *
 * CETTE LISTE NE DOIT QUE RÉTRÉCIR. Une entrée retirée est une vue nettoyée ;
 * une entrée ajoutée est une régression qu'on aurait dû corriger plutôt que
 * déclarer.
 *
 * @return array<string, list<string>>
 */
function detteConnue(): array
{
    return [
        'faits-verifies.blade.php' => ['break-words', 'prose-lesson'],
        'gabarit-certificat.blade.php' => ['list-disc'],
        /*
         | `infolettre.blade.php` est sortie le 14 septembre 2026 : ses bordures
         | d'alerte colorées et son `!min-h-[36px]` étaient inertes. Les
         | premières vivent dans `pages/staff-infolettre.css`, le second a
         | disparu — le bouton garde les 44 px de `.st-btn`.
         */
        'messages-contact.blade.php' => ['bg-[#F8FAFC]'],
        'mots-surveilles.blade.php' => ['tabular-nums'],
        'opportunites.blade.php' => ['text-[#B45309]'],
        'packs-unites.blade.php' => ['!min-h-[36px]'],
        /*
         | `parametres.blade.php` PORTAIT NEUF CLASSES INERTES — celles de la
         | mise en page en onglets latéraux collants demandée par le porteur du
         | projet. Aucune n'existait au build : le panneau ne collait pas, la
         | grille à deux colonnes ne se formait pas. L'écran avait été écrit
         | contre un Tailwind qui n'est pas là.
         |
         | Corrigé au lot 10, et la correction méritait d'être comprise : il n'y
         | avait pas d'utilitaire équivalent au build, et il n'y en aurait jamais.
         | La règle a été écrite dans `staff.css` — du CSS de projet, écrit à la
         | main, qui PEUT grandir. C'est `tailwind.css` qui est gelé, pas la
         | feuille du back-office.
         |
         | Sa ligne a donc quitté cette liste. Elle ne doit pas y revenir.
         */
        'parrainages.blade.php' => ['tabular-nums'],
        'signalements-contenu.blade.php' => ['bg-[#F8FAFC]'],
        'utilisateurs.blade.php' => ['text-[#8A5A0B]'],
    ];
}

it('n\'introduit AUCUNE classe absente du build figé', function (): void {
    $dette = detteConnue();
    $nouvelles = [];

    $fichiers = Finder::create()
        ->files()
        ->in(resource_path('views/livewire/staff'))
        ->name('*.blade.php');

    foreach ($fichiers as $fichier) {
        $nom = $fichier->getFilename();
        $tolerees = $dette[$nom] ?? [];

        $cssDeLaPage = feuillesDeLaVue($fichier->getPathname());

        foreach (classesDeLaVue($fichier->getPathname()) as $classe) {
            if (in_array($classe, $tolerees, true) || classePresente($classe, $cssDeLaPage)) {
                continue;
            }

            $nouvelles[$nom][] = $classe;
        }
    }

    expect($nouvelles)->toBe([], "Classes absentes de public/assets/css/*.css :\n"
        .json_encode($nouvelles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ."\n\nLe build Tailwind est FIGÉ : ces classes n'ont aucun effet. "
        .'Transposez avec une classe qui existe déjà, ou ajoutez la règle à staff.css.');
});

it('ne laisse pas la dette connue devenir périmée', function (): void {
    /*
     | Le pendant du cliquet. Une entrée de `detteConnue()` qui ne correspond
     | plus à rien — vue supprimée, classe corrigée, ou classe ajoutée depuis au
     | CSS — doit SORTIR de la liste.
     |
     | Sans ce second test, la liste se fossiliserait : elle tolérerait pour
     | toujours des classes que plus personne n'écrit, et le premier test
     | cesserait silencieusement de protéger ces vues-là.
     */
    $perimees = [];

    foreach (detteConnue() as $nom => $classes) {
        $chemin = resource_path('views/livewire/staff/'.$nom);

        if (! is_file($chemin)) {
            $perimees[$nom] = 'la vue n\'existe plus';

            continue;
        }

        $utilisees = classesDeLaVue($chemin);

        foreach ($classes as $classe) {
            if (! in_array($classe, $utilisees, true)) {
                $perimees[$nom][] = $classe.' — plus utilisée';
            } elseif (classePresente($classe)) {
                $perimees[$nom][] = $classe.' — existe désormais au CSS';
            }
        }
    }

    expect($perimees)->toBe([], "Entrées à retirer de detteConnue() :\n"
        .json_encode($perimees, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
});

/*
|--------------------------------------------------------------------------
| Le même cliquet, pour l'espace CITOYEN
|--------------------------------------------------------------------------
|
| Le contrôle ci-dessus ne balayait que `views/livewire/staff`. C'est
| précisément pour cela que `rapport.blade.php` a pu accumuler des dizaines de
| classes inertes sans qu'une seule barre ne passe au rouge : son Hero de
| verdict, sa grille à deux colonnes et sa mise en forme Markdown étaient
| écrits contre un Tailwind qui n'est pas là.
|
| Deux listes SÉPARÉES plutôt qu'une, parce que six noms de gabarits existent
| des deux côtés — `cloche`, `cours`, `live-classes`, `outils`,
| `portails-officiels`, `tableau-de-bord`. Une liste unique indexée par nom de
| fichier les confondrait, et une tolérance posée pour l'un s'appliquerait en
| silence à l'autre.
|
| MÊME RÈGLE : cette liste ne doit que RÉTRÉCIR.
*/

/**
 * @return array<string, list<string>>
 */
function detteConnueCitoyen(): array
{
    return [
        /*
         | Relevé au 25 septembre 2026, LISTE DE TOLÉRANCE VIDÉE au préalable.
         |
         | La précaution mérite d'être dite, parce que l'omettre a produit un
         | relevé faux une heure plus tôt : mesurer pendant qu'une tolérance est
         | en vigueur ne donne pas les classes inertes, il donne celles qui ne
         | sont pas encore tolérées. Deux vues manquaient à l'appel.
         |
         | `rapport.blade.php` N'Y FIGURE PAS, et ne doit jamais y revenir : ses
         | quarante-neuf classes inertes — dont les DIX-HUIT du tableau `$theme`,
         | invisibles pour l'extracteur jusqu'au 25 septembre — ont été
         | corrigées au lot 1.
         */
        'article-hub.blade.php' => [
            'prose-lesson',
        ],
        'cours.blade.php' => [
            'disabled:opacity-50', 'first:border-0', 'first:pt-0',
        ],
        'forum.blade.php' => [
            'disabled:opacity-50', 'lg:grid-cols-[minmax(0,1fr)_320px]',
        ],
        'lecon.blade.php' => [
            'p-0', 'prose-lesson',
        ],
        'mes-tutoriels.blade.php' => [
            'no-scrollbar',
        ],
        'mon-parrainage.blade.php' => [
            'sm:mt-0.5',
        ],
        'notes-du-sujet.blade.php' => [
            'disabled:opacity-50', 'is-off', 'vf-note', 'vf-note--attente', 'vf-note--ecartee',
            'vf-note--publiee', 'vf-note-sources', 'vf-note-texte', 'vf-note-vote',
            'vf-reputation',
        ],
        'passer-quiz.blade.php' => [
            'disabled:opacity-50', 'first:border-0', 'first:pt-0', 'pl-6', 'text-vdoubt',
        ],
        'profil.blade.php' => [
            'tracking-[0.4em]',
        ],
        'recompenses.blade.php' => [
            'gap-x-6', 'pb-1.5',
        ],
        'rediffusion.blade.php' => [
            'text-vdoubt',
        ],
        'reglages.blade.php' => [
            'first:pt-0', 'last:mb-0', 'p-0',
        ],
        'sondage-du-sujet.blade.php' => [
            'is-mienne', 'vf-sondage-libelle', 'vf-sondage-ligne', 'vf-sondage-option',
            'vf-sondage-part', 'vf-sondage-resultat',
        ],
        'sujet.blade.php' => [
            'disabled:opacity-50', 'min-w-[200px]', 'prose-lesson',
        ],
        'unites.blade.php' => [
            'animate-spin', 'pack-btn',
        ],
        'verification-en-cours.blade.php' => [
            'animate-[pulse_1.5s_infinite]', 'animate-spin', 'bg-slate-100', 'bg-white/25',
            'duration-500', 'ease-out', 'shadow-inner',
        ],
        'verifier-mon-identite.blade.php' => [
            'p-0',
        ],
        'verifier.blade.php' => [
            '!text-[32px]', 'animate-spin', 'badge-cost', 'min-w-[240px]',
        ],
    ];
}

it('n\'introduit AUCUNE classe absente du build figé — espace citoyen', function (): void {
    $dette = detteConnueCitoyen();
    $nouvelles = [];

    $fichiers = Finder::create()
        ->files()
        ->in(resource_path('views/livewire/citizen'))
        ->name('*.blade.php');

    foreach ($fichiers as $fichier) {
        $nom = $fichier->getFilename();
        $tolerees = $dette[$nom] ?? [];

        $cssDeLaPage = feuillesDeLaVue($fichier->getPathname());

        foreach (classesDeLaVue($fichier->getPathname()) as $classe) {
            if (in_array($classe, $tolerees, true) || classePresente($classe, $cssDeLaPage)) {
                continue;
            }

            $nouvelles[$nom][] = $classe;
        }
    }

    expect($nouvelles)->toBe([], "Classes absentes de public/assets/css/*.css :\n"
        .json_encode($nouvelles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ."\n\nLe build Tailwind est FIGÉ : ces classes n'ont aucun effet. "
        .'Transposez avec une classe qui existe déjà, ou ajoutez la règle à citizen.css '
        .'ou à une feuille assets/css/pages/*.css que la vue pousse elle-même.');
});

it('ne laisse pas la dette citoyenne devenir périmée', function (): void {
    $perimees = [];

    foreach (detteConnueCitoyen() as $nom => $classes) {
        $chemin = resource_path('views/livewire/citizen/'.$nom);

        if (! is_file($chemin)) {
            $perimees[$nom] = 'la vue n\'existe plus';

            continue;
        }

        $utilisees = classesDeLaVue($chemin);
        $cssDeLaPage = feuillesDeLaVue($chemin);

        foreach ($classes as $classe) {
            if (! in_array($classe, $utilisees, true)) {
                $perimees[$nom][] = $classe.' — plus utilisée';
            } elseif (classePresente($classe, $cssDeLaPage)) {
                $perimees[$nom][] = $classe.' — existe désormais au CSS';
            }
        }
    }

    expect($perimees)->toBe([], "Entrées à retirer de detteConnueCitoyen() :\n"
        .json_encode($perimees, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
});

/*
|--------------------------------------------------------------------------
| La surface du RAPPORT — aucune tolérance
|--------------------------------------------------------------------------
|
| Les deux contrôles ci-dessus balayent `views/livewire/staff` et
| `views/livewire/citizen`. Ni l'un ni l'autre ne voit `views/components` ni
| `views/livewire/public` — or le corps du rapport et le menu de partage
| vivent là, et la page publique aussi.
|
| Ces quatre fichiers ont été écrits aux lots 1, 3, 9 et 10, contre une
| feuille qu'on venait d'écrire. Ils n'ont donc AUCUNE dette héritée, et pas
| de liste de tolérance : toute classe absente est une faute du jour même.
| Le contrôle a d'ailleurs servi tout de suite — il a relevé `vf-print-marque`
| et `vf-ico-courriel`, employées et jamais déclarées, et `hover:bg-surface`,
| écrite par habitude et sans effet.
|
| `pages/rapport.css` compte, puisque les deux gabarits la poussent eux-mêmes
| dans `@stack('styles')`.
*/
it('ne laisse AUCUNE classe inerte sur la surface du rapport', function (): void {
    $fichiers = [
        'views/components/rapport/corps.blade.php',
        'views/components/menu-partage.blade.php',
        'views/livewire/public/rapport-public.blade.php',
        'views/livewire/citizen/rapport.blade.php',
    ];

    $feuille = (string) file_get_contents(public_path('assets/css/pages/rapport.css'));
    $manquantes = [];

    foreach ($fichiers as $relatif) {
        $chemin = resource_path($relatif);

        expect($chemin)->toBeFile();

        foreach (classesDeLaVue($chemin) as $classe) {
            if (! classePresente($classe, $feuille)) {
                $manquantes[basename($relatif)][] = $classe;
            }
        }
    }

    expect($manquantes)->toBe([], "Classes sans règle CSS :\n"
        .json_encode($manquantes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ."\n\nDéclarez-les dans public/assets/css/pages/rapport.css — la feuille de page PEUT "
        .'grandir, contrairement au build figé — ou employez une classe qui existe déjà.');
});
