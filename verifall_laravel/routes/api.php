<?php

declare(strict_types=1);

use App\Http\Api\V1\Controllers\AcademieController;
use App\Http\Api\V1\Controllers\AuthentificationController;
use App\Http\Api\V1\Controllers\ForumController;
use App\Http\Api\V1\Controllers\GamificationController;
use App\Http\Api\V1\Controllers\HubController;
use App\Http\Api\V1\Controllers\MonCompteController;
use App\Http\Api\V1\Controllers\PorteMonnaieController;
use App\Http\Api\V1\Controllers\SeanceEtOutilsController;
use App\Http\Api\V1\Controllers\TableauDeBordController;
use App\Http\Api\V1\Controllers\TeleversementController;
use App\Http\Api\V1\Controllers\VerificationController;
use App\Http\Api\V1\Middleware\CompteApiActif;
use App\Http\Api\V1\Middleware\CurseurValide;
use App\Http\Api\V1\Middleware\Idempotence;
use App\Http\Api\V1\Middleware\RafraichirLeJeton;
use App\Http\Api\V1\Middleware\VersionApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — le contrat mobile
|--------------------------------------------------------------------------
|
| Le contrat complet vit dans `docs/kb/API_SPECIFICATION.md`. Ce fichier en
| est la traduction exécutable : chaque route ci-dessous correspond à un appel
| déclaré par un écran du simulateur `prototype_html/mobile/index.html`, et
| AUCUNE ne va au-delà. Une API plus large qu'utile est une surface d'attaque
| et une dette d'entretien.
|
| LA VERSION EST DANS L'URL. Un numéro majeur en préfixe se lit dans un
| journal d'accès, se route par un mandataire, et se sert en parallèle de la
| suivante — ce qu'un en-tête de négociation ne permet pas aussi simplement.
| C'est ce qui autorise à publier une v2 sans casser les téléphones qui
| tournent encore en v1.
|
| LES NOMS DE ROUTE (`api.v1.*`) SONT DU CONTRAT : `CompteApiActif` s'en sert
| pour reconnaître les deux routes qu'un compte en cours de suppression garde
| le droit d'appeler.
|
*/

Route::prefix('api/v1')
    ->name('api.v1.')
    ->middleware(['api', VersionApi::class, CurseurValide::class])
    ->group(function (): void {

        /*
         |------------------------------------------------------------------
         | Identité — SANS jeton
         |------------------------------------------------------------------
         |
         | Débit serré et compté PAR ADRESSE IP : il n'y a pas encore de jeton
         | à quoi rattacher un plafond, et ce sont les points d'entrée du
         | forçage de mot de passe et de l'envoi massif de courriels.
         */
        Route::middleware('throttle:api-identite')->group(function (): void {
            Route::post('auth/login', [AuthentificationController::class, 'connexion'])
                ->name('auth.login');

            Route::post('auth/register', [AuthentificationController::class, 'inscription'])
                ->name('auth.register');

            // Deux URI pour un seul geste : l'écran de saisie du code a un
            // bouton « renvoyer », et le simulateur les nomme séparément.
            Route::post('auth/otp/request', [AuthentificationController::class, 'demanderLeCode'])
                ->name('auth.otp.request');
            Route::post('auth/otp/resend', [AuthentificationController::class, 'demanderLeCode'])
                ->name('auth.otp.resend');

            Route::post('auth/otp/verify', [AuthentificationController::class, 'verifierLeCode'])
                ->name('auth.otp.verify');

            Route::post('auth/password/forgot', [AuthentificationController::class, 'motDePasseOublie'])
                ->name('auth.password.forgot');
            Route::post('auth/password/reset', [AuthentificationController::class, 'reinitialiserLeMotDePasse'])
                ->name('auth.password.reset');
        });

        /*
         |------------------------------------------------------------------
         | Tout le reste — AVEC jeton
         |------------------------------------------------------------------
         |
         | Quatre gardes, aucune redondante :
         |   auth:sanctum ...... le jeton existe et n'est pas expiré ;
         |   CompteApiActif .... le compte est activé et n'est pas en cours de
         |                       suppression ;
         |   RafraichirLeJeton . repousse l'échéance tant que l'application sert ;
         |   throttle .......... plafond par jeton, distinct en lecture et en
         |                       écriture.
         */
        Route::middleware(['auth:sanctum', CompteApiActif::class, RafraichirLeJeton::class])
            ->group(function (): void {

                // Se déconnecter reste possible même en cours de suppression :
                // refuser de fermer un accès serait absurde.
                Route::post('auth/logout', [AuthentificationController::class, 'deconnexion'])
                    ->name('auth.logout');

                /* ---------------------------------------------- lectures */
                Route::middleware('throttle:api-lecture')->group(function (): void {
                    Route::get('me', [MonCompteController::class, 'afficher'])->name('me.show');
                    Route::get('me/settings', [MonCompteController::class, 'reglages'])->name('me.settings');
                    Route::get('me/dashboard', [TableauDeBordController::class, 'accueil'])->name('me.dashboard');
                    Route::get('me/stats', [TableauDeBordController::class, 'statistiques'])->name('me.stats');

                    Route::get('me/verifications', [VerificationController::class, 'mesVerifications'])
                        ->name('me.verifications');
                    Route::get('verifications/{verification}', [VerificationController::class, 'afficher'])
                        ->name('verifications.show');

                    Route::get('me/wallet', [PorteMonnaieController::class, 'afficher'])->name('me.wallet');
                    Route::get('me/wallet/transactions', [PorteMonnaieController::class, 'mouvements'])
                        ->name('me.wallet.transactions');

                    Route::get('me/gamification', [GamificationController::class, 'recompenses'])
                        ->name('me.gamification');
                    Route::get('me/certificates', [GamificationController::class, 'certificats'])
                        ->name('me.certificates');
                    Route::get('me/certificates/{certificate}', [GamificationController::class, 'certificat'])
                        ->name('me.certificates.show');
                    Route::get('me/referrals', [GamificationController::class, 'parrainage'])
                        ->name('me.referrals');

                    Route::get('courses', [AcademieController::class, 'catalogue'])->name('courses.index');
                    Route::get('courses/{course}', [AcademieController::class, 'cours'])->name('courses.show');
                    Route::get('courses/{course}/quiz', [AcademieController::class, 'quiz'])->name('courses.quiz');
                    Route::get('courses/{course}/lessons/{lesson}', [AcademieController::class, 'lecon'])
                        ->name('courses.lesson');

                    Route::get('me/enrollments', [AcademieController::class, 'mesInscriptions'])
                        ->name('me.enrollments');
                    Route::get('me/courses/resume', [AcademieController::class, 'reprendre'])
                        ->name('me.courses.resume');
                    Route::get('me/courses/{course}/progress', [AcademieController::class, 'progression'])
                        ->name('me.courses.progress');

                    Route::get('live-classes', [SeanceEtOutilsController::class, 'seances'])
                        ->name('live-classes.index');
                    Route::get('tools', [SeanceEtOutilsController::class, 'outils'])->name('tools.index');

                    Route::get('forum/threads', [ForumController::class, 'sujets'])->name('forum.threads.index');
                    Route::get('forum/threads/{thread}', [ForumController::class, 'sujet'])
                        ->name('forum.threads.show');

                    Route::get('hub/articles', [HubController::class, 'articles'])->name('hub.articles.index');
                    Route::get('hub/articles/{article}', [HubController::class, 'article'])
                        ->name('hub.articles.show');
                    Route::get('hub/opportunities', [HubController::class, 'opportunites'])
                        ->name('hub.opportunities.index');
                    Route::get('hub/opportunities/{opportunity}', [HubController::class, 'opportunite'])
                        ->name('hub.opportunities.show');
                });

                /* ---------------------------------------------- écritures */
                Route::middleware(['throttle:api-ecriture', Idempotence::class])->group(function (): void {
                    Route::patch('me', [MonCompteController::class, 'mettreAJour'])->name('me.update');
                    Route::delete('me', [MonCompteController::class, 'supprimer'])->name('me.destroy');
                    Route::patch('me/settings', [MonCompteController::class, 'enregistrerLesReglages'])
                        ->name('me.settings.update');
                    Route::post('me/export', [MonCompteController::class, 'exporterMesDonnees'])
                        ->name('me.export');

                    Route::post('uploads', TeleversementController::class)->name('uploads.store');
                    Route::post('verifications', [VerificationController::class, 'soumettre'])
                        ->name('verifications.store');
                    Route::post('verifications/{verification}/contest', [VerificationController::class, 'contester'])
                        ->name('verifications.contest');

                    Route::post('me/wallet/topup', [PorteMonnaieController::class, 'recharger'])
                        ->name('me.wallet.topup');
                    Route::post('me/referrals/invite', [GamificationController::class, 'inviter'])
                        ->name('me.referrals.invite');

                    Route::post('courses/{course}/enroll', [AcademieController::class, 'inscrire'])
                        ->name('courses.enroll');
                    Route::post('me/lessons/{lesson}/complete', [AcademieController::class, 'terminerLaLecon'])
                        ->name('me.lessons.complete');
                    Route::post('me/quiz/{quiz}/attempt', [AcademieController::class, 'tenterLeQuiz'])
                        ->name('me.quiz.attempt');

                    Route::post('live-classes/{liveClass}/remind', [SeanceEtOutilsController::class, 'rappeler'])
                        ->name('live-classes.remind');

                    Route::post('forum/threads', [ForumController::class, 'publier'])
                        ->name('forum.threads.store');
                    Route::post('forum/threads/{thread}/vote', [ForumController::class, 'voter'])
                        ->name('forum.threads.vote');
                    Route::post('forum/threads/{thread}/replies', [ForumController::class, 'repondre'])
                        ->name('forum.threads.replies');
                    Route::post('forum/threads/{thread}/report', [ForumController::class, 'signaler'])
                        ->name('forum.threads.report');

                    Route::post('hub/opportunities/{opportunity}/report', [HubController::class, 'signaler'])
                        ->name('hub.opportunities.report');
                });
            });
    });
