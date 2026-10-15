<?php

declare(strict_types=1);

use App\Core\FactChecking\Services\DomaineDeLaSource;
use App\Core\FactChecking\Services\IndependanceDesSources;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Enums\PositionPreuve;

/*
|--------------------------------------------------------------------------
| Phase 5 — canonicalisation et provenance (contrat V-9)
|--------------------------------------------------------------------------
*/

function preuveDe(string $url, string $nom = ''): Preuve
{
    return new Preuve(url: $url, nomSource: $nom, extrait: '', position: PositionPreuve::Neutre);
}

describe('domaine enregistrable — eTLD+1', function (): void {
    beforeEach(function (): void {
        $this->domaines = app(DomaineDeLaSource::class);
    });

    it('fusionne www, m et amp comme le même éditeur', function (): void {
        expect($this->domaines->domaineEnregistrable('https://www.lemonde.fr/article'))->toBe('lemonde.fr')
            ->and($this->domaines->domaineEnregistrable('https://m.lemonde.fr/article'))->toBe('lemonde.fr')
            ->and($this->domaines->domaineEnregistrable('https://amp.lemonde.fr/article'))->toBe('lemonde.fr');
    });

    it('garde deux sous-domaines institutionnels distincts séparés', function (): void {
        expect($this->domaines->domaineEnregistrable('https://sante.gouv.bj/avis'))->toBe('sante.gouv.bj')
            ->and($this->domaines->domaineEnregistrable('https://travail.gouv.bj/avis'))->toBe('travail.gouv.bj');
    });

    it('reconnaît un suffixe à deux niveaux (.co.uk)', function (): void {
        expect($this->domaines->domaineEnregistrable('https://www.bbc.co.uk/news/article'))->toBe('bbc.co.uk');
    });

    it('ne remonte jamais jusqu\'à une extension seule', function (): void {
        expect($this->domaines->domaineEnregistrable('https://exemple.com'))->toBe('exemple.com');
    });
});

describe('déduplication par domaine enregistrable — contrat V-9', function (): void {
    it('V9-E : six URL partageant un éditeur comptent pour UN SEUL document', function (): void {
        $independance = app(IndependanceDesSources::class);

        $preuves = [
            preuveDe('https://www.lemonde.fr/a', 'Le Monde'),
            preuveDe('https://m.lemonde.fr/b', 'Le Monde'),
            preuveDe('https://amp.lemonde.fr/c', 'Le Monde'),
            preuveDe('https://lemonde.fr/d', 'Le Monde'),
            preuveDe('https://www.lemonde.fr/e', 'Le Monde'),
            preuveDe('https://www.lemonde.fr/f', 'Le Monde'),
        ];

        expect($independance->nombreDeVoixIndependantes($preuves))->toBe(1)
            ->and($independance->uneSeuleVoix($preuves))->toBeTrue();
    });

    it('V9-F : deux articles distincts d\'un même domaine comptent pour un document, un éditeur', function (): void {
        $independance = app(IndependanceDesSources::class);

        $preuves = [preuveDe('https://lanationbenin.info/a'), preuveDe('https://lanationbenin.info/b')];

        expect($independance->nombreDeVoixIndependantes($preuves))->toBe(1);
    });

    it('deux éditeurs distincts comptent pour deux voix', function (): void {
        $independance = app(IndependanceDesSources::class);

        $preuves = [preuveDe('https://lemonde.fr/a'), preuveDe('https://rfi.fr/b')];

        expect($independance->nombreDeVoixIndependantes($preuves))->toBe(2)
            ->and($independance->uneSeuleVoix($preuves))->toBeFalse();
    });

    it('un lot vide n\'est la voix unique de personne', function (): void {
        expect(app(IndependanceDesSources::class)->uneSeuleVoix([]))->toBeFalse();
    });
});
