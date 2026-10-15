<?php

declare(strict_types=1);

namespace App\Support\DataTables;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Ce qu'un dépôt rend à un tableau côté serveur : deux compteurs et une page.
 *
 * Les deux compteurs ne sont pas redondants, et DataTables s'en sert pour
 * distinguer deux états que l'écran ne doit pas confondre : « il n'existe
 * aucun compte » (`total` = 0) et « aucun compte ne correspond à vos critères »
 * (`filtres` = 0, `total` > 0).
 *
 * @template TModele of Model
 */
final readonly class PageDeResultats
{
    /**
     * @param  Collection<int, TModele>  $lignes
     */
    public function __construct(
        public int $total,
        public int $filtres,
        public Collection $lignes,
        public bool $tronque = false,
    ) {}
}
