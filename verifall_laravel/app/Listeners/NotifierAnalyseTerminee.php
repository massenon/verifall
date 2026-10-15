<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Identity\Notifications\AnalyseTerminee;
use App\Events\VerificationAchevee;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prévient la personne que son analyse est prête.
 *
 * ## Pourquoi un écouteur et non un appel dans l'orchestrateur
 *
 * Pour la même raison que les récompenses en ont un : le fact-checking n'a
 * aucune raison de connaître la cloche. Sans cette séparation, chaque
 * nouveauté d'interface viendrait modifier le cœur du produit — et c'est le
 * cœur qui porte l'argent des gens.
 *
 * ## Les deux défauts corrigés ici, et pourquoi ils étaient graves
 *
 * **1. Une relation chargée à la volée, dans une transaction.**
 *
 * La première version lisait `$evenement->verification->user` — une relation
 * non chargée. L'événement est émis DANS `DB::transaction()`, et le job
 * charge le modèle par un simple `Verification::find()`. Avec
 * `preventLazyLoading(! isProduction())`, cette lecture LÈVE.
 *
 * Ce n'était donc pas « une notification qui manque » : l'exception remontait
 * dans la transaction et annulait TOUT — le rapport, les preuves, les appels
 * de moteurs facturés, le remboursement, le statut. En développement et en
 * recette, chaque analyse achevée se serait défaite au dernier instant.
 *
 * La suite de tests ne pouvait pas le voir : elle passe un modèle qu'elle
 * vient de créer, et `wasRecentlyCreated` supprime la violation.
 *
 * **2. La première instruction hors du filet.**
 *
 * Un écouteur de notification ne doit JAMAIS pouvoir défaire une analyse
 * payée. Tout est donc dans le try/catch, y compris la lecture de la relation.
 */
final class NotifierAnalyseTerminee
{
    public function handle(VerificationAchevee $evenement): void
    {
        try {
            $verification = $evenement->verification;

            /*
             | On lit la clé étrangère, PAS la relation. `user_id` est une
             | colonne déjà chargée ; `->user` déclencherait une requête que
             | `preventLazyLoading` interdit — dans une transaction.
             */
            $auteurId = $verification->getAttribute('user_id');

            if ($auteurId === null) {
                // Vérification faite en invité : personne à prévenir.
                return;
            }

            $auteur = User::find($auteurId);

            if ($auteur === null) {
                // Compte supprimé entre la soumission et la fin de l'analyse.
                return;
            }

            $auteur->notify(new AnalyseTerminee($verification));
        } catch (Throwable $souci) {
            /*
             | Journalisé, jamais propagé.
             |
             | Cet écouteur tourne dans la transaction qui valide l'analyse.
             | Laisser filer une exception ferait perdre à quelqu'un le rapport
             | qu'il vient de payer — pour un message qui n'a pas pu partir.
             */
            Log::warning('Notification de fin d’analyse non envoyée : '.$souci->getMessage(), [
                'verification' => $evenement->verification->getKey(),
            ]);
        }
    }
}
