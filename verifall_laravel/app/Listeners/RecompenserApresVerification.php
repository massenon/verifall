<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Gamification\Services\AttributionDesBadges;
use App\Core\Gamification\Services\SerieDeVigilance;
use App\Core\Marketing\Services\Parrainage;
use App\Events\VerificationAchevee;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ce qu'une vérification achevée déclenche du côté des récompenses.
 *
 * Trois gestes, dans cet ordre : la série du jour, le parrainage honoré, les
 * badges recalculés. L'ordre compte — le badge « Ambassadeur » se calcule sur
 * les parrainages éligibles, et le badge « Série de 7 jours » sur la série qui
 * vient d'être mise à jour.
 *
 * SYNCHRONE et non mis en file : ces trois opérations sont courtes, et
 * quelqu'un qui vient de terminer une vérification doit voir son badge tout de
 * suite. Une file ajouterait un délai visible pour économiser des millisecondes
 * invisibles.
 *
 * Mais surtout : ce qui échoue ici ne doit RIEN casser. Un badge qui ne
 * s'accorde pas est ennuyeux ; une analyse qui échoue parce qu'un badge n'a pas
 * pu s'accorder est inacceptable.
 */
final class RecompenserApresVerification
{
    public function __construct(
        private readonly SerieDeVigilance $series,
        private readonly Parrainage $parrainage,
        private readonly AttributionDesBadges $badges,
    ) {}

    public function handle(VerificationAchevee $evenement): void
    {
        try {
            /*
             | On lit la CLÉ, pas la relation. `->user` déclencherait une
             | requête que `preventLazyLoading` interdit — et l'exception
             | remonterait dans la transaction qui valide l'analyse, faisant
             | perdre à quelqu'un le rapport qu'il vient de payer.
             |
             | Et cette lecture est DANS le try : c'est elle qui lève, alors que
             | le commentaire de cette classe promet qu'un échec ici ne casse
             | rien. La promesse était juste ; la ligne qui la tenait manquait.
             */
            $auteurId = $evenement->verification->getAttribute('user_id');

            if ($auteurId === null) {
                return;
            }

            $utilisateur = User::find($auteurId);

            if ($utilisateur === null) {
                return;
            }

            $this->series->enregistrerUnPassage($utilisateur);
            $this->parrainage->honorer($utilisateur);
            $this->badges->recalculer($utilisateur->refresh());
        } catch (Throwable $e) {
            /*
             | Journalisé, jamais propagé. L'analyse est déjà écrite et payée :
             | la faire échouer pour un badge reviendrait à perdre le travail
             | utile au profit de l'accessoire.
             */
            Log::warning('Récompenses non attribuées après une vérification', [
                'verification' => $evenement->verification->uuid,
                'erreur' => $e->getMessage(),
            ]);
        }
    }
}
