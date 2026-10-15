<?php

declare(strict_types=1);

namespace App\Core\Application\Contracts;

/**
 * Ce que le plan du site doit énumérer.
 *
 * Une interface plutôt qu'un appel direct à Eloquent, parce que la règle 2 du
 * chantier ne souffre pas d'exception : aucune requête hors dépôt. Elle a une
 * conséquence pratique ici — le contrôleur du plan se teste sans base.
 */
interface DepotPlanDuSite
{
    /**
     * Les URL publiques, publiées et indexables, avec leur date réelle.
     *
     * @return list<array{url: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    public function entrees(): array;
}
