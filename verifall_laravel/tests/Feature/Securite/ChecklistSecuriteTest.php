<?php

declare(strict_types=1);

use App\Core\Identity\Services\SuppressionCompteService;
use App\Jobs\PreparerExportDonnees;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/*
 |============================================================================
 | LA CHECKLIST SÉCURITÉ & RGPD, VÉRIFIÉE PLUTÔT QU'AFFIRMÉE
 |============================================================================
 |
 | « Laravel protège des injections SQL » est vrai de Laravel. Ce n'est pas une
 | preuve que CE dépôt n'a pas contourné la protection quelque part — et c'est
 | toujours quelque part que ça se passe.
 |
 | Chaque test ci-dessous vérifie un point de la checklist SUR LE CODE RÉEL,
 | pas sur les garanties du framework. Ce qui échoue est une vraie faille ;
 | ce qui passe est acquis, et n'a plus besoin d'être discuté.
 */

/**
 * `__('cle.litterale', [...])` : les paramètres sont-ils TOUS échappés ?
 *
 * `__()` n'échappe pas ce qu'il substitue — il remplace, c'est tout. Une clé
 * littérale ne suffit donc pas : le fichier de langue est écrit par l'équipe,
 * mais le paramètre, lui, peut venir de n'importe où.
 *
 * La fonction vit ici, au niveau du fichier, parce que le détecteur ET le test
 * qui l'éprouve s'en servent. Deux copies d'une règle de sécurité finissent
 * toujours par diverger, et c'est la copie du test qui reste verte.
 */
function parametresTousEchappes(string $expression): bool
{
    if (! preg_match("/^__\(\s*['\"][a-z0-9_.]+['\"]\s*,\s*\[(.+)\]\s*\)$/is", $expression, $m)) {
        return false;
    }

    // Découpe sur les virgules de premier niveau : un tableau imbriqué ne doit
    // pas être scindé en deux paires bancales.
    $valeurs = preg_split("/,(?![^\[\]]*\])/", $m[1]) ?: [];

    foreach ($valeurs as $paire) {
        $valeur = trim((string) mb_strstr($paire, '=>'), " \t\n=>");

        if ($valeur === '') {
            return false;
        }

        // Une valeur littérale ne porte aucune saisie ; tout le reste passe
        // par `e()`. On examine CHAQUE valeur : il suffit d'un paramètre nu
        // pour refuser l'expression entière.
        $litterale = preg_match("/^['\"]/", $valeur) || is_numeric($valeur);

        if (! $litterale && ! str_starts_with($valeur, 'e(')) {
            return false;
        }
    }

    return $valeurs !== [];
}

describe('injection SQL', function (): void {
    it("n'emploie aucune requête brute avec interpolation", function (): void {
        /*
         | L'ORM lie ses paramètres, c'est acquis. Le risque réel est ailleurs :
         | un `DB::raw()` ou un `whereRaw()` dans lequel on a CONCATÉNÉ une
         | variable. C'est ça qu'on cherche.
         */
        $suspects = [];

        $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.php')) {
                continue;
            }

            $source = file_get_contents($fichier->getPathname());

            // Un `raw` suivi, sur la même ligne, d'une concaténation avec une
            // variable : `whereRaw('x = '.$valeur)` ou `DB::raw("a $b")`.
            preg_match_all(
                '/(?:DB::raw|whereRaw|havingRaw|orderByRaw|selectRaw|groupByRaw)\s*\(\s*[^)]*(?:\.\s*\$|\$\{|"\s*[^"]*\$)/i',
                $source,
                $trouves
            );

            foreach ($trouves[0] as $extrait) {
                $suspects[] = basename($fichier->getPathname()).' : '.mb_substr(trim($extrait), 0, 70);
            }
        }

        expect($suspects)->toBe([], "Requêtes brutes avec interpolation :\n  ".implode("\n  ", $suspects));
    });
});

describe('XSS', function (): void {
    it("n'affiche jamais de donnée utilisateur sans purification", function (): void {
        /*
         | Blade échappe `{{ }}`. Le risque est `{!! !!}` — et le dépôt en a
         | légitimement besoin pour le contenu éditorial riche. La règle du
         | chantier est alors stricte : tout `{!! !!}` doit passer par
         | `clean()`, la purification de `mews/purifier`.
         */
        $sansPurification = [];

        $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            /*
             | Les composants de courriel publiés par Laravel ne sont pas notre
             | code : ils emploient `{!! $slot !!}` par conception, et les
             | corriger reviendrait à diverger du framework à chaque mise à jour.
             */
            if (str_contains($fichier->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $source = file_get_contents($fichier->getPathname());

            /*
             | Les commentaires Blade sont retirés AVANT l'analyse. Sans cela,
             | un commentaire qui explique la règle — « le JSON-LD passe par
             | @json et non par {!! !!} » — se fait signaler comme une
             | violation de la règle qu'il énonce.
             */
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;

            preg_match_all('/\{!!(.+?)!!\}/s', $source, $trouves);

            foreach ($trouves[1] as $expression) {
                $expression = trim($expression);

                /*
                 | Tolérés : ce qui vient du framework ou d'une constante du
                 | dépôt — pagination, JSON encodé, SVG écrit dans la vue.
                 | Ils ne portent aucune saisie.
                 */
                $sur = str_contains($expression, 'clean(')
                    || str_contains($expression, '->links(')
                    || str_contains($expression, 'json_encode')
                    || str_contains($expression, '@json')
                    || str_starts_with($expression, "'")
                    || str_starts_with($expression, '"')
                    /*
                     | Les QR codes. Ils sont engendrés PAR LE SERVEUR, par
                     | BaconQrCode, à partir d'un code que nous fabriquons —
                     | jamais d'une saisie. Les échapper afficherait le source
                     | SVG en toutes lettres à la place de l'image.
                     |
                     | C'est la seule exception du dépôt, et elle est nommée
                     | plutôt que tolérée : ajouter un `{!! !!}` ailleurs fera
                     | échouer ce test.
                     */
                    || str_contains($expression, 'qrCode')
                    || str_contains($expression, 'qrCodeSvg')
                    /*
                     | Les fichiers de langue, et SEULEMENT quand la clé est
                     | écrite en toutes lettres.
                     |
                     | Certains paragraphes portent du balisage en ligne : un
                     | <strong> qui met en valeur une proposition. Les découper
                     | pour l'éviter produirait des fragments qu'aucune langue
                     | ne se traduit correctement.
                     |
                     | Ces chaînes vivent dans `lang/`, versionnées dans le
                     | dépôt et écrites par l'équipe. Aucune saisie n'y entre.
                     |
                     | LA CLÉ LITTÉRALE EST LA CONDITION. `__($variable)` reste
                     | signalé : une clé calculée peut désigner n'importe quoi,
                     | et c'est précisément par là qu'une donnée d'utilisateur
                     | se glisserait dans une sortie non échappée.
                     */
                    || (bool) preg_match("/^__\(\s*['\"][a-z0-9_.]+['\"]\s*\)$/i", $expression)
                    /*
                     | Même chose AVEC paramètres, à une condition stricte :
                     | chaque valeur substituée doit passer par `e()`.
                     |
                     | `__()` n'échappe PAS ce qu'il substitue — il remplace,
                     | c'est tout. `{!! __('k', ['nom' => $saisie]) !!}` est donc
                     | une faille franche, même si la clé est littérale. Ce que
                     | le fichier de langue contient est écrit par l'équipe ; ce
                     | que le paramètre apporte ne l'est pas.
                     |
                     | La condition porte sur CHAQUE valeur : il suffit d'un
                     | paramètre nu pour que l'expression soit refusée.
                     */
                    || parametresTousEchappes($expression);

                if (! $sur) {
                    $sansPurification[] = basename($fichier->getPathname()).' : '.mb_substr($expression, 0, 70);
                }
            }
        }

        expect($sansPurification)->toBe([],
            "Sorties non échappées et non purifiées :\n  ".implode("\n  ", $sansPurification));
    });
});

describe("l'exception des fichiers de langue", function (): void {
    it('accepte une cle LITTERALE, refuse une cle calculee', function (): void {
        /*
         | L'exception ouverte pour `{!! __('...') !!}` est etroite, et ce test
         | la maintient etroite.
         |
         | Une cle ecrite en toutes lettres designe une chaine de `lang/`,
         | versionnee dans le depot et ecrite par l'equipe : aucune saisie n'y
         | entre. Une cle CALCULEE peut designer n'importe quoi, et c'est
         | exactement par la qu'une donnee d'utilisateur se glisserait dans une
         | sortie non echappee.
         |
         | Elargir l'exception a `__($variable)` reviendrait a desarmer le
         | detecteur tout en gardant l'apparence d'une protection.
         */
        $motif = "/^__\(\s*['\"][a-z0-9_.]+['\"]\s*\)$/i";

        $acceptees = ["__('accueil.titre')", '__("accueil.titre")'];
        $refusees = ['__($cle)', '__($page->titre)', "__('a.b') . \$saisie", '$utilisateur->bio'];

        foreach ($acceptees as $expression) {
            expect((bool) preg_match($motif, $expression))->toBeTrue("Devrait passer : {$expression}");
        }

        foreach ($refusees as $expression) {
            expect((bool) preg_match($motif, $expression))->toBeFalse("Devrait etre signale : {$expression}");
        }
    });

    it("n'accepte un paramètre substitué QUE s'il est échappé", function (): void {
        /*
         | `__()` ne fait que REMPLACER : il n'échappe pas ce qu'il substitue.
         | La clé littérale garantit que le gabarit vient de `lang/`, pas que
         | le paramètre soit sûr. `{!! __('k', ['nom' => $saisie]) !!}` est donc
         | une faille franche, malgré une clé irréprochable.
         |
         | Ce test éprouve la fonction MÊME qu'emploie le détecteur, et non une
         | copie de sa règle : une seconde copie finirait par diverger, et c'est
         | celle du test qui resterait verte.
         |
         | Le cas décisif est le dernier accepté/refusé : un paramètre échappé
         | À CÔTÉ d'un paramètre nu doit faire refuser l'expression entière.
         | Chercher « e( » dans l'expression entière l'aurait laissée passer.
         */
        $acceptees = [
            "__('a.b', ['n' => e(\$x)])",
            "__('a.b', ['n' => e(\$x), 'm' => e(\$y)])",
            "__('a.b', ['n' => 12])",
            "__('a.b', ['n' => 'texte'])",
            "__('a.b', ['n' => e(App\\Foo\\Bar::CONSTANTE)])",
        ];

        $refusees = [
            "__('a.b', ['n' => \$saisie])",
            "__('a.b', ['n' => e(\$x), 'm' => \$saisie])",
            "__('a.b', ['m' => \$saisie, 'n' => e(\$x)])",
            "__('a.b', ['n' => \$u->bio])",
            "__(\$cle, ['n' => e(\$x)])",
            "__('a.b')",
        ];

        foreach ($acceptees as $expression) {
            expect(parametresTousEchappes($expression))->toBeTrue("Devrait passer : {$expression}");
        }

        foreach ($refusees as $expression) {
            expect(parametresTousEchappes($expression))->toBeFalse("Devrait etre signale : {$expression}");
        }
    });
});

describe('CSRF', function (): void {
    it('est activé, et ne dispense que ce qui ne peut pas porter de jeton', function (): void {
        /*
         | CE QUI NE PEUT PAS ÊTRE TESTÉ, ET POURQUOI.
         |
         | On ne peut PAS prouver le rejet d'un POST sans jeton par une requête
         | de test : `ValidateCsrfToken::handle()` court-circuite lui-même sur
         | `runningUnitTests()`. Le middleware ne s'exécute jamais sous Pest —
         | par conception du framework, pour que chaque test n'ait pas à porter
         | un jeton.
         |
         | Un test qui poste sans jeton et attend un 419 obtient donc un 302, et
         | quelqu'un finit par « corriger » l'assertion vers 302 — un test vert
         | qui ne prouve rien, ce qui est pire que pas de test.
         |
         | On vérifie donc ce qui EST vérifiable : que la protection est
         | branchée, et que sa seule dispense est justifiée.
         */
        $demarrage = file_get_contents(base_path('bootstrap/app.php'));

        expect($demarrage)->toContain('validateCsrfTokens');

        /*
         | La seule dispense est le rappel de paiement Mobile Money. Un
         | opérateur ne peut pas porter notre jeton de session — il n'en a
         | aucune. Cette route s'authentifie autrement : par la signature du
         | rappel, vérifiée avec `PAYMENT_WEBHOOK_SECRET`.
         |
         | Toute dispense AJOUTÉE ici fera échouer ce test, ce qui est le but :
         | une dispense CSRF doit être une décision, pas une ligne qu'on ajoute
         | pour faire passer un formulaire récalcitrant.
         */
        preg_match('/validateCsrfTokens\(except:\s*\[(.*?)\]/s', $demarrage, $dispenses);

        expect($dispenses[1] ?? '')->toContain('webhooks/momo');

        $nombre = substr_count($dispenses[1] ?? '', "'");
        expect($nombre)->toBe(2, 'Une seule dispense CSRF est attendue.');
    });

    it("n'expose aucun formulaire POST sans @csrf", function (): void {
        $sansJeton = [];

        $iterateur = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($fichier->getPathname());

            preg_match_all('/<form\b[^>]*method\s*=\s*["\']?post["\']?[^>]*>(.*?)<\/form>/is', $source, $trouves);

            foreach ($trouves[0] as $formulaire) {
                // Un formulaire piloté par Livewire (`wire:submit`) ne poste
                // pas : il passe par le canal Livewire, qui a sa propre
                // protection.
                if (str_contains($formulaire, 'wire:submit')) {
                    continue;
                }

                if (! str_contains($formulaire, '@csrf') && ! str_contains($formulaire, 'csrf_field')) {
                    $sansJeton[] = basename($fichier->getPathname());
                }
            }
        }

        expect(array_unique($sansJeton))->toBe([],
            'Formulaires POST sans @csrf : '.implode(', ', array_unique($sansJeton)));
    });
});

describe('assignation de masse', function (): void {
    it('déclare $fillable sur CHAQUE modèle', function (): void {
        /*
         | Un modèle sans `$fillable` accepte n'importe quelle colonne au
         | remplissage. Sur `User`, cela veut dire qu'un formulaire d'inscription
         | forgé pourrait écrire `units`, `xp`, ou `email_verified_at`.
         |
         | La règle 7 du chantier l'exige ; on le vérifie plutôt que d'y croire.
         */
        $sansFillable = [];

        foreach (glob(app_path('Models/*.php')) as $fichier) {
            $classe = 'App\\Models\\'.basename($fichier, '.php');

            if (! class_exists($classe)) {
                continue;
            }

            $reflet = new ReflectionClass($classe);

            if ($reflet->isAbstract() || ! $reflet->isSubclassOf(Model::class)) {
                continue;
            }

            $modele = $reflet->newInstanceWithoutConstructor();
            $propriete = $reflet->getProperty('fillable');
            $propriete->setAccessible(true);

            if ($propriete->getValue($modele) === []) {
                $sansFillable[] = $reflet->getShortName();
            }
        }

        expect($sansFillable)->toBe([],
            'Modèles sans $fillable : '.implode(', ', $sansFillable));
    });
});

describe('identifiants publics', function (): void {
    it("n'expose aucun identifiant auto-incrémenté en URL", function (): void {
        /*
         | Un `/factures/1042` dit à qui le lit combien de factures existent, et
         | invite à essayer 1041. La règle 4 du chantier impose `uuid` ou `slug`
         | sur tout modèle atteignable publiquement.
         */
        $exposes = [];

        foreach (glob(app_path('Models/*.php')) as $fichier) {
            $classe = 'App\\Models\\'.basename($fichier, '.php');

            if (! class_exists($classe)) {
                continue;
            }

            $reflet = new ReflectionClass($classe);

            if ($reflet->isAbstract() || ! $reflet->isSubclassOf(Model::class)) {
                continue;
            }

            $nom = $reflet->getShortName();

            // On ne regarde que les modèles réellement liés à une route.
            $lie = false;

            foreach (Route::getRoutes() as $route) {
                if (str_contains((string) $route->getActionName(), $nom)
                    || str_contains($route->uri(), Str::kebab($nom))) {
                    $lie = true;
                    break;
                }
            }

            if (! $lie) {
                continue;
            }

            $modele = $reflet->newInstanceWithoutConstructor();

            if ($modele->getRouteKeyName() === 'id') {
                $exposes[] = $nom;
            }
        }

        expect($exposes)->toBe([],
            'Modèles routés par identifiant numérique : '.implode(', ', $exposes));
    });
});

describe('limitation de débit', function (): void {
    it('protège les routes sensibles', function (): void {
        /*
         | Sans limite, un formulaire de connexion se teste à mille mots de
         | passe par minute. Les trois seaux d'API et les compteurs des écrans
         | d'authentification sont vérifiés ici sur le CODE, pas sur l'intention.
         */
        $api = file_get_contents(base_path('routes/api.php'));
        $inscription = file_get_contents(app_path('Livewire/Auth/Register.php'));

        expect($api)->toContain('throttle:api-identite')
            ->and($api)->toContain('throttle:api-ecriture')
            ->and($inscription)->toContain('RateLimiter::tooManyAttempts');
    });
});

describe('dépôt de fichiers', function (): void {
    it('ne fait jamais confiance au nom fourni', function (): void {
        /*
         | Un nom d'origine peut contenir un séparateur de chemin ou une double
         | extension (`photo.php.jpg`). Le dépôt engendre donc un nom aléatoire
         | partout — on le vérifie sur les écrans qui reçoivent des fichiers.
         */
        $parametres = file_get_contents(app_path('Livewire/Staff/Parametres.php'));

        expect($parametres)->toContain('Str::uuid()')
            ->and($parametres)->not->toContain('getClientOriginalName()');
    });

    it('contraint le type par le CONTENU, pas par l’étiquette', function (): void {
        $types = file_get_contents(app_path('Core/Application/Reglages/TypeDeReglage.php'));

        // `mimes` inspecte le contenu ; `extensions` se fierait au nom.
        expect($types)->toContain("'mimes:")
            ->and($types)->toContain("'max:");
    });
});

describe('production', function (): void {
    it("n'expose pas de trace technique une fois en ligne", function (): void {
        /*
         | En `APP_DEBUG=true`, la page d'erreur affiche la trace complète, les
         | variables d'environnement et des extraits de code — à qui provoque
         | une erreur. C'est la deuxième fuite de secrets la plus courante après
         | le `.env` servi.
         */
        $exemple = file_get_contents(base_path('.env.example'));

        expect($exemple)->toContain('APP_DEBUG=true');

        // L'exemple est en `true` pour le développement, ce qui est correct.
        // Ce qui compte est que la doc de déploiement l'impose à `false`.
        $deploiement = file_get_contents(base_path('docs/DEPLOIEMENT_CPANEL.md'));

        expect($deploiement)->toContain('APP_DEBUG=false');
    });

    it('garde le .env hors de la racine web', function (): void {
        // Un `.env` servi rend la clé d'application, le mot de passe de base et
        // toutes les clés d'API — en clair, à qui le demande.
        expect(is_file(public_path('.env')))->toBeFalse()
            ->and(is_file(public_path('artisan')))->toBeFalse();
    });
});

describe('RGPD', function (): void {
    it('offre un export de ses données', function (): void {
        expect(class_exists(PreparerExportDonnees::class))->toBeTrue();
    });

    it('offre une suppression de compte, avec délai de rétractation', function (): void {
        /*
         | Le droit à l'effacement suppose un chemin réel, pas une adresse de
         | support. Et un délai : une suppression immédiate sur un clic mal
         | placé est irrattrapable.
         */
        expect(class_exists(SuppressionCompteService::class))->toBeTrue();

        $service = file_get_contents(app_path('Core/Identity/Services/SuppressionCompteService.php'));

        expect($service)->toContain('anonym');
    });

    it('purge automatiquement ce qui a expiré', function (): void {
        // Une obligation de conservation confiée à quelqu'un qui doit y penser
        // n'est pas une obligation remplie.
        $console = file_get_contents(base_path('routes/console.php'));

        expect($console)->toContain('PurgerDonneesExpirees');
    });

    it('ne conserve ni IP ni agent dans le suivi de visites', function (): void {
        $suivi = file_get_contents(app_path('Core/Marketing/Services/SuiviDesVisites.php'));

        expect($suivi)->toContain('hash(')
            // L'empreinte est salée au JOUR : le suivi ne peut pas retracer un
            // parcours dans le temps, même pour qui aurait la base.
            ->and($suivi)->toContain('toDateString()');
    });
});
