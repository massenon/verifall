<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Application\Services\SyntheseCitoyen;
use App\Core\Gamification\Services\NiveauCitoyen;
use App\Core\Gamification\Services\SerieDeVigilance;
use App\Enums\VerdictFactCheck;
use App\Http\Api\V1\Resources\CoursResource;
use App\Http\Api\V1\Support\Enveloppe;
use App\Models\Course;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les deux écrans de chiffres : l'accueil et les statistiques.
 *
 * POURQUOI UN AGRÉGAT ET NON SIX APPELS. Le prototype mobile est explicite :
 * la première image doit tenir sous 400 ms. Six allers-retours sur un réseau
 * mobile béninois, c'est six fois la latence — l'écran d'accueil deviendrait
 * l'écran le plus lent de l'application, celui qu'on voit à chaque ouverture.
 *
 * Les chiffres viennent tous de `SyntheseCitoyen`, partagé avec l'écran web :
 * deux formules pour un même pourcentage finissent par afficher deux
 * pourcentages.
 */
final class TableauDeBordController
{
    /** `GET /me/dashboard` */
    public function accueil(
        Request $requete,
        SyntheseCitoyen $synthese,
        SerieDeVigilance $series,
        NiveauCitoyen $niveaux,
    ): JsonResponse {
        $utilisateur = $this->utilisateur($requete);

        return Enveloppe::donnee([
            'salutation' => $utilisateur->first_name,
            'compteurs' => $synthese->compteurs($utilisateur),
            'niveau' => [
                'rang' => $niveaux->niveau($utilisateur),
                'nom' => $niveaux->nom($utilisateur),
                'pourcentage' => $niveaux->pourcentage($utilisateur),
            ],
            'serie' => [
                'jours' => $utilisateur->streak_days,
                'vivante' => $series->estVivante($utilisateur),
            ],
            'activite' => $synthese->activite($utilisateur, 30),
            /*
             | Le cours offert du mois n'est pas choisi ici : il l'est en
             | donnant une date à `free_until`. Aucun cours en promotion ⇒ la
             | bannière ne s'affiche pas. Annoncer une offre qui n'existe pas
             | serait une promesse en l'air.
             */
            'cours_offert' => ($offert = $this->coursOffertDuMois()) === null
                ? null
                : CoursResource::make($offert),
        ]);
    }

    /**
     * `GET /me/stats`
     *
     * `period` accepte 7d, 30d, 90d, 365d — la même liste fermée que l'écran
     * web. Une valeur libre laisserait demander 100 000 jours d'historique,
     * c'est-à-dire une requête très coûteuse pour un graphique illisible.
     */
    public function statistiques(Request $requete, SyntheseCitoyen $synthese): JsonResponse
    {
        $requete->validate([
            'period' => ['nullable', 'string', 'in:7d,30d,90d,365d'],
        ]);

        $jours = match ($requete->string('period', '30d')->toString()) {
            '7d' => 7,
            '90d' => 90,
            '365d' => 365,
            default => 30,
        };

        $utilisateur = $this->utilisateur($requete);
        $verdicts = $synthese->verdicts($utilisateur);

        return Enveloppe::donnee([
            'periode_jours' => $jours,
            'verifications' => Verification::where('user_id', $utilisateur->id)->count(),
            'intox_detectees' => ($verdicts[VerdictFactCheck::Faux->value] ?? 0)
                + ($verdicts[VerdictFactCheck::Trompeur->value] ?? 0),
            'evolution_mensuelle' => $synthese->evolutionMensuelle($utilisateur),
            'lecons_terminees' => $synthese->leconsTerminees($utilisateur),
            'lecons_cette_semaine' => $synthese->leconsCetteSemaine($utilisateur),
            'verdicts' => $verdicts,
            'types' => $synthese->types($utilisateur),
            'activite' => $synthese->activite($utilisateur, $jours),
            'semaine' => $synthese->semaine($utilisateur),
        ]);
    }

    /** Le cours en promotion, s'il y en a un. */
    private function coursOffertDuMois(): ?Course
    {
        return Course::with('instructor')
            ->where('is_published', true)
            ->whereNotNull('free_until')
            ->whereDate('free_until', '>=', now()->toDateString())
            ->orderBy('free_until')
            ->first();
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
