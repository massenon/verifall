<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Application\Reglages\Reglages;
use App\Enums\TypeContenu;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Vérifie et comptabilise le quota journalier de vérifications multimédia.
 *
 * Le quota s'applique aux types Image, Audio et Vidéo — pas au texte ni aux
 * liens, qui ne mobilisent pas de moteurs lourds. Il cible les comptes gratuits
 * et les invités ; les rôles Admin, Modérateur et Mentor sont exempts.
 *
 * La clé de cache inclut la DATE du jour : elle change naturellement à minuit,
 * ce qui réinitialise le compteur sans planificateur ni purge.
 *
 * Utilisation :
 *
 *   $quota->verifier($type, $utilisateur);            // lève si dépassé
 *   $quota->enregistrer($type, $utilisateur);         // incrémente après succès
 *
 * Les deux sont séparés : `verifier()` avant la soumission, `enregistrer()`
 * après — la soumission peut encore être refusée (solde insuffisant, module
 * fermé) entre les deux.
 */
final class QuotaMediaService
{
    /** Les types concernés par la limite journalière. */
    private const TYPES_CONTINGENTES = [
        TypeContenu::Image,
        TypeContenu::Audio,
        TypeContenu::Video,
    ];

    /** Les rôles exemptés du quota. */
    private const ROLES_EXEMPTS = ['Admin', 'Modérateur', 'Mentor'];

    /** Secondes dans 25 heures — TTL de sécurité pour le cache. */
    private const TTL = 90000;

    public function __construct(
        private readonly Reglages $reglages,
    ) {}

    /**
     * Vérifie que l'utilisateur n'a pas dépassé son quota journalier.
     *
     * Ne fait rien si le type n'est pas contingent, ou si l'utilisateur est
     * exempté. Lève {@see QuotaMediaAtteint} si le quota est épuisé.
     *
     * @throws QuotaMediaAtteint
     */
    public function verifier(TypeContenu $type, ?User $utilisateur): void
    {
        if (! $this->estContingente($type)) {
            return;
        }

        if ($utilisateur !== null && $this->estExempt($utilisateur)) {
            return;
        }

        $limite = $this->limite();
        $cle = $this->cle($utilisateur);
        $compteur = (int) Cache::get($cle, 0);

        if ($compteur >= $limite) {
            throw QuotaMediaAtteint::pour($limite);
        }
    }

    /**
     * Incrémente le compteur après une soumission acceptée.
     *
     * Appelé APRÈS que la soumission a réussi, jamais avant.
     */
    public function enregistrer(TypeContenu $type, ?User $utilisateur): void
    {
        if (! $this->estContingente($type)) {
            return;
        }

        if ($utilisateur !== null && $this->estExempt($utilisateur)) {
            return;
        }

        $cle = $this->cle($utilisateur);
        $compteur = (int) Cache::get($cle, 0);
        Cache::put($cle, $compteur + 1, self::TTL);
    }

    /**
     * Vérifications multimédia restantes pour cet utilisateur aujourd'hui.
     *
     * Pour l'affichage uniquement — jamais pour autoriser. Entre cet appel et
     * la soumission, le compteur peut avoir bougé.
     */
    public function restantes(TypeContenu $type, ?User $utilisateur): int
    {
        if (! $this->estContingente($type)) {
            return PHP_INT_MAX;
        }

        if ($utilisateur !== null && $this->estExempt($utilisateur)) {
            return PHP_INT_MAX;
        }

        $limite = $this->limite();
        $compteur = (int) Cache::get($this->cle($utilisateur), 0);

        return max(0, $limite - $compteur);
    }

    private function estContingente(TypeContenu $type): bool
    {
        return in_array($type, self::TYPES_CONTINGENTES, true);
    }

    private function estExempt(User $utilisateur): bool
    {
        foreach (self::ROLES_EXEMPTS as $role) {
            if ($utilisateur->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    private function limite(): int
    {
        return max(1, (int) $this->reglages->get('quota_media_journalier', '2'));
    }

    /**
     * Clé de cache journalière, unique à l'utilisateur.
     *
     * La date est incluse dans la clé : quand le jour bascule, la clé change
     * et le compteur repart de zéro sans purge ni planificateur.
     *
     * Les invités (`null`) partagent un compteur global par jour — le quota
     * s'applique globalement aux invités, pas individuellement (contrairement
     * à l'espace citoyen où chaque compte a le sien). Ce comportement est
     * intentionnel : un invité ne peut pas être identifié de façon fiable.
     */
    private function cle(?User $utilisateur): string
    {
        $date = now()->toDateString();

        return $utilisateur !== null
            ? "quota_media:{$utilisateur->id}:{$date}"
            : "quota_media:invite:{$date}";
    }
}
