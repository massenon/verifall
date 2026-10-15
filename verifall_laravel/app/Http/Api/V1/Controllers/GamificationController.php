<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Gamification\Services\AttributionDesBadges;
use App\Core\Gamification\Services\NiveauCitoyen;
use App\Core\Gamification\Services\SerieDeVigilance;
use App\Core\Marketing\Services\Parrainage;
use App\Http\Api\V1\Resources\BadgeResource;
use App\Http\Api\V1\Resources\CertificatResource;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Récompenses, badges, certificats et parrainage.
 *
 * Le niveau, la série de vigilance et l'attribution des badges viennent de
 * `app/Core/Gamification/Services` — recalculés, jamais incrémentés à
 * l'aveugle. Le code de parrainage et son lien viennent de
 * `Marketing\Services\Parrainage`, qui garantit l'alphabet sans O/0, I/1, S/5
 * et l'usage de `random_int`.
 */
final class GamificationController
{
    /**
     * `GET /me/gamification`
     *
     * L'appel RECALCULE les badges avant de répondre, comme le fait l'écran
     * web à l'ouverture. Un badge mérité qu'on ne découvre qu'au prochain
     * passage est un badge qui semble ne pas marcher.
     */
    public function recompenses(
        Request $requete,
        AttributionDesBadges $attribution,
        NiveauCitoyen $niveaux,
        SerieDeVigilance $series,
    ): JsonResponse {
        $utilisateur = $this->utilisateur($requete);

        $attribution->recalculer($utilisateur);

        $obtentions = UserBadge::where('user_id', $utilisateur->id)
            ->get()
            ->keyBy('badge_id');

        $badges = Badge::where('is_active', true)
            ->orderBy('position')
            ->get()
            ->each(function (Badge $badge) use ($obtentions): void {
                $obtention = $obtentions->get($badge->id);
                $badge->setAttribute('debloque', $obtention !== null);
                $badge->setAttribute('debloque_le', $obtention?->unlocked_at?->toIso8601String());
            });

        return Enveloppe::donnee([
            'niveau' => [
                'rang' => $niveaux->niveau($utilisateur),
                'nom' => $niveaux->nom($utilisateur),
                'xp' => $utilisateur->xp,
                'xp_dans_le_palier' => $niveaux->xpDansLePalier($utilisateur),
                'xp_restant' => $niveaux->xpRestant($utilisateur),
                'pourcentage' => $niveaux->pourcentage($utilisateur),
            ],
            'serie' => [
                'jours' => $utilisateur->streak_days,
                'record' => $utilisateur->streak_best,
                'vivante' => $series->estVivante($utilisateur),
                'jours_avant_la_prime' => $series->joursAvantLaPrime($utilisateur),
            ],
            'badges' => BadgeResource::collection($badges),
        ]);
    }

    /**
     * `GET /me/certificates`
     *
     * Pas de pagination : personne n'obtient trois cents certificats, et une
     * liste de dix éléments paginée est une complication gratuite pour le
     * client.
     */
    public function certificats(Request $requete): JsonResponse
    {
        $certificats = Certificate::with('course')
            ->where('user_id', $this->utilisateur($requete)->id)
            ->orderByDesc('issued_at')
            ->get();

        return Enveloppe::donnee(CertificatResource::collection($certificats));
    }

    /**
     * `GET /me/certificates/{code}`
     *
     * Borné au TITULAIRE. La vérification publique d'un certificat existe
     * déjà, sur le site, et c'est là qu'elle doit rester : un recruteur n'a
     * pas de jeton, et cette route ne doit pas devenir un second oracle où
     * l'on énumère des codes.
     */
    public function certificat(Request $requete, Certificate $certificate): JsonResponse
    {
        if ($certificate->user_id !== $this->utilisateur($requete)->id) {
            return Enveloppe::erreur(CodeErreur::Introuvable);
        }

        return Enveloppe::donnee(CertificatResource::make($certificate->loadMissing('course')));
    }

    /** `GET /me/referrals` */
    public function parrainage(Request $requete, Parrainage $parrainages): JsonResponse
    {
        $utilisateur = $this->utilisateur($requete);

        return Enveloppe::donnee([
            'code' => $parrainages->codeDe($utilisateur),
            'lien' => $parrainages->lienDe($utilisateur),
            'indicateurs' => $parrainages->indicateurs($utilisateur),
            'filleuls' => Referral::with('referred')
                ->where('referrer_id', $utilisateur->id)
                ->orderByDesc('id')
                ->get()
                ->map(fn (Referral $parrainage): array => [
                    'uuid' => $parrainage->uuid,
                    // Le PRÉNOM seul, jamais l'adresse e-mail : la personne
                    // parrainée n'a pas consenti à ce que son adresse soit
                    // rendue à qui l'a invitée.
                    'prenom' => $parrainage->referred?->first_name,
                    'statut' => $parrainage->status->value,
                    'statut_libelle' => $parrainage->status->libelle(),
                    'inscrit_le' => $parrainage->registered_at?->toIso8601String(),
                    'recompense_le' => $parrainage->rewarded_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    /**
     * `POST /me/referrals/invite`
     *
     * Ne fait PAS d'envoi. Le partage est un geste du téléphone — WhatsApp,
     * SMS, QR à l'écran — et confier l'envoi au serveur ferait arriver une
     * invitation depuis une adresse que le destinataire ne connaît pas, dans
     * un dossier indésirable, plutôt que d'un ami dans une conversation.
     *
     * L'appel NE COMPTE RIEN, et c'est délibéré. Le seul compteur honnête du
     * parrainage est `referral_visits` — le nombre de personnes qui ont OUVERT
     * une invitation — et il s'incrémente sur la page publique d'invitation,
     * pas ici. L'incrémenter à chaque ouverture de l'écran « Parrainage »
     * fabriquerait un indicateur qui mesure des consultations de son propre
     * code en le faisant passer pour de l'audience.
     */
    public function inviter(Request $requete, Parrainage $parrainages): JsonResponse
    {
        $utilisateur = $this->utilisateur($requete);

        return Enveloppe::donnee([
            'code' => $parrainages->codeDe($utilisateur),
            'lien' => $parrainages->lienDe($utilisateur),
            'message' => 'Partagez ce lien : votre filleul et vous serez récompensés à sa première vérification.',
        ]);
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
