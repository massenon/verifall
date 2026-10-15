<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

use App\Models\Campaign;
use App\Models\HubArticle;
use App\Models\NewsletterSubscriber;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Tout ce que l'infolettre lit et écrit — et rien d'autre.
 *
 * ## Pourquoi UN port et non trois
 *
 * L'infolettre touche trois choses : les abonnés, les traces d'envoi, et les
 * fact-checks publiés de la semaine. Trois interfaces auraient été plus pures
 * — et auraient exigé trois liaisons dans `AppServiceProvider`, fichier de
 * couture partagé par quatre lots écrits en parallèle. Un port par CAS
 * D'USAGE, pas par table : c'est le découpage que la Clean Architecture
 * appelle de ses vœux, et il ne coûte qu'une ligne à l'intégration.
 *
 * ## Ce qui n'est PAS ici
 *
 * L'envoi. Le dépôt ne connaît ni Mailable ni transport : il sait qui écrire,
 * pas comment. C'est ce qui permet de tester l'idempotence sans serveur SMTP.
 */
interface DepotAbonnes
{
    /**
     * Clés PUBLIQUES (le `data` des colonnes), jamais des noms SQL.
     *
     * `etat` n'y figure pas : c'est une combinaison de deux colonnes
     * nullables (`confirmed_at`, `unsubscribed_at`), pas une colonne — le
     * pilote exclut de même tout ce qui n'a pas de correspondance directe.
     */
    public const COLONNES_TRIABLES = ['email', 'nom', 'inscritLe', 'confirmeLe'];

    // ═════════════════════════════════════════════════════════════ abonnés

    /**
     * La liste des abonnés, pour la table DataTables du back-office.
     *
     * @return PageDeResultats<NewsletterSubscriber>
     */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats;

    public function parEmail(string $email): ?NewsletterSubscriber;

    public function parJetonDeConfirmation(string $jeton): ?NewsletterSubscriber;

    public function parUuid(string $uuid): ?NewsletterSubscriber;

    /**
     * Crée une ligne d'abonné NON confirmée.
     *
     * @param  array<string, mixed>  $attributs
     */
    public function creer(array $attributs): NewsletterSubscriber;

    /**
     * Réarme le double opt-in sur une ligne existante.
     *
     * Employé quand une adresse déjà connue se réinscrit : un nouveau jeton,
     * la confirmation remise à zéro. Sans cela, une adresse désabonnée par
     * erreur ne pourrait jamais revenir.
     */
    public function reinitialiserConfirmation(
        NewsletterSubscriber $abonne,
        string $jeton,
        ?string $nom,
        ?string $empreinteIp,
    ): NewsletterSubscriber;

    public function confirmer(NewsletterSubscriber $abonne): NewsletterSubscriber;

    public function desabonner(NewsletterSubscriber $abonne): NewsletterSubscriber;

    /**
     * Les destinataires légitimes, par tranches, dans l'ordre des identifiants.
     *
     * Paginé par curseur et non par `offset` : un envoi qui dure et une
     * inscription qui arrive au milieu décaleraient toutes les pages
     * suivantes, et quelqu'un serait sauté sans que rien ne le signale.
     *
     * @return Collection<int, NewsletterSubscriber>
     */
    public function destinataires(int $taille, int $apresId = 0): Collection;

    public function compterDestinataires(): int;

    /**
     * Indicateurs de l'écran d'administration. Tous COMPTÉS, aucun estimé.
     *
     * @return array{confirmes: int, en_attente: int, desabonnes: int, nouveaux_30j: int}
     */
    public function indicateurs(): array;

    /**
     * @param  ''|'confirmes'|'en-attente'|'desabonnes'  $filtre
     * @return LengthAwarePaginator<int, NewsletterSubscriber>
     */
    public function paginer(string $recherche, string $filtre, int $parPage): LengthAwarePaginator;

    // ══════════════════════════════════════════════════ éditions et envois

    public function campagneDeLEdition(string $cleEdition): ?Campaign;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function creerCampagne(string $cleEdition, array $attributs): Campaign;

    /**
     * Réserve un destinataire pour une édition.
     *
     * Renvoie `false` quand la ligne existait déjà — donc quand cette adresse
     * a DÉJÀ été servie, ou l'est en ce moment même par un autre processus.
     * C'est le point exact où l'idempotence se joue.
     */
    public function reserverEnvoi(Campaign $campagne, NewsletterSubscriber $abonne): bool;

    public function marquerEnvoye(Campaign $campagne, NewsletterSubscriber $abonne): void;

    public function marquerEchoue(Campaign $campagne, NewsletterSubscriber $abonne, string $erreur): void;

    /**
     * Recale les trois compteurs de la campagne sur les traces réelles.
     *
     * Recomptés plutôt qu'incrémentés : un compteur incrémenté à chaque envoi
     * dérive dès la première reprise après incident, et un chiffre faux sur un
     * écran d'administration est pire qu'un chiffre absent.
     */
    public function recalerCompteurs(Campaign $campagne, int $destinataires, ?string $statut = null): Campaign;

    /**
     * L'historique des envois de l'infolettre, le plus récent d'abord.
     *
     * @return LengthAwarePaginator<int, Campaign>
     */
    public function historique(int $parPage): LengthAwarePaginator;

    /** La dernière édition RÉELLEMENT partie, ou null si aucune ne l'est. */
    public function derniereEditionEnvoyee(): ?Campaign;

    // ═══════════════════════════════════════════════════ matière du récap

    /**
     * Les fact-checks PUBLIÉS de la fenêtre — des `HubArticle`, jamais des
     * `VerifiedFact`.
     *
     * La distinction n'est pas théorique : `VerifiedFact` est l'enregistrement
     * interne de l'équipe, sans page publique. La confusion a déjà produit un
     * plan du site plein de 404 ; dans une infolettre, elle produirait des
     * liens morts envoyés à toute la liste.
     *
     * @return Collection<int, HubArticle>
     */
    public function factChecksPublies(CarbonInterface $debut, CarbonInterface $fin, int $limite): Collection;
}
