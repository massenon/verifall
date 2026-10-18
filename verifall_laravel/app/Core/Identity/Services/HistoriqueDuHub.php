<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Enums\ConsentType;
use App\Models\ConsultationHub;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * L'historique des consultations du Hub — UNIQUEMENT avec consentement.
 *
 * Ce qu'une personne lit dit ses centres d'intérêt, sa santé, sa recherche
 * d'emploi : c'est du profilage (RGPD art. 4.4), et aucune finalité de sécurité
 * ne le justifie. Base légale : le consentement (art. 6.1.a), recueilli à part,
 * jamais pré-coché, retirable à tout moment (arbitrage n° 178).
 *
 *  - Sans consentement en vigueur, `noter()` n'écrit RIEN. Le comptage anonyme
 *    des visites (`SuiviDesVisites`) continue, lui, sans rattachement.
 *  - Le retrait du consentement efface l'historique sur-le-champ : garder ce
 *    qu'on n'a plus le droit de traiter n'a pas de base légale.
 *  - Conservation : 90 jours.
 */
final class HistoriqueDuHub
{
    public function __construct(private readonly ConsentService $consentements) {}

    public function estActif(User $utilisateur): bool
    {
        return $this->consentements->hasGranted($utilisateur, ConsentType::HistoriqueHub);
    }

    /**
     * @param  'actualite'|'portail'|'offre'  $rubrique
     */
    public function noter(?User $utilisateur, string $rubrique, int $cibleId, string $titre, string $action): ?ConsultationHub
    {
        if ($utilisateur === null || ! $this->estActif($utilisateur)) {
            return null;
        }

        // Une lecture par jour et par contenu suffit : la même page rouverte
        // dix fois n'apprend rien de plus, et grossirait l'historique.
        $deja = ConsultationHub::query()
            ->where('user_id', $utilisateur->id)
            ->where('rubrique', $rubrique)
            ->where('cible_id', $cibleId)
            ->where('action', $action)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();

        if ($deja) {
            return null;
        }

        return ConsultationHub::query()->create([
            'user_id' => $utilisateur->id,
            'rubrique' => $rubrique,
            'cible_id' => $cibleId,
            'titre' => Str::limit($titre, 197),
            'action' => $action,
        ]);
    }

    /**
     * Accorde ou retire le consentement. Le retrait efface l'historique.
     */
    public function definir(User $utilisateur, bool $accepte, ?string $ip, ?string $agent): void
    {
        $this->consentements->record($utilisateur, ConsentType::HistoriqueHub, $accepte, $ip, $agent);

        if (! $accepte) {
            $this->effacer($utilisateur);
        }
    }

    public function effacer(User $utilisateur): int
    {
        return ConsultationHub::query()->where('user_id', $utilisateur->id)->delete();
    }

    /**
     * @return Collection<int, ConsultationHub>
     */
    public function pour(User $utilisateur, int $limite = 50): Collection
    {
        return ConsultationHub::query()
            ->where('user_id', $utilisateur->id)
            ->latest('created_at')->latest('id')
            ->limit($limite)
            ->get();
    }

    public function purger(?Carbon $avant = null): int
    {
        return ConsultationHub::query()
            ->where('created_at', '<', $avant ?? now()->subDays(ConsultationHub::JOURS_DE_CONSERVATION))
            ->delete();
    }
}
