<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Liaison des suites (STRATEGIE_DE_TESTS)
|--------------------------------------------------------------------------
| LazilyRefreshDatabase plutôt que RefreshDatabase : la base n'est migrée que
| pour les tests qui la touchent réellement. Sur une suite qui atteindra
| plusieurs centaines de tests, la différence est nette.
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');
