<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Marketing\Emails\ExpediteurDeCourriels;
use App\Enums\PreferenceNotification;
use App\Models\Course;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Annonce un cours fraîchement publié.
 *
 * ## L'audience, tranchée le 2 septembre 2026
 *
 * **Les citoyens déjà inscrits à au moins un cours**, et dont les « rappels
 * d'apprentissage » sont actifs. Deux autres audiences ont été écartées, et
 * chacune pour une raison distincte :
 *
 *   · **tous les comptes** — annoncer un cours à quelqu'un qui n'a jamais
 *     ouvert l'Académie est de la prospection, pas un service. Le premier
 *     message de ce genre est toléré, le troisième fait perdre le compte ;
 *   · **la liste d'infolettre** — son consentement porte sur les fact-checks
 *     hebdomadaires. Y verser des annonces de cours étendrait un consentement
 *     à un objet qu'il ne couvre pas, ce qui est exactement le reproche fait
 *     aux plateformes qu'on démystifie.
 *
 * Une inscription antérieure est un signal d'intérêt donné par la personne
 * elle-même. C'est le fondement le plus solide dont on dispose sans lui poser
 * une question de plus.
 *
 * L'envoi reste **éteint par défaut** au catalogue : cette audience décrit qui
 * recevrait le message, pas qu'il faille l'envoyer.
 *
 * ## Le marqueur, et pourquoi il n'est pas facultatif
 *
 * Publier n'est pas un geste unique. On dépublie pour corriger une leçon, on
 * republie une heure plus tard ; sans `announced_at`, chaque bascule renverrait
 * l'annonce aux mêmes personnes. Il est posé AVANT la boucle : un job repris
 * après incident ne réannonce pas.
 */
final class AnnoncerLeNouveauCours implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Une seule tentative : un renvoi automatique doublerait l'annonce. */
    public int $tries = 1;

    public int $timeout = 900;

    /** Assez petit pour ne pas charger dix mille comptes d'un coup. */
    private const TAILLE_LOT = 200;

    public function __construct(private readonly int $coursId) {}

    public function handle(ExpediteurDeCourriels $courriels): void
    {
        $cours = Course::find($this->coursId);

        if ($cours === null || ! $cours->is_published || $cours->announced_at !== null) {
            // Dépublié entre-temps, supprimé, ou déjà annoncé. On se tait : un
            // job rejoué par une file qui rattrape son retard ne doit pas alerter.
            return;
        }

        /*
         | Le marqueur est posé AVANT la boucle, pas après.
         |
         | Posé après, un job interrompu au milieu — timeout, redémarrage —
         | serait rejoué depuis le début et servirait deux fois les premiers
         | destinataires. Posé avant, une interruption prive au pire les
         | derniers d'une annonce ; c'est le moindre mal, et de loin.
         */
        $cours->forceFill(['announced_at' => now()])->save();

        $lien = route('citizen.course', ['course' => $cours]);
        // `libelle()` et non la valeur : le courriel dirait « beginner » à
        // quelqu'un qui lit en français. `level` est casté en enum, et le
        // convertir en chaîne lève.
        $niveau = $cours->level->libelle();

        $this->audience(function (User $citoyen) use ($courriels, $cours, $lien, $niveau): void {
            $courriels->envoyer(
                $citoyen,
                'nouveau_cours',
                [
                    'prenom' => $citoyen->name,
                    'cours' => $cours->title,
                    'niveau' => $niveau,
                ],
                $lien,
                // La préférence individuelle est vérifiée par l'expéditeur :
                // l'audience dit QUI pourrait recevoir, la préférence dit qui
                // a accepté.
                PreferenceNotification::RappelsApprentissage,
            );
        });
    }

    /**
     * Les comptes actifs ayant au moins une inscription, par lots.
     *
     * @param  callable(User): void  $traitement
     */
    private function audience(callable $traitement): void
    {
        User::query()
            ->whereNotNull('otp_verified_at')
            ->whereNull('anonymized_at')
            // `whereExists` plutôt qu'une jointure : une jointure rendrait un
            // compte autant de fois qu'il a d'inscriptions, et la personne
            // recevrait l'annonce une fois par cours suivi.
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('enrollments')
                ->whereColumn('enrollments.user_id', 'users.id'))
            ->orderBy('id')
            ->chunkById(self::TAILLE_LOT, function ($lot) use ($traitement): void {
                foreach ($lot as $citoyen) {
                    $traitement($citoyen);
                }
            });
    }
}
