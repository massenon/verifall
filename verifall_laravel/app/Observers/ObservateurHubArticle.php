<?php

declare(strict_types=1);

namespace App\Observers;

use App\Core\Marketing\Cartes\CarteDeDementi;
use App\Core\Marketing\Seo\FluxClaimReview;
use App\Models\HubArticle;

/**
 * Tient la carte de démenti à jour, sans que personne ait à y penser.
 *
 * C'est ici, et non dans l'écran qui publie, parce qu'un fact-check change de
 * verdict par plusieurs chemins : l'écran d'administration, un seeder, une
 * commande de rattrapage. Un appel posé dans un seul de ces chemins produirait
 * exactement le défaut qu'on veut éviter — une carte qui annonce FAUX sur un
 * verdict devenu VRAI, partagée telle quelle sur WhatsApp.
 *
 * Rien n'est fabriqué ici : `planifierSiNecessaire()` met en file et sort. Elle
 * est sans effet quand la carte est déjà à jour, donc appelable à chaque
 * enregistrement.
 */
final readonly class ObservateurHubArticle
{
    public function __construct(
        private CarteDeDementi $cartes,
        private FluxClaimReview $flux,
    ) {}

    public function saved(HubArticle $article): void
    {
        $this->cartes->planifierSiNecessaire($article);

        /*
         | Le flux de vérifications est mis en cache une heure. Sans cet oubli,
         | un verdict corrigé continuerait d'être servi FAUX pendant une heure
         | à un moteur qui l'ingère sans relecture — soit précisément le
         | scénario que la correction visait à empêcher.
         */
        $this->flux->oublier();
    }

    /**
     * Un article supprimé emporte sa carte.
     *
     * Sans cela, l'image resterait servie par son URL publique après le retrait
     * de l'article : un démenti que le site ne montre plus, mais que WhatsApp
     * continue d'afficher.
     */
    public function deleted(HubArticle $article): void
    {
        $this->cartes->supprimer($article);
        $this->flux->oublier();
    }
}
