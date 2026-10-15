<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Enums\StatutMessageContact;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Tout ce que la boîte de contact lit et écrit — et rien d'autre.
 *
 * Ce qui n'est PAS ici : l'envoi du courriel de notification. Le dépôt sait
 * QUOI enregistrer, pas comment prévenir. C'est ce qui permet d'éprouver la
 * limite de cadence et le piège à robots sans serveur SMTP.
 */
interface DepotMessagesContact
{
    /**
     * Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL.
     *
     * `email` n'y figure pas volontairement : interrogeable (recherche),
     * jamais triable — comme `extrait`, `natureLibelle` ou `statutLibelle`,
     * qui n'ont pas de colonne SQL correspondante.
     */
    public const COLONNES_TRIABLES = ['expediteur', 'statut', 'nature', 'recuLe'];

    /**
     * La boîte de réception, pour la table DataTables du back-office.
     *
     * @return PageDeResultats<ContactMessage>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    /** @param array<string, mixed> $donnees */
    public function enregistrer(array $donnees): ContactMessage;

    public function parUuid(string $uuid): ?ContactMessage;

    /**
     * Combien d'envois depuis cette empreinte d'IP sur la fenêtre donnée.
     *
     * Second rideau derrière `throttle` : celui-ci compte les messages
     * RÉELLEMENT écrits, donc il survit à un changement de session ou de
     * cookie, là où la limite de cadence de Laravel s'appuie aussi sur elles.
     */
    public function envoisRecents(string $empreinteIp, int $minutes): int;

    /**
     * @param  string|null  $nature  'contact' | 'don', ou null pour tout
     * @return LengthAwarePaginator<int, ContactMessage>
     */
    public function paginer(
        ?string $recherche,
        ?StatutMessageContact $statut,
        int $parPage,
        ?string $nature = null,
    ): LengthAwarePaginator;

    /** @return array<string, int> */
    public function compteurs(): array;

    public function changerStatut(
        ContactMessage $message,
        StatutMessageContact $statut,
        User $acteur,
        ?string $note = null,
    ): ContactMessage;
}
