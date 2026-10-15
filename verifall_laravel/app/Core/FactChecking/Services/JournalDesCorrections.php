<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Application\Contracts\DepotCorrections;
use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Models\VerdictRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Le journal public des corrections.
 *
 * ## Ce qu'il garantit
 *
 * Qu'un verdict ne change jamais en silence. Quand la plateforme corrige, elle
 * garde l'ancien verdict visible, avec sa date et sa raison — c'est le point
 * du code de conduite de l'IFCN qui a le plus de conséquences techniques, et
 * le seul qui se vérifie de l'extérieur.
 *
 * ## Pourquoi la raison est refusée vide, et non « recommandée »
 *
 * Parce qu'une correction sans motif est une réécriture avec un horodatage.
 * Elle dit qu'on a changé d'avis, pas ce qu'on a appris — et le lecteur qui
 * avait partagé l'ancien verdict n'a toujours aucun moyen de savoir s'il a eu
 * tort de le croire ou tort de le partager.
 *
 * `consigner()` lève donc `RaisonDeCorrectionManquante`. Appelée dans la même
 * transaction que l'écriture du verdict, cette exception annule les deux : on
 * n'obtient jamais un verdict corrigé sans son entrée au journal.
 */
final class JournalDesCorrections
{
    public function __construct(
        private readonly DepotCorrections $depot,
    ) {}

    /**
     * Consigne une correction — et refuse tout le reste.
     *
     * @param  Model  $contenu  `HubArticle` ou `VerifiedFact`
     * @param  string  $affirmation  L'affirmation telle qu'elle a été examinée
     */
    public function consigner(
        Model $contenu,
        string $affirmation,
        VerdictFactCheck $ancien,
        VerdictFactCheck $nouveau,
        string $raison,
        ?User $auteur = null,
    ): VerdictRevision {
        $this->exigerUneRaison($raison);

        return $this->depot->enregistrer([
            'uuid' => (string) Str::uuid(),
            'revisable_type' => $contenu->getMorphClass(),
            'revisable_id' => $contenu->getKey(),
            'affirmation' => trim($affirmation),
            'ancien_verdict' => $ancien->value,
            'nouveau_verdict' => $nouveau->value,
            'raison' => trim($raison),
            'auteur_id' => $auteur?->id,
        ]);
    }

    /**
     * Consigne SI le verdict a bougé — le point d'accroche des services
     * d'écriture.
     *
     * Rendre ce test au service appelant, c'est accepter qu'un jour il
     * l'oublie. Ici, la seule façon de changer un verdict sans laisser de
     * trace est de ne pas appeler cette méthode du tout ; et si elle est
     * appelée, la raison n'est plus négociable.
     */
    public function consignerSiLeVerdictAChange(
        Model $contenu,
        string $affirmation,
        ?VerdictFactCheck $ancien,
        VerdictFactCheck $nouveau,
        string $raison,
        ?User $auteur = null,
    ): ?VerdictRevision {
        /*
         | Un verdict qui n'existait pas encore n'est pas une correction : le
         | premier verdict rendu sur une affirmation est une publication, et le
         | journal des corrections ne doit pas s'en encombrer — sinon il devient
         | un journal des publications, que personne ne lira.
         */
        if ($ancien === null || $ancien === $nouveau) {
            return null;
        }

        return $this->consigner($contenu, $affirmation, $ancien, $nouveau, $raison, $auteur);
    }

    /**
     * Vérifie la raison SANS rien écrire.
     *
     * Utile à un écran qui veut échouer tôt, avant d'avoir modifié quoi que ce
     * soit — pour montrer un message sous un champ plutôt qu'une page d'erreur.
     */
    public function exigerUneRaison(string $raison): void
    {
        if (trim($raison) === '') {
            throw RaisonDeCorrectionManquante::pourUneCorrection();
        }
    }

    /**
     * @return LengthAwarePaginator<int, VerdictRevision>
     */
    public function journal(int $parPage = 15): LengthAwarePaginator
    {
        return $this->depot->journal($parPage);
    }

    /**
     * @return Collection<int, VerdictRevision>
     */
    public function historiqueDe(Model $contenu): Collection
    {
        return $this->depot->pour($contenu);
    }

    /** Cette vérification a-t-elle déjà été corrigée ? */
    public function aEteCorrige(Model $contenu): bool
    {
        return $this->depot->nombrePour($contenu) > 0;
    }
}
