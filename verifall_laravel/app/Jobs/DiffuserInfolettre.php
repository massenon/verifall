<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Marketing\Enums\StatutCampagne;
use App\Core\Marketing\Services\Infolettre;
use App\Mail\DiffusionInfolettre;
use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Diffuse une campagne écrite à la main, à tous les abonnés confirmés.
 *
 * ## Pourquoi un job, et pas un envoi dans la requête
 *
 * Trois mille adresses ne partent pas dans les trente secondes d'une requête
 * HTTP. Surtout : le clic qui déclenche est irréversible, et le laisser dépendre
 * d'un onglet resté ouvert ferait qu'un rafraîchissement au mauvais moment
 * couperait la diffusion au milieu de la liste.
 *
 * **Sur cPanel mutualisé, ce job attend le cron `queue:work`.** Sans lui, rien
 * ne part et rien ne le signale — l'écran dira « diffusion en cours » pour
 * toujours. C'est le même piège que l'inscription qui s'arrête à l'étape 2, et
 * la carte « Santé du système » est l'endroit où le manque se voit.
 *
 * ## L'idempotence est déjà résolue, et pas ici
 *
 * `reserverEnvoi()` insère une ligne de livraison AVANT l'envoi ; l'unicité en
 * base fait que deux exécutions ne peuvent pas servir deux fois la même
 * adresse. Un job rejoué après un timeout reprend donc là où il s'était arrêté,
 * sans que personne ne reçoive de doublon. C'est aussi ce qui permet de relancer
 * sans réfléchir après un incident.
 */
final class DiffuserInfolettre implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Une seule tentative automatique.
     *
     * Un renvoi automatique sur une diffusion de masse est le genre de chose
     * qui expédie deux fois la même lettre à trois mille personnes le jour où
     * la réservation aura un défaut. On préfère un job échoué visible, relancé
     * à la main.
     */
    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(private readonly int $campagneId) {}

    public function handle(DepotAbonnes $depot, Infolettre $infolettre): void
    {
        $campagne = Campaign::find($this->campagneId);

        if ($campagne === null || StatutCampagne::depuis($campagne->status) === StatutCampagne::Envoyee) {
            // Déjà servie, ou supprimée entre-temps. On se tait : un job rejoué
            // par une file qui rattrape son retard ne doit pas alerter.
            return;
        }

        $destinataires = $depot->compterDestinataires();
        $depot->recalerCompteurs($campagne, $destinataires, StatutCampagne::EnCours->value);

        $envoyes = 0;
        $echecs = 0;
        $dernierId = 0;

        while (true) {
            $lot = $depot->destinataires(Infolettre::TAILLE_LOT, $dernierId);

            if ($lot->isEmpty()) {
                break;
            }

            foreach ($lot as $abonne) {
                $dernierId = $abonne->id;

                // Réservation d'abord, envoi ensuite : l'ordre EST l'idempotence.
                if (! $depot->reserverEnvoi($campagne, $abonne)) {
                    continue;
                }

                try {
                    Mail::to($abonne->email)->send(new DiffusionInfolettre(
                        objet: (string) $campagne->subject,
                        corps: (string) $campagne->body,
                        lienDeDesabonnement: $infolettre->lienDeDesabonnement($abonne),
                        nomDuSite: $infolettre->nomDuSite(),
                        prenom: $abonne->name,
                    ));

                    $depot->marquerEnvoye($campagne, $abonne);
                    $envoyes++;
                } catch (Throwable $e) {
                    /*
                     | Un échec sur UNE adresse n'arrête pas la diffusion : une
                     | boîte pleine ne doit pas priver les suivantes.
                     */
                    $depot->marquerEchoue($campagne, $abonne, $e->getMessage());
                    $echecs++;
                }
            }
        }

        $depot->recalerCompteurs($campagne, $destinataires, StatutCampagne::Envoyee->value);

        if ($echecs > 0) {
            // Un envoi partiel n'est pas un envoi réussi. Le journal le dit,
            // parce que l'écran, lui, affichera surtout le nombre d'envoyés.
            Log::warning('Infolettre : diffusion partielle.', [
                'campagne' => $campagne->uuid,
                'envoyes' => $envoyes,
                'echecs' => $echecs,
            ]);
        }
    }
}
