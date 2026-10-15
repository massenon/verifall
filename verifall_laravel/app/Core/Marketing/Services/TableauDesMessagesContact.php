<?php

declare(strict_types=1);

namespace App\Core\Marketing\Services;

use App\Core\Application\Contracts\DepotMessagesContact;
use App\Enums\NatureMessageContact;
use App\Enums\StatutMessageContact;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * La boîte de réception du formulaire de contact, telle que DataTables la reçoit.
 *
 * Clone du pilote `TableauDesUtilisateurs` (contrat : `docs/DATATABLES_CONTRAT.md`).
 *
 * ## Ce que ce service décide
 *
 * 1. **Qui voit la boîte** : `viewAny` de `ContactMessagePolicy`
 *    (`contact_message.view` — administrateur seulement ; le modérateur et le
 *    mentor n'y entrent pas), rejoué ici même si la route la garde déjà.
 * 2. **Pas d'export.** Aucun `contact_message.export` dans la matrice (§8).
 * 3. **Ce qui sort** : ce que la liste d'avant affichait, pas davantage — le
 *    nom et l'adresse de l'expéditeur (c'est à cette adresse qu'on répond, et
 *    c'est elle qui trahit un envoi indésirable), un EXTRAIT du message tronqué
 *    ici, la nature, le statut, la date. Jamais le téléphone, l'organisation,
 *    l'empreinte d'IP, la note de traitement ni le montant déclaré.
 *
 * Le message est du texte libre écrit par un inconnu : il part en texte brut,
 * et le navigateur l'échappe à l'affichage.
 */
final readonly class TableauDesMessagesContact
{
    /** Même longueur que l'extrait de la liste Livewire d'avant. */
    public const EXTRAIT_MAXIMUM = 110;

    public function __construct(private DepotMessagesContact $depot) {}

    /**
     * Les filtres rapides, adossés aux enums réels et à `created_at`.
     * `statut` et `nature` reprennent les valeurs de `?statut=` et `?nature=`.
     *
     * @return array<string, list<string>|'date'>
     */
    public static function filtresAutorises(): array
    {
        return [
            'statut' => array_column(StatutMessageContact::cases(), 'value'),
            'nature' => array_column(NatureMessageContact::cases(), 'value'),
            'recu_du' => 'date',
            'recu_au' => 'date',
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
        $porte->authorize('viewAny', ContactMessage::class);

        if ($requete->export) {
            throw new AuthorizationException("Aucune permission d'export n'est déclarée pour la boîte de contact.");
        }

        $page = $this->depot->pourTableau($requete);

        $lignes = [];
        foreach ($page->lignes as $message) {
            $texte = trim((string) preg_replace('/\s+/u', ' ', (string) $message->message));

            $lignes[] = [
                'uuid' => (string) $message->uuid,
                'nature' => $message->kind->value,
                'natureLibelle' => $message->kind->libelle(),
                'natureBadge' => $message->kind->cleBadge(),
                'expediteur' => (string) $message->name,
                'email' => (string) $message->email,
                'extrait' => Str::limit($texte, self::EXTRAIT_MAXIMUM),
                'statut' => $message->status->value,
                'statutLibelle' => $message->status->libelle(),
                'statutBadge' => $message->status->cleBadge(),
                'recuLe' => $message->created_at?->toDateString(),
                'recuLeTexte' => $message->created_at?->translatedFormat('j M Y'),
                'actions' => [
                    // `ouvrir()` n'a pas d'autre garde que l'accès à l'écran.
                    'ouvrir' => true,
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
