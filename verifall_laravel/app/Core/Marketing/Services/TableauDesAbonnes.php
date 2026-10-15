<?php

declare(strict_types=1);

namespace App\Core\Marketing\Services;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * L'onglet « Abonnés » de l'infolettre, tel que DataTables le reçoit.
 *
 * Clone du pilote `TableauDesUtilisateurs` (contrat : `docs/DATATABLES_CONTRAT.md`).
 *
 * ## Ce que ce service décide
 *
 * 1. **Qui voit la liste** : `viewAny` de `NewsletterSubscriberPolicy`
 *    (`newsletter.view` — administrateur seulement ; ni mentor ni modérateur).
 * 2. **Pas d'export.** La liste d'abonnés est le fichier d'adresses le plus
 *    facilement exfiltrable de la plateforme, et `MatricePermissions` ne
 *    déclare aucun `newsletter.export` : `export=1` répond 403 (§8).
 * 3. **Ce qui sort** : l'adresse et le nom — ce qu'il faut pour reconnaître la
 *    personne qui appelle le support —, les trois dates qui racontent le
 *    consentement, et l'état. JAMAIS les deux jetons (confirmer ou désabonner
 *    au nom d'autrui), jamais l'empreinte d'IP du consentement.
 * 4. **L'action** : « Désabonner » n'est proposé qu'à qui détient
 *    `newsletter.send` (la garde de `Infolettre::retirer()`), et seulement sur
 *    une adresse encore abonnée.
 */
final readonly class TableauDesAbonnes
{
    public function __construct(private DepotAbonnes $depot) {}

    /**
     * Les filtres rapides, et leurs seules valeurs acceptées. `etat` reprend
     * les valeurs de `?etat=` de l'écran d'avant ; la période porte sur
     * `created_at`, la date de saisie de l'adresse.
     *
     * @return array<string, list<string>|'date'>
     */
    public static function filtresAutorises(): array
    {
        return [
            'etat' => ['confirmes', 'en-attente', 'desabonnes'],
            'inscrit_du' => 'date',
            'inscrit_au' => 'date',
        ];
    }

    /**
     * @return array{draw: int, recordsTotal: int, recordsFiltered: int, data: list<array<string, mixed>>, exportTronque: bool}
     *
     * @throws AuthorizationException
     */
    public function donnees(RequeteDataTables $requete, User $acteur): array
    {
        $porte = Gate::forUser($acteur);
        $porte->authorize('viewAny', NewsletterSubscriber::class);

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour la liste des abonnés.");
        }

        $page = $this->depot->pourTableau($requete);

        // La Policy ne dépend pas de la ligne : décidée une fois pour la page.
        $peutDesabonner = $porte->allows('send', NewsletterSubscriber::class);

        $lignes = [];
        foreach ($page->lignes as $abonne) {
            $etat = match (true) {
                $abonne->unsubscribed_at !== null => 'desabonne',
                $abonne->confirmed_at !== null => 'confirme',
                default => 'en_attente',
            };

            $lignes[] = [
                'uuid' => (string) $abonne->uuid,
                'email' => (string) $abonne->email,
                'nom' => $abonne->name,
                'etat' => $etat,
                'inscritLe' => $abonne->created_at?->toDateString(),
                'inscritLeTexte' => $abonne->created_at?->format('d/m/Y'),
                // La date de confirmation EST le consentement.
                'confirmeLe' => $abonne->confirmed_at?->toDateString(),
                'confirmeLeTexte' => $abonne->confirmed_at?->format('d/m/Y'),
                'desabonneLeTexte' => $abonne->unsubscribed_at?->format('d/m/Y'),
                'actions' => [
                    'desabonner' => $peutDesabonner && $abonne->unsubscribed_at === null,
                ],
            ];
        }

        return [
            'draw' => $requete->draw,
            'recordsTotal' => $page->total,
            'recordsFiltered' => $page->filtres,
            'data' => $lignes,
            'exportTronque' => false,
        ];
    }
}
