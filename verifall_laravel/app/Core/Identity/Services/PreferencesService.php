<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Enums\ConsentType;
use App\Enums\PreferenceNotification;
use App\Models\User;

/**
 * Préférences de notification du citoyen.
 *
 * Deux natures distinctes derrière des interrupteurs identiques, et c'est
 * précisément ce qui justifie ce service :
 *
 *   · les notifications de SERVICE (résultat d'une vérification, rappel de
 *     cours) sont un réglage de confort, stocké tel quel ;
 *   · la lettre d'information est un CONSENTEMENT marketing. Le RGPD exige
 *     qu'il soit horodaté, rattaché à une version de document et révocable —
 *     et que le retrait soit aussi simple que l'accord. Il passe donc par
 *     `consents`, pas par la colonne de préférences.
 *
 * Confondre les deux ferait perdre la preuve du consentement le jour où elle
 * est réclamée.
 */
final class PreferencesService
{
    public function __construct(private readonly ConsentService $consentements) {}

    /**
     * État courant de toutes les préférences de service.
     *
     * @return array<string, bool>
     */
    public function toutes(User $utilisateur): array
    {
        $stockees = $utilisateur->notification_preferences ?? [];
        $etat = [];

        foreach (PreferenceNotification::cases() as $preference) {
            // L'absence de clé vaut « valeur par défaut » : ajouter une
            // préférence ne doit pas obliger à rétro-remplir tous les comptes.
            $etat[$preference->value] = (bool) ($stockees[$preference->value] ?? $preference->defaut());
        }

        return $etat;
    }

    public function estActive(User $utilisateur, PreferenceNotification $preference): bool
    {
        return $this->toutes($utilisateur)[$preference->value];
    }

    public function definir(User $utilisateur, PreferenceNotification $preference, bool $active): void
    {
        $etat = $this->toutes($utilisateur);
        $etat[$preference->value] = $active;

        // forceFill : les préférences ne sont pas dans $fillable, pour qu'une
        // requête ne puisse jamais les écrire au passage d'un autre formulaire.
        $utilisateur->forceFill(['notification_preferences' => $etat])->save();
    }

    public function newsletterActive(User $utilisateur): bool
    {
        return $this->consentements->hasGranted($utilisateur, ConsentType::Newsletter);
    }

    /**
     * Accorde ou retire le consentement à la lettre d'information.
     *
     * Chaque bascule ajoute une LIGNE au journal des consentements plutôt que
     * d'en modifier une : l'historique des accords et des retraits est
     * exactement ce qu'il faut pouvoir produire en cas de contrôle.
     */
    public function definirNewsletter(User $utilisateur, bool $accepte, ?string $ip, ?string $agent): void
    {
        $this->consentements->record($utilisateur, ConsentType::Newsletter, $accepte, $ip, $agent);
    }
}
