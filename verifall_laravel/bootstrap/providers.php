<?php

declare(strict_types=1);

use App\Providers\ApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    // Métadonnées de référencement. Posées par défaut sur chaque page publique
    // à partir du nom de sa route : l'exigence est « toutes les pages, sans
    // exception », et une variable passée page par page manque toujours celle
    // que personne ne relit.
    SeoServiceProvider::class,
    // Plafonds de cadence de l'API v1. Un provider distinct plutôt qu'une
    // méthode de plus dans AppServiceProvider : ces règles ne concernent que
    // l'API, et les mêler au reste rendrait l'un et l'autre plus difficiles à
    // relire.
    ApiServiceProvider::class,
];
