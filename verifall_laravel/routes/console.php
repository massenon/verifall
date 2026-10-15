<?php

declare(strict_types=1);

use App\Console\Commands\AttribuerDotationMensuelle;
use App\Console\Commands\CalculerLesScoresDePontage;
use App\Console\Commands\EnvoyerInfolettreHebdomadaire;
use App\Console\Commands\FermerLesOpportunitesExpirees;
use App\Console\Commands\PurgerDonneesExpirees;
use App\Console\Commands\ReconcilierPaiements;
use App\Console\Commands\SonderLesMoteurs;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
| Rappel : elles n'existent que si un `php artisan schedule:run` tourne toutes
| les minutes sur le serveur. Une obligation de rétention confiée à un
| planificateur qui n'est pas branché n'est pas une obligation remplie.
*/

// Toutes les heures : assez fréquent pour qu'une archive expirée ne traîne
// jamais longtemps, assez espacé pour ne rien coûter.
Schedule::command(PurgerDonneesExpirees::class)
    ->hourly()
    // Sans chevauchement : deux passages simultanés tenteraient d'anonymiser
    // le même compte, et le second écraserait la date posée par le premier.
    ->withoutOverlapping();

// Toutes les 10 minutes : une recharge payée dont le rappel s'est perdu ne doit
// pas rester en attente une heure. Réinterroge avant de conclure.
Schedule::command(ReconcilierPaiements::class)
    ->everyTenMinutes()
    ->withoutOverlapping();

// Le 1ᵉʳ du mois, à 00h05 : « 10 unités offertes chaque 1ᵉʳ du mois »
// (prototype). Cinq minutes après minuit, pour ne pas courir contre le
// changement de date.
Schedule::command(AttribuerDotationMensuelle::class)
    ->monthlyOn(1, '00:05')
    ->withoutOverlapping();

// Une fois par jour, tôt : une opportunité expirée depuis plus d'une semaine ne
// sert plus qu'à faire perdre du temps à quelqu'un qui cherche du travail. La
// granularité du jour suffit — une date limite n'a pas d'heure.
Schedule::command(FermerLesOpportunitesExpirees::class)
    ->dailyAt('03:20')
    ->withoutOverlapping();

/*
 | Notes de communauté : le calcul de pontage.
 |
 | Toutes les heures, et pas autrement. Le calcul est quadratique en nombre de
 | votants — chaque minute ferait payer un travail dont le résultat ne bouge
 | qu'au rythme des votes ; chaque jour ferait arriver le contexte après la
 | rumeur, ce qui revient à ne pas l'avoir écrit.
 |
 | SANS CETTE LIGNE, AUCUNE NOTE NE SE PUBLIE JAMAIS. Les votes s'accumulent,
 | les notes restent « en attente d'évaluation », et rien ne signale la panne.
 | C'est le premier point à vérifier si le mécanisme paraît inerte.
 */
Schedule::command(CalculerLesScoresDePontage::class)
    ->hourly()
    ->withoutOverlapping();

/*
 | L'infolettre du lundi. 07h10, heure de Cotonou.
 |
 | 07h10 et non 07h00 : sur un hébergement mutualisé, les heures rondes sont
 | encombrées. Le fuseau est EXPLICITE parce que `config/app.php` est en UTC —
 | sans lui, le rendez-vous du lundi matin partirait à 08h10 locale, et le nom
 | même du produit deviendrait faux.
 |
 | `runInBackground` : l'envoi est synchrone, par choix assumé (arbitrage 157 —
 | un envoi mis en file rendrait le compteur d'échecs toujours nul). Il peut
 | durer plusieurs minutes ; sans cela il retiendrait tout le planificateur.
 */
Schedule::command(EnvoyerInfolettreHebdomadaire::class)
    ->weeklyOn(1, '07:10')
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping()
    ->runInBackground();

/*
 | Toutes les heures : l'état des moteurs d'analyse, pour le tableau de bord.
 |
 | Il est HORS DE QUESTION de sonder à l'affichage. Le quota gratuit de Google
 | Custom Search est de cent requêtes par jour : un écran ouvert toutes les
 | trois minutes l'épuiserait avant midi, et la carte « Santé du système »
 | provoquerait elle-même la panne qu'elle affiche.
 |
 | Une heure suffit largement : une clé d'API ne meurt pas en dix minutes, et
 | l'écran affiche l'âge de la mesure à côté de la mesure.
 */
Schedule::command(SonderLesMoteurs::class)
    ->hourly()
    ->withoutOverlapping();
