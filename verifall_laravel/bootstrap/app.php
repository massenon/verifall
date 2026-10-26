<?php

declare(strict_types=1);

use App\Core\Identity\Services\VerrouDeSession;
use App\Http\Api\V1\Support\RenduDesErreursApi;
use App\Http\Middleware\ChoisirLaLangue;
use App\Http\Middleware\CompterLeNavigateur;
use App\Http\Middleware\MaintenanceApplicative;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\StaffIdleTimeout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        /*
         | L'API mobile a son propre fichier de routes, et son propre préfixe
         | déclaré à l'intérieur (`api/v1`). Pas de `apiPrefix` ici : la version
         | fait partie du contrat, elle doit se lire dans le fichier qui la
         | définit et non dans un réglage d'amorçage que personne ne relit.
         */
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias employés sur les groupes de routes staff.
        // Rappel : le middleware filtre, la Policy tranche.
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Sur toutes les réponses, y compris les erreurs.
        $middleware->append(SecureHeaders::class);

        // Expiration d'inactivité du back-office (30 min). Placé sur le groupe
        // web : le middleware se retire de lui-même quand le guard staff n'est
        // pas connecté, ce qui évite de dupliquer un groupe de routes entier.
        $middleware->web(append: [StaffIdleTimeout::class]);

        /*
         | La langue de la requête. Sur le groupe web, donc pour le site public
         | ET l'espace citoyen. Le back-office reste en français : il s'adresse
         | à une équipe, pas au public, et le traduire ferait deux fois le
         | travail pour deux personnes.
         |
         | APPEND, et surtout pas `prepend` : la pile web démarre la session
         | dans son propre ordre, et se placer avant elle fait lire une session
         | qui n'existe pas encore — « Session store not set on request », page
         | 500 sur tout le site public.
         |
         | En fin de pile web, la session est ouverte et le middleware s'exécute
         | quand même AVANT le contrôleur : tout ce qui rend du texte, messages
         | de validation compris, voit donc la bonne langue.
         */
        /*
         | Le mode maintenance, APRÈS `ChoisirLaLangue` et avant tout le reste :
         | la page de maintenance doit s'afficher dans la langue de la personne,
         | et rien d'autre n'a besoin de tourner quand le site est fermé.
         |
         | Il vit dans l'application et non dans `artisan down`, qui couperait
         | aussi le back-office — on ne pourrait alors plus rouvrir sans SSH.
         | Voir le bloc de documentation du middleware.
         */
        $middleware->web(append: [MaintenanceApplicative::class]);

        $middleware->web(append: [ChoisirLaLangue::class]);

        /*
         | Le compteur de navigateurs, qui alimente le panneau « Navigateurs »
         | du tableau de bord d'administration.
         |
         | Il compte APRÈS la réponse, n'écrit qu'un entier par famille et par
         | jour, et se tait sur toute erreur : un compteur de tableau de bord
         | n'a pas à faire tomber une page publique. Aucune donnée personnelle
         | n'est conservée — le détail du raisonnement est dans le modèle
         | `VisitorHit`, qui énumère ce qui n'est délibérément PAS stocké.
         */
        $middleware->web(append: [CompterLeNavigateur::class]);

        /*
         | Le rappel Mobile Money est exclu de la protection CSRF : l'opérateur
         | est un serveur tiers, il ne peut pas porter notre jeton de
         | formulaire. Sa légitimité est établie autrement — signature HMAC
         | vérifiée dans le contrôleur — et c'est la SEULE route exclue.
         |
         | L'API v1 n'y figure pas et n'a pas à y figurer : le groupe `api` de
         | Laravel est SANS ÉTAT, il ne passe pas par la session et n'entre
         | donc jamais dans le middleware CSRF. C'est précisément l'intérêt du
         | jeton porteur — pas de cookie, pas de CSRF.
         */
        $middleware->validateCsrfTokens(except: ['webhooks/momo/*']);

        /*
         | Où renvoyer un visiteur non connecté.
         |
         | Par défaut, Laravel renvoie tout le monde vers `login` — c'est-à-dire
         | vers la connexion CITOYENNE, y compris depuis le back-office. Le
         | membre de l'équipe atterrissait alors sur un écran qui refuse ses
         | identifiants, sans rien lui dire de plus. Il fallait connaître
         | `/admin/connexion` par cœur pour entrer.
         |
         | Le chemin demandé décide : ce qui commence par `admin/` mène à la
         | connexion du back-office.
         */
        // Une session de console VERROUILLÉE (n° 180) va à son écran de
        // verrouillage : la personne est là, seul le mot de passe manque.
        $middleware->redirectGuestsTo(fn (Request $requete): string => $requete->is('admin/*')
            ? (session()->has(VerrouDeSession::CLE) ? route('staff.verrou') : route('staff.login'))
            : route('login'));

        // Derrière un load-balancer TLS, sans quoi $request->secure() est faux
        // et HSTS n'est jamais émis.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Aucune donnée sensible dans les journaux d'exception.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'otp_code',
            'two_factor_code',
            'token',
        ]);

        /*
         | Toute exception qui traverse l'API v1 ressort dans l'enveloppe
         | unique. Sans ce rendu, un 404 de route model binding renverrait la
         | page HTML d'erreur du site à un client qui attend du JSON — et
         | l'application afficherait « une erreur est survenue » là où le
         | serveur savait dire « ce cours n'existe pas ».
         */
        RenduDesErreursApi::enregistrer($exceptions);
    })->create();
