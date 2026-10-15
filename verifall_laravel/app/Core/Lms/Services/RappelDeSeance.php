<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Models\LiveClass;
use App\Models\LiveClassRegistration;
use App\Models\User;

/**
 * S'inscrire à une séance en direct pour en être rappelé.
 *
 * CE QUE FAIT CE SERVICE, ET SEULEMENT CELA : enregistrer que quelqu'un veut
 * assister. La table `live_class_registrations` existe depuis le sprint LMS,
 * avec sa contrainte d'unicité `(user_id, live_class_id)` — une inscription
 * par personne et par séance, garantie par le schéma et non par le code.
 *
 * `firstOrCreate` et non `create` : un double appui, ou une requête rejouée
 * depuis la file hors ligne du téléphone, ne doit pas produire deux lignes. La
 * contrainte les refuserait, mais avec une exception au visage de la personne.
 *
 * CE QUE CE SERVICE NE FAIT PAS — et il faut le dire franchement : il
 * n'ENVOIE aucun rappel. La notification J-1 suppose une infrastructure de
 * notifications qui n'existe pas encore sur cette plateforme (voir la réserve
 * du contrat d'API). L'intention, elle, est enregistrée durablement : le jour
 * où la notification arrivera, elle aura sa liste de destinataires, et
 * personne n'aura à redemander aux gens de se réinscrire.
 *
 * On n'inscrit PAS à une séance passée : le rappel n'aurait plus d'objet, et
 * la liste de présence deviendrait un registre de gens qui n'y étaient pas.
 */
final class RappelDeSeance
{
    public function demander(User $utilisateur, LiveClass $seance): ?LiveClassRegistration
    {
        if ($seance->estPassee() || ! $seance->is_published) {
            return null;
        }

        return LiveClassRegistration::firstOrCreate([
            'user_id' => $utilisateur->id,
            'live_class_id' => $seance->id,
        ]);
    }

    /** Cette personne est-elle déjà inscrite ? */
    public function dejaDemande(User $utilisateur, LiveClass $seance): bool
    {
        return LiveClassRegistration::where('user_id', $utilisateur->id)
            ->where('live_class_id', $seance->id)
            ->exists();
    }
}
