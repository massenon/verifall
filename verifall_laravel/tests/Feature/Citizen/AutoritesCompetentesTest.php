<?php

declare(strict_types=1);

use App\Core\FactChecking\Adapters\ConstructeurDePrompt;
use App\Core\FactChecking\Services\AutoritesCompetentes;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\TypeContenu;

/*
|--------------------------------------------------------------------------
| Phase 4 — découverte universelle des sources
|--------------------------------------------------------------------------
| Lève C-06.1 et C-06.6 : « gouv.bj » en dur dans les prompts, quelle que soit
| la juridiction réelle du contenu soumis. Les six scénarios du plan : santé,
| science, finance, sport, entreprise, juridiction inconnue.
*/

describe('AutoritesCompetentes — une fonction, jamais un pays', function (): void {
    beforeEach(function (): void {
        $this->autorites = app(AutoritesCompetentes::class);
    });

    it('santé : nomme le ministère compétent et l\'OMS, jamais un pays précis', function (): void {
        $reponse = $this->autorites->pour('Un vaccin provoque une épidémie dans plusieurs hôpitaux.');

        expect($reponse)->toContain('Santé')
            ->toContain('Organisation mondiale de la Santé')
            ->not->toContain('Bénin')->not->toContain('gouv.bj');
    });

    it('science : nomme l\'institution de recherche, pas une agence d\'un seul pays', function (): void {
        expect($this->autorites->pour('Une étude scientifique publiée par des chercheurs universitaires.'))
            ->toContain('recherche');
    });

    it('finance : nomme la banque centrale et le régulateur, jamais une seule monnaie', function (): void {
        $reponse = $this->autorites->pour('La banque centrale a relevé son taux de change.');

        expect($reponse)->toContain('banque centrale')
            ->not->toContain('Bénin')->not->toContain('CFA');
    });

    it('sport : nomme la fédération sportive compétente', function (): void {
        expect($this->autorites->pour('Le championnat régional a été remporté hier.'))
            ->toContain('fédération sportive');
    });

    it('entreprise : nomme le registre du commerce, pas une chambre nationale précise', function (): void {
        expect($this->autorites->pour('L\'entreprise a été rachetée par un actionnaire étranger.'))
            ->toContain('registre du commerce');
    });

    it('juridiction inconnue : aucun domaine reconnu ne présume AUCUN pays par défaut', function (): void {
        $reponse = $this->autorites->pour('Un phénomène étrange a été observé dans la région.');

        expect($reponse)->toContain('jamais une seule juridiction par défaut')
            ->not->toContain('Bénin')->not->toContain('gouv.bj');
    });
});

describe('le prompt n\'enferme plus la vérification dans une seule juridiction', function (): void {
    it('injecte la fonction compétente au lieu de « gouv.bj » en dur', function (): void {
        $prompt = app(ConstructeurDePrompt::class)->pour(new SacDePreuves(
            affirmation: 'Un vaccin a été retiré du marché par le ministère de la Santé.',
            type: TypeContenu::Texte,
        ));

        expect($prompt)->toContain('Organisation mondiale de la Santé')
            ->not->toContain('notamment gouv.bj');
    });

    it('ne laisse AUCUN marqueur {{AUTORITES_COMPETENTES}} non substitué', function (): void {
        $prompt = app(ConstructeurDePrompt::class)->pour(new SacDePreuves(
            affirmation: 'Une affirmation quelconque.',
            type: TypeContenu::Texte,
        ));

        expect($prompt)->not->toContain('{{AUTORITES_COMPETENTES}}');
    });

    it('le prompt URL décrit la FORME d\'un suffixe institutionnel, pas le Bénin spécifiquement', function (): void {
        $prompt = app(ConstructeurDePrompt::class)->pour(new SacDePreuves(
            affirmation: 'https://exemple-suspect.info/offre',
            type: TypeContenu::Lien,
        ));

        expect($prompt)->toContain('suffixe réservé aux institutions')
            ->not->toContain("les sites d'État béninois");
    });
});
