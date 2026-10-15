<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Enums\ConsentType;
use App\Models\Consent;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;

/**
 * Registre des consentements (RGPD — SECURITE_RGPD §5).
 *
 * ⚠️ ÉCART ASSUMÉ AVEC LE PROTOTYPE.
 * L'écran d'inscription du prototype ne comporte AUCUNE case de consentement,
 * ni mention des CGU ou de la politique de confidentialité — vérifié : zéro
 * occurrence. C'est un manquement au RGPD, pas un choix de design. Les cases
 * sont donc ajoutées, ce qui constitue le seul ajout structurel au prototype
 * de tout le Sprint 1, et il est ici documenté plutôt que discret.
 */
final class ConsentService
{
    public function __construct(private readonly IdentityHasher $hasher) {}

    /**
     * Enregistre un consentement (octroi ou retrait).
     *
     * Toujours une insertion, jamais une mise à jour : l'historique est la
     * preuve. Écraser la ligne précédente reviendrait à effacer le fait que la
     * personne avait consenti à une version antérieure du texte.
     */
    public function record(
        User $user,
        ConsentType $type,
        bool $granted = true,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Consent {
        return Consent::create([
            'user_id' => $user->id,
            'type' => $type,
            'document_version' => $type->versionCourante(),
            'granted' => $granted,
            'ip_hash' => $ip !== null ? $this->hasher->hash($ip) : null,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Enregistre en une fois les consentements donnés à l'inscription.
     *
     * @param  array<string, bool>  $reponses  type => accepté
     * @return list<Consent>
     */
    public function recordMany(
        User $user,
        array $reponses,
        ?string $ip = null,
        ?string $userAgent = null,
    ): array {
        $traces = [];

        foreach ($reponses as $type => $accepte) {
            $enum = ConsentType::tryFrom($type);

            if ($enum === null) {
                continue;
            }

            // Un refus est tracé aussi : pouvoir prouver que la newsletter n'a
            // PAS été acceptée protège autant que l'inverse.
            $traces[] = $this->record($user, $enum, $accepte, $ip, $userAgent);
        }

        return $traces;
    }

    /**
     * État courant d'un consentement : la trace la plus récente fait foi.
     */
    public function hasGranted(User $user, ConsentType $type): bool
    {
        $dernier = Consent::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        return $dernier?->granted ?? false;
    }

    /**
     * Le citoyen a-t-il accepté tout ce qui est obligatoire, dans la version
     * courante des textes ?
     *
     * Une acceptation portant sur une version périmée ne suffit pas : c'est ce
     * qui permettra de re-solliciter après une refonte des CGU.
     */
    public function aJourSurLesObligatoires(User $user): bool
    {
        foreach (ConsentType::cases() as $type) {
            if (! $type->estObligatoire()) {
                continue;
            }

            $dernier = Consent::query()
                ->where('user_id', $user->id)
                ->where('type', $type->value)
                ->latest('recorded_at')
                ->latest('id')
                ->first();

            if ($dernier === null
                || ! $dernier->granted
                || $dernier->document_version !== $type->versionCourante()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Consentements obligatoires, pour l'affichage du formulaire.
     *
     * @return list<ConsentType>
     */
    public static function obligatoires(): array
    {
        return array_values(array_filter(
            ConsentType::cases(),
            static fn (ConsentType $t): bool => $t->estObligatoire(),
        ));
    }
}
