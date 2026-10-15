<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Marketing\Emails\RenduDeGabarit;
use App\Core\Marketing\Enums\StatutCampagne;
use App\Core\Marketing\Services\Infolettre;
use App\Mail\AlerteIntox;
use App\Models\HubArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alerte la liste d'infolettre qu'une intox circule.
 *
 * ## L'audience, tranchée le 2 septembre 2026
 *
 * **Les abonnés confirmés de l'infolettre.** C'est exactement ce pour quoi ils
 * se sont inscrits — des fact-checks — et seule la CADENCE change : au lieu du
 * lundi, tout de suite. Étendre à tous les comptes aurait fait d'un service
 * demandé une notification imposée.
 *
 * Conséquence directe de ce choix : l'alerte passe par la machinerie de
 * campagne, pas par le mailable générique. Elle porte donc un lien de
 * désabonnement et l'en-tête `List-Unsubscribe`, et ses envois sont tracés
 * comme ceux de l'hebdomadaire.
 *
 * ## Éteinte par défaut, et c'est voulu
 *
 * Une alerte est le message le plus intrusif que la plateforme envoie. Le
 * catalogue la livre à `false` : l'équipe l'arme quand elle décide que ce
 * canal a un sens, pas parce que le code existe.
 *
 * ## L'idempotence, à deux niveaux
 *
 * `alerted_at` empêche de rejouer l'alerte quand on republie l'article après
 * une correction de faute de frappe. `reserverEnvoi()` empêche, à l'intérieur
 * d'un même envoi, qu'une adresse soit servie deux fois. Les deux sont
 * nécessaires : le premier protège du geste humain, le second de la reprise
 * technique.
 */
final class AlerterSurUneIntox implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(private readonly int $articleId) {}

    public function handle(
        DepotAbonnes $depot,
        Infolettre $infolettre,
        RenduDeGabarit $rendu,
    ): void {
        $article = HubArticle::find($this->articleId);

        if ($article === null || ! $article->is_published || ! $article->is_urgent) {
            return;
        }

        if ($article->alerted_at !== null) {
            // Republication après correction : l'alerte est déjà partie.
            return;
        }

        if (! $rendu->estAllume('alerte_intox')) {
            /*
             | L'interrupteur est relu ICI et pas seulement au moment du clic :
             | entre la mise en file et l'exécution, l'administration a pu
             | l'éteindre. Sur le message le plus intrusif du produit, on relit.
             */
            return;
        }

        $destinataires = $depot->compterDestinataires();

        if ($destinataires === 0) {
            return;
        }

        // Posé avant la boucle — un job repris ne réalerte pas.
        $article->forceFill(['alerted_at' => now()])->save();

        $campagne = $depot->creerCampagne('alerte-'.$article->uuid, [
            'uuid' => (string) Str::uuid(),
            'name' => Str::limit('Alerte — '.$article->title, 80),
            'subject' => Str::limit($article->title, 190),
            'body' => (string) $article->excerpt,
            'audience' => Infolettre::AUDIENCE,
            'status' => StatutCampagne::EnCours->value,
            'recipients_count' => $destinataires,
        ]);

        $lienDuFactCheck = route('public.fact', ['article' => $article->slug]);

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

                if (! $depot->reserverEnvoi($campagne, $abonne)) {
                    continue;
                }

                try {
                    Mail::to($abonne->email)->send(new AlerteIntox(
                        variables: [
                            'prenom' => $abonne->name,
                            'titre' => $article->title,
                            'resume' => (string) $article->excerpt,
                        ],
                        lienDuFactCheck: $lienDuFactCheck,
                        lienDeDesabonnement: $infolettre->lienDeDesabonnement($abonne),
                    ));

                    $depot->marquerEnvoye($campagne, $abonne);
                    $envoyes++;
                } catch (Throwable $e) {
                    // Une boîte pleine ne doit pas priver les suivantes.
                    $depot->marquerEchoue($campagne, $abonne, $e->getMessage());
                    $echecs++;
                }
            }
        }

        $depot->recalerCompteurs($campagne, $destinataires, StatutCampagne::Envoyee->value);

        if ($echecs > 0) {
            Log::warning('Alerte intox : envoi partiel.', [
                'article' => $article->uuid,
                'envoyes' => $envoyes,
                'echecs' => $echecs,
            ]);
        }
    }
}
