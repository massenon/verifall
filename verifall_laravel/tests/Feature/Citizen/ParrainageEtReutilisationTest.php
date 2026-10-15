<?php

declare(strict_types=1);

use App\Events\VerificationAchevee;
use App\Models\Verification;
use Illuminate\Support\Facades\Event;

/*
 | LE PARRAIN DOIT ÊTRE CRÉDITÉ, MÊME QUAND LE CACHE RÉPOND.
 |
 | `depuisLeFait()` et `depuisLAnalyse()` — les deux chemins de RÉUTILISATION —
 | posaient bien `Terminee` et `completed_at`, mais sortaient sans émettre
 | `VerificationAchevee`.
 |
 | Or c'est cet événement qui déclenche, par ses écouteurs : la notification de
 | fin d'analyse, la série de vigilance, le recalcul des badges, et surtout
 | `Parrainage::honorer()`.
 |
 | Conséquence, pour le cas que le code lui-même qualifie de plus fréquent —
 | « signalée des centaines de fois » : un parrain dont le filleul tombait sur
 | le cache n'était JAMAIS crédité, et ces unités étaient perdues sans trace,
 | `honorer()` n'étant appelé de nulle part ailleurs.
 |
 | L'ironie du défaut vaut d'être notée : plus la plateforme réutilisait — donc
 | plus elle économisait d'appels payants — moins elle récompensait ceux qui
 | l'alimentaient.
 |
 | Trouvé par une revue de code.
 */

it('émet VerificationAchevee sur les DEUX chemins de réutilisation', function (): void {
    /*
     | On vérifie sur le CODE plutôt qu'en montant une analyse complète : les
     | deux chemins exigent un fait vérifié ou une analyse antérieure avec leur
     | empreinte de contenu, et les monter dans un test reproduirait
     | l'orchestrateur au lieu de le tester.
     |
     | Ce qui compte ici est qu'aucun des trois points de sortie n'oublie
     | l'événement — et cela, la lecture du fichier le dit exactement.
     */
    $source = file_get_contents(
        app_path('Core/FactChecking/Services/OrchestrateurVerification.php')
    );

    /*
     | QUATRE émissions attendues depuis la phase 2 : l'analyse complète, les
     | deux réutilisations, et `rapportInsuffisant()` — le point de sortie
     | d'un contenu sans aucun claim vérifiable (opinion, question, contenu
     | non factuel). Une vérification qui s'y conclut est aussi complète
     | qu'une autre : la même raison qui vaut pour le cache vaut ici, le
     | parrainage et les badges ne doivent pas ignorer ce chemin non plus.
     */
    expect(substr_count($source, 'VerificationAchevee::dispatch'))->toBe(4);

    // Et chacun de ces points de sortie en porte une.
    foreach (['depuisLeFait', 'depuisLAnalyse', 'rapportInsuffisant'] as $methode) {
        $debut = strpos($source, 'function '.$methode.'(');
        expect($debut)->not->toBeFalse();

        // Le corps de la méthode, jusqu'à la suivante. Élargi à 4000 depuis
        // la phase 2 : `depuisLeFait`/`depuisLAnalyse` recopient désormais
        // aussi les claims, ce qui repousse l'émission au-delà de 3000.
        $corps = substr($source, $debut, 4000);

        expect($corps)->toContain('VerificationAchevee::dispatch');
    }
});

it('a un écouteur de parrainage branché sur cet événement', function (): void {
    /*
     | Le lien entre l'événement et la récompense. Si l'écouteur disparaissait,
     | l'émission ci-dessus ne servirait plus à rien — et le test précédent
     | resterait vert.
     */
    $ecouteur = file_get_contents(app_path('Listeners/RecompenserApresVerification.php'));

    expect($ecouteur)->toContain('VerificationAchevee')
        ->and($ecouteur)->toContain('honorer(');
});

it("ne casse RIEN quand l'écouteur échoue", function (): void {
    /*
     | L'événement est émis DANS la transaction qui valide l'analyse. Un
     | écouteur qui lève ferait perdre à quelqu'un le rapport qu'il vient de
     | payer — pour une notification qui n'a pas pu partir.
     |
     | Les deux écouteurs enveloppent donc TOUT leur corps, y compris la
     | première lecture. C'est cette première ligne qui manquait au filet.
     */
    foreach ([
        'Listeners/RecompenserApresVerification.php',
        'Listeners/NotifierAnalyseTerminee.php',
    ] as $chemin) {
        /*
         | On retire les COMMENTAIRES avant toute analyse.
         |
         | Les deux fichiers expliquent précisément le défaut corrigé, en citant
         | le code fautif. Une recherche naïve prend donc l'explication pour une
         | rechute — et, pire, trouve `$evenement->verification` dans le
         | docblock, AVANT le `try`, ce qui fait échouer le test de position sur
         | du texte.
         */
        $codeSeul = preg_replace('#/\*.*?\*/#s', '', file_get_contents(app_path($chemin))) ?? '';
        $codeSeul = preg_replace('#//[^\n]*#', '', $codeSeul) ?? $codeSeul;

        $positionTry = strpos($codeSeul, 'try {');
        $positionLecture = strpos($codeSeul, '$evenement->verification');

        expect($positionTry)->not->toBeFalse()
            ->and($positionLecture)->not->toBeFalse()
            // La lecture vient APRÈS l'ouverture du try.
            ->and($positionLecture)->toBeGreaterThan($positionTry);

        // Et surtout : jamais la relation `->user`, qui déclenche une requête
        // que `preventLazyLoading` interdit — dans une transaction.
        expect($codeSeul)->not->toContain('verification->user');
    }
});

it('émet un événement portant la vérification achevée', function (): void {
    // Contrat de l'événement : les écouteurs en dépendent tous.
    Event::fake();

    $evenement = new VerificationAchevee(new Verification);

    expect($evenement->verification)->toBeInstanceOf(Verification::class);
});
