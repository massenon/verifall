<?php

declare(strict_types=1);

use App\Enums\StatutCopieExamen;
use App\Enums\StatutExamen;
use App\Enums\StatutInscriptionExamen;
use App\Enums\StatutQuestion;
use App\Enums\StatutSessionExamen;
use App\Enums\TypeQuestion;

/*
|--------------------------------------------------------------------------
| Examens — les énumérations affichées
|--------------------------------------------------------------------------
| Un `match` sans branche par défaut est un piège à retardement : ajouter un
| cas fait planter, à l'exécution seulement, toutes les pages qui l'affichent.
| Ces tests parcourent `cases()` et exigent un libellé et une pastille pour
| chaque valeur — c'est la seule façon d'être prévenu à l'ajout, pas en
| production.
*/

it('donne un libellé et une pastille à chaque état d\'examen', function (StatutExamen $statut): void {
    expect($statut->libelle())->not->toBeEmpty()
        ->and($statut->badge())->toStartWith('pastille');
})->with(StatutExamen::cases());

it('donne un libellé et une pastille à chaque type de question', function (TypeQuestion $type): void {
    expect($type->libelle())->not->toBeEmpty()
        ->and($type->badge())->toStartWith('pastille');
})->with(TypeQuestion::cases());

it('donne un libellé et une pastille à chaque état de question', function (StatutQuestion $statut): void {
    expect($statut->libelle())->not->toBeEmpty()
        ->and($statut->badge())->toStartWith('pastille');
})->with(StatutQuestion::cases());

it('donne un libellé et une pastille à chaque état de session', function (StatutSessionExamen $statut): void {
    expect($statut->libelle())->not->toBeEmpty()
        ->and($statut->badge())->toStartWith('pastille');
})->with(StatutSessionExamen::cases());

it('donne un libellé et une pastille à chaque état d\'inscription', function (StatutInscriptionExamen $statut): void {
    expect($statut->libelle())->not->toBeEmpty()
        ->and($statut->badge())->toStartWith('pastille');
})->with(StatutInscriptionExamen::cases());

it('donne un libellé et une pastille à chaque état de copie', function (StatutCopieExamen $statut): void {
    expect($statut->libelle())->not->toBeEmpty()
        ->and($statut->badge())->toStartWith('pastille');
})->with(StatutCopieExamen::cases());

it('ne confie à la machine que ce qu\'elle sait trancher', function (): void {
    // C'est cette réponse-là qui décide si un examen part en file de
    // correction. Une erreur ici et la note serait rendue sans que personne
    // n'ait lu la copie.
    $humaines = array_filter(TypeQuestion::cases(), fn (TypeQuestion $t): bool => ! $t->correctionAutomatique());

    expect($humaines)->toHaveCount(1)
        ->and(reset($humaines))->toBe(TypeQuestion::ReponseCourte);
});

it('ne montre dans la file de correction que ce qui a été rendu', function (): void {
    $file = array_filter(StatutCopieExamen::cases(), fn (StatutCopieExamen $s): bool => $s->dansLaFileDeCorrection());

    expect($file)->toHaveCount(2)
        ->and(StatutCopieExamen::EnComposition->dansLaFileDeCorrection())->toBeFalse()
        ->and(StatutCopieExamen::Abandonnee->dansLaFileDeCorrection())->toBeFalse();
});

it('ne compte dans les statistiques que les copies corrigées', function (StatutCopieExamen $statut): void {
    // Une épreuve abandonnée n'est pas un échec : la compter comme tel ferait
    // chuter un taux affiché sans que personne ne comprenne pourquoi.
    expect($statut->compteDansLesStatistiques())->toBe($statut === StatutCopieExamen::Corrigee);
})->with(StatutCopieExamen::cases());
