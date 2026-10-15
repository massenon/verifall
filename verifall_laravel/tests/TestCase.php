<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Vide le cache des permissions AVANT chaque test.
     *
     * ## L'intermittence que ceci corrige, observée trois fois
     *
     * `CACHE_STORE=array` : le cache vit DANS le processus PHP, pas dans un
     * fichier ni en base. Or paratest enchaîne des dizaines de fichiers de test
     * dans le même processus, et `LazilyRefreshDatabase` remet la base à zéro
     * entre eux — **sans toucher au cache**.
     *
     * Spatie range rôles et permissions sous une seule clé,
     * `spatie.permission.cache`, avec leurs identifiants numériques. Un test
     * qui sème les rôles remplit ce cache ; le test suivant, dans le même
     * processus, hérite d'identifiants qui ne désignent plus rien depuis que la
     * base a été vidée. D'où un `hasRole()` qui répond faux sur un rôle
     * pourtant assigné, ou un `ModelNotFoundException` — et rien du tout quand
     * on rejoue le fichier seul, puisque le processus repart vierge.
     *
     * C'est le symptôme consigné trois fois dans `RESTE_A_FAIRE.md` :
     * `ProfilTest` le 24 août, `FinalisationInscriptionTest` le 2 septembre,
     * puis `AuthentificationApiTest` le même jour — tous trois sur un test qui
     * vérifie un rôle après une inscription, tous trois verts en isolation.
     *
     * L'oubli coûte une écriture de tableau par test ; le garder coûtait une
     * demi-journée d'enquête à chaque réapparition.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
