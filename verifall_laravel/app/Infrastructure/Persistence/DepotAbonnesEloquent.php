<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Marketing\Enums\StatutCampagne;
use App\Models\Campaign;
use App\Models\HubArticle;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Le dépôt de l'infolettre, en Eloquent.
 *
 * Une adresse e-mail est toujours normalisée AVANT d'atteindre la base :
 * minuscules et espaces retirés. Sans cela « Rene@Verifall.com » et
 * « rene@verifall.com  » créent deux lignes, la personne reçoit deux
 * exemplaires, et son désabonnement n'en éteint qu'un.
 */
final class DepotAbonnesEloquent implements DepotAbonnes
{
    /** Clé publique → colonne SQL. Tout ce qui n'y figure pas n'atteint jamais `orderBy()`. */
    private const COLONNES_SQL = [
        'email' => 'email',
        'nom' => 'name',
        'inscritLe' => 'created_at',
        'confirmeLe' => 'confirmed_at',
    ];

    // ═════════════════════════════════════════════════════════════ abonnés

    /** @return PageDeResultats<NewsletterSubscriber> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = NewsletterSubscriber::query()->count();

        $filtree = NewsletterSubscriber::query();
        $this->filtrerPourTableau($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        if ($requete->tri === []) {
            $filtree->orderByDesc('id');
        } else {
            foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
                $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
            }
        }

        $lignes = $filtree
            ->skip($requete->debut)
            ->take($requete->longueur)
            ->get();

        return new PageDeResultats(
            total: $total,
            filtres: $nombreFiltre,
            lignes: $lignes,
            tronque: $requete->export && $nombreFiltre > $requete->longueur,
        );
    }

    /**
     * @param  Builder<NewsletterSubscriber>  $requete
     */
    private function filtrerPourTableau(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $terme = '%'.$criteres->recherche.'%';
            $requete->where(fn (Builder $sous) => $sous
                ->where('email', 'like', $terme)
                ->orWhere('name', 'like', $terme));
        }

        match ($filtres['etat'] ?? null) {
            'confirmes' => $requete->whereNotNull('confirmed_at')->whereNull('unsubscribed_at'),
            'en-attente' => $requete->whereNull('confirmed_at')->whereNull('unsubscribed_at'),
            'desabonnes' => $requete->whereNotNull('unsubscribed_at'),
            default => null,
        };

        if (isset($filtres['inscrit_du'])) {
            $requete->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $filtres['inscrit_du'])?->startOfDay());
        }

        if (isset($filtres['inscrit_au'])) {
            $requete->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $filtres['inscrit_au'])?->endOfDay());
        }
    }

    public function parEmail(string $email): ?NewsletterSubscriber
    {
        return NewsletterSubscriber::where('email', self::normaliser($email))->first();
    }

    public function parJetonDeConfirmation(string $jeton): ?NewsletterSubscriber
    {
        if ($jeton === '') {
            return null;
        }

        return NewsletterSubscriber::where('confirmation_token', $jeton)->first();
    }

    public function parUuid(string $uuid): ?NewsletterSubscriber
    {
        return NewsletterSubscriber::where('uuid', $uuid)->first();
    }

    public function creer(array $attributs): NewsletterSubscriber
    {
        $attributs['email'] = self::normaliser((string) ($attributs['email'] ?? ''));
        $attributs['uuid'] ??= (string) Str::uuid();

        return NewsletterSubscriber::create($attributs);
    }

    public function reinitialiserConfirmation(
        NewsletterSubscriber $abonne,
        string $jeton,
        ?string $nom,
        ?string $empreinteIp,
    ): NewsletterSubscriber {
        $abonne->forceFill([
            'confirmation_token' => $jeton,
            /*
             | La confirmation repart de zéro, même si l'adresse était déjà
             | confirmée avant un désabonnement. Un consentement retiré ne se
             | reprend pas d'un clic sur un vieux formulaire : il se redonne.
             */
            'confirmed_at' => null,
            'unsubscribed_at' => null,
            'name' => $nom ?? $abonne->name,
            'consent_ip_hash' => $empreinteIp ?? $abonne->consent_ip_hash,
        ])->save();

        return $abonne->refresh();
    }

    public function confirmer(NewsletterSubscriber $abonne): NewsletterSubscriber
    {
        $abonne->forceFill([
            'confirmed_at' => $abonne->confirmed_at ?? now(),
            'unsubscribed_at' => null,
        ])->save();

        return $abonne->refresh();
    }

    public function desabonner(NewsletterSubscriber $abonne): NewsletterSubscriber
    {
        /*
         | `confirmed_at` est CONSERVÉ. C'est la preuve que le consentement a
         | existé — exigée si quelqu'un conteste avoir été démarché — et
         | l'effacer au retrait la détruirait précisément au moment où elle
         | devient utile.
         */
        $abonne->forceFill(['unsubscribed_at' => $abonne->unsubscribed_at ?? now()])->save();

        return $abonne->refresh();
    }

    public function destinataires(int $taille, int $apresId = 0): Collection
    {
        return $this->destinatairesQuery()
            ->where('id', '>', $apresId)
            ->orderBy('id')
            ->limit($taille)
            ->get();
    }

    public function compterDestinataires(): int
    {
        return $this->destinatairesQuery()->count();
    }

    public function indicateurs(): array
    {
        return [
            'confirmes' => $this->destinatairesQuery()->count(),
            'en_attente' => NewsletterSubscriber::whereNull('confirmed_at')
                ->whereNull('unsubscribed_at')->count(),
            'desabonnes' => NewsletterSubscriber::whereNotNull('unsubscribed_at')->count(),
            'nouveaux_30j' => NewsletterSubscriber::whereNotNull('confirmed_at')
                ->where('confirmed_at', '>=', now()->subDays(30))->count(),
        ];
    }

    public function paginer(string $recherche, string $filtre, int $parPage): LengthAwarePaginator
    {
        return NewsletterSubscriber::query()
            ->when($recherche !== '', function (Builder $q) use ($recherche): void {
                $terme = '%'.mb_strtolower(trim($recherche)).'%';
                $q->where(fn (Builder $sous) => $sous
                    ->whereRaw('LOWER(email) LIKE ?', [$terme])
                    ->orWhereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$terme]));
            })
            ->when($filtre === 'confirmes', fn (Builder $q) => $q
                ->whereNotNull('confirmed_at')->whereNull('unsubscribed_at'))
            ->when($filtre === 'en-attente', fn (Builder $q) => $q
                ->whereNull('confirmed_at')->whereNull('unsubscribed_at'))
            ->when($filtre === 'desabonnes', fn (Builder $q) => $q->whereNotNull('unsubscribed_at'))
            ->orderByDesc('id')
            ->paginate($parPage);
    }

    // ══════════════════════════════════════════════════ éditions et envois

    public function campagneDeLEdition(string $cleEdition): ?Campaign
    {
        return Campaign::where('edition_key', $cleEdition)->first();
    }

    public function creerCampagne(string $cleEdition, array $attributs): Campaign
    {
        /*
         | `forceFill` : `edition_key` n'est pas au `$fillable` de `Campaign`,
         | modèle qui n'appartient pas à ce lot. Le passer de force est
         | préférable à toucher un fichier partagé pendant que trois autres
         | agents écrivent — voir l'arbitrage 159.
         */
        $campagne = new Campaign;
        $campagne->forceFill($attributs + [
            'uuid' => (string) Str::uuid(),
            'edition_key' => $cleEdition,
        ])->save();

        return $campagne;
    }

    public function reserverEnvoi(Campaign $campagne, NewsletterSubscriber $abonne): bool
    {
        try {
            NewsletterDelivery::create([
                'campaign_id' => $campagne->id,
                'newsletter_subscriber_id' => $abonne->id,
            ]);

            return true;
        } catch (QueryException) {
            /*
             | La contrainte d'unicité a parlé : cette adresse est déjà servie
             | sur cette édition. Ce n'est PAS une erreur — c'est exactement le
             | comportement attendu d'une commande rejouée, et le seul qui
             | tienne quand deux processus tournent en même temps.
             */
            return false;
        }
    }

    public function marquerEnvoye(Campaign $campagne, NewsletterSubscriber $abonne): void
    {
        $this->trace($campagne, $abonne)?->forceFill([
            'sent_at' => now(),
            'failed_at' => null,
            'error' => null,
        ])->save();
    }

    public function marquerEchoue(Campaign $campagne, NewsletterSubscriber $abonne, string $erreur): void
    {
        $this->trace($campagne, $abonne)?->forceFill([
            'sent_at' => null,
            'failed_at' => now(),
            'error' => mb_substr($erreur, 0, 500),
        ])->save();
    }

    public function recalerCompteurs(Campaign $campagne, int $destinataires, ?string $statut = null): Campaign
    {
        $envoyes = NewsletterDelivery::where('campaign_id', $campagne->id)
            ->whereNotNull('sent_at')->count();
        $echoues = NewsletterDelivery::where('campaign_id', $campagne->id)
            ->whereNotNull('failed_at')->count();

        $campagne->forceFill([
            'recipients_count' => $destinataires,
            'sent_count' => $envoyes,
            'failed_count' => $echoues,
            'status' => $statut ?? $campagne->status,
            'sent_at' => $statut === StatutCampagne::Envoyee->value
                ? ($campagne->sent_at ?? now())
                : $campagne->sent_at,
        ])->save();

        return $campagne->refresh();
    }

    public function historique(int $parPage): LengthAwarePaginator
    {
        return Campaign::query()
            ->where('audience', 'subscribers')
            ->whereNotNull('edition_key')
            ->orderByDesc('id')
            ->paginate($parPage);
    }

    public function derniereEditionEnvoyee(): ?Campaign
    {
        return Campaign::whereNotNull('edition_key')
            ->where('status', StatutCampagne::Envoyee->value)
            ->whereNotNull('sent_at')
            ->orderByDesc('sent_at')
            ->first();
    }

    // ═══════════════════════════════════════════════════ matière du récap

    public function factChecksPublies(CarbonInterface $debut, CarbonInterface $fin, int $limite): Collection
    {
        return HubArticle::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$debut, $fin])
            ->orderByDesc('published_at')
            ->limit($limite)
            ->get();
    }

    // ═════════════════════════════════════════════════════════════ privé

    private function trace(Campaign $campagne, NewsletterSubscriber $abonne): ?NewsletterDelivery
    {
        return NewsletterDelivery::where('campaign_id', $campagne->id)
            ->where('newsletter_subscriber_id', $abonne->id)
            ->first();
    }

    /**
     * @return Builder<NewsletterSubscriber>
     */
    private function destinatairesQuery(): Builder
    {
        return NewsletterSubscriber::query()
            ->whereNotNull('confirmed_at')
            ->whereNull('unsubscribed_at');
    }

    private static function normaliser(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
