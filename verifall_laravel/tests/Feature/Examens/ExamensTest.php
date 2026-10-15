<?php

declare(strict_types=1);

use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Core\Application\Contracts\DepotExamens;
use App\Core\Exams\Exceptions\CandidatNonInscrit;
use App\Core\Exams\Exceptions\CopieNonCorrigeable;
use App\Core\Exams\Exceptions\ExamenIndisponible;
use App\Core\Exams\Exceptions\QuestionInutilisable;
use App\Core\Exams\Exceptions\TentativesEpuisees;
use App\Core\Exams\Services\BanqueDeQuestions;
use App\Core\Exams\Services\CorrectionExamen;
use App\Core\Exams\Services\EnrolementExamen;
use App\Core\Exams\Services\PassationExamen;
use App\Core\Exams\Services\PlanificationExamen;
use App\Enums\StatutCopieExamen;
use App\Enums\StatutExamen;
use App\Enums\StatutInscriptionExamen;
use App\Enums\StatutQuestion;
use App\Enums\StatutSessionExamen;
use App\Enums\TypeQuestion;
use App\Events\ExamenReussi;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\QuestionBankItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Examens — banque, planification, enrôlement, passation, correction
|--------------------------------------------------------------------------
| Deux fautes seraient impardonnables ici, et ce sont elles que la plupart de
| ces tests surveillent : rendre une note à quelqu'un dont la copie n'a pas été
| lue, et faire bouger après coup une note déjà rendue.
*/

beforeEach(function (): void {
    $this->banque = app(BanqueDeQuestions::class);
    $this->planification = app(PlanificationExamen::class);
    $this->enrolement = app(EnrolementExamen::class);
    $this->passation = app(PassationExamen::class);
    $this->correction = app(CorrectionExamen::class);

    $this->candidat = User::factory()->actif()->create();
    $this->mentor = User::factory()->actif()->create();
});

/** Un QCM publié, dont la première proposition est la bonne. */
function questionQcmDExamen(int $points = 10): QuestionBankItem
{
    $banque = app(BanqueDeQuestions::class);

    $question = $banque->enregistrer([
        'type' => TypeQuestion::Qcm,
        'prompt' => 'Quel est le premier réflexe face à une image virale ?',
        'points' => $points,
    ]);

    $question = $banque->definirLesPropositions($question, [
        ['label' => 'Faire une recherche inversée', 'is_correct' => true],
        ['label' => 'La partager tout de suite'],
    ]);

    return $banque->publier($question);
}

/** Une question à réponse libre, publiée : elle exige un correcteur humain. */
function questionLibreDExamen(int $points = 10): QuestionBankItem
{
    $banque = app(BanqueDeQuestions::class);

    return $banque->publier($banque->enregistrer([
        'type' => TypeQuestion::ReponseCourte,
        'prompt' => "Citez un critère d'évaluation d'une source.",
        'points' => $points,
    ]));
}

/**
 * Un examen publié, monté sur les questions données.
 *
 * @param  list<QuestionBankItem>  $questions
 */
function examenPublie(array $questions, int $seuil = 70, int $tentatives = 2): Exam
{
    $planification = app(PlanificationExamen::class);

    $examen = $planification->creer([
        'title' => 'Certification Fact-Checker Niveau '.random_int(1, 9999),
        'pass_score' => $seuil,
        'max_attempts' => $tentatives,
    ]);

    foreach ($questions as $question) {
        $planification->ajouterUneQuestion($examen, $question);
    }

    return $planification->publier($examen);
}

/** Une session ouverte à la composition. */
function sessionOuverte(Exam $examen, ?int $places = null): ExamSession
{
    $planification = app(PlanificationExamen::class);

    return $planification->ouvrirUneSession(
        $planification->planifierUneSession($examen, now()->addDay(), ['seats' => $places])
    );
}

// ---------------------------------------------------------------- BANQUE

describe('la banque de questions', function (): void {
    it('crée une question en brouillon et purifie son explication', function (): void {
        $question = $this->banque->enregistrer([
            'type' => TypeQuestion::Qcm,
            'prompt' => 'Question ?',
            'explanation' => '<p>Utile.</p><script>alert(1)</script>',
        ]);

        expect($question->status)->toBe(StatutQuestion::Brouillon)
            ->and($question->explanation)->not->toContain('<script')
            ->and($question->explanation)->toContain('Utile.');
    });

    it('refuse de publier une question à choix sans proposition', function (): void {
        $question = $this->banque->enregistrer([
            'type' => TypeQuestion::Qcm,
            'prompt' => 'Question ?',
        ]);

        $this->banque->publier($question);
    })->throws(QuestionInutilisable::class);

    it('refuse de publier un QCM dont aucune réponse n\'est juste', function (): void {
        // Publiée telle quelle, elle noterait zéro tout le monde en silence.
        $question = $this->banque->enregistrer([
            'type' => TypeQuestion::Qcm,
            'prompt' => 'Question ?',
        ]);

        $question = $this->banque->definirLesPropositions($question, [
            ['label' => 'A'],
            ['label' => 'B'],
        ]);

        $this->banque->publier($question);
    })->throws(QuestionInutilisable::class);

    it('publie une réponse courte sans lui réclamer de proposition', function (): void {
        expect(questionLibreDExamen()->status)->toBe(StatutQuestion::Publiee);
    });

    it('remplace le jeu de propositions en bloc', function (): void {
        // Une mise à jour partielle laisserait l'ancienne bonne réponse à côté
        // de la nouvelle, et la question aurait deux solutions.
        $question = questionQcmDExamen();

        $question = $this->banque->definirLesPropositions($question, [
            ['label' => 'Vérifier la source', 'is_correct' => true],
        ]);

        expect($question->loadMissing('options')->options)->toHaveCount(1);
    });
});

// -------------------------------------------------------- PLANIFICATION

describe('la planification d\'une épreuve', function (): void {
    it('refuse de publier un examen sans question', function (): void {
        $examen = $this->planification->creer(['title' => 'Épreuve vide']);

        $this->planification->publier($examen);
    })->throws(ExamenIndisponible::class);

    it('publie une épreuve montée et l\'horodate', function (): void {
        $examen = examenPublie([questionQcmDExamen()]);

        expect($examen->status)->toBe(StatutExamen::Publie)
            ->and($examen->published_at)->not->toBeNull();
    });

    it('refuse de poser une question qui n\'est pas publiée', function (): void {
        $examen = $this->planification->creer(['title' => 'Épreuve']);
        $brouillon = $this->banque->enregistrer(['type' => TypeQuestion::Qcm, 'prompt' => 'Q ?']);

        $this->planification->ajouterUneQuestion($examen, $brouillon);
    })->throws(QuestionInutilisable::class);

    it('ramène un seuil absurde dans les bornes', function (): void {
        // Zéro rendrait l'examen impossible à rater, 150 impossible à réussir.
        $bas = $this->planification->creer(['title' => 'Seuil bas', 'pass_score' => 0]);
        $haut = $this->planification->creer(['title' => 'Seuil haut', 'pass_score' => 150]);

        expect($bas->pass_score)->toBe(1)->and($haut->pass_score)->toBe(100);
    });

    it('donne un slug distinct à deux examens homonymes', function (): void {
        $premier = $this->planification->creer(['title' => 'Certification OSINT']);
        $second = $this->planification->creer(['title' => 'Certification OSINT']);

        expect($second->slug)->not->toBe($premier->slug);
    });

    it('additionne le barème de ses questions', function (): void {
        $examen = examenPublie([questionQcmDExamen(10), questionQcmDExamen(15)]);

        expect($this->planification->baremeTotal($examen))->toBe(25);
    });

    it('refuse de planifier une session sur un examen non publié', function (): void {
        $examen = $this->planification->creer(['title' => 'Brouillon']);

        $this->planification->planifierUneSession($examen, now()->addDay());
    })->throws(ExamenIndisponible::class);

    it('refuse deux sessions du même examen à la même heure', function (): void {
        $examen = examenPublie([questionQcmDExamen()]);
        $heure = now()->addDays(3);

        $this->planification->planifierUneSession($examen, $heure);
        $this->planification->planifierUneSession($examen, $heure);
    })->throws(ExamenIndisponible::class);

    it('ne rouvre pas une session annulée', function (): void {
        // Les convoqués ont été prévenus : la rouvrir enverrait du monde à une
        // épreuve que personne ne surveille.
        $examen = examenPublie([questionQcmDExamen()]);
        $session = $this->planification->planifierUneSession($examen, now()->addDay());

        $this->planification->ouvrirUneSession($this->planification->annulerUneSession($session));
    })->throws(ExamenIndisponible::class);
});

// ------------------------------------------------------------ ENRÔLEMENT

describe('l\'enrôlement', function (): void {
    it('n\'inscrit qu\'une fois, même sur un double-clic', function (): void {
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]));

        $premiere = $this->enrolement->inscrire($session, $this->candidat);
        $seconde = $this->enrolement->inscrire($session, $this->candidat);

        expect($seconde->id)->toBe($premiere->id)
            ->and($session->refresh()->registrations_count)->toBe(1);
    });

    it('refuse une session complète', function (): void {
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]), places: 1);

        $this->enrolement->inscrire($session, $this->candidat);
        $this->enrolement->inscrire($session->refresh(), User::factory()->actif()->create());
    })->throws(ExamenIndisponible::class);

    it('émet la convocation une seule fois', function (): void {
        // Regénérer le code invaliderait celui que le candidat a imprimé.
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]));
        $inscription = $this->enrolement->inscrire($session, $this->candidat);

        $convoquee = $this->enrolement->envoyerLaConvocation($inscription);
        $rejouee = $this->enrolement->envoyerLaConvocation($convoquee);

        expect($convoquee->convocation_code)->not->toBeNull()
            ->and($rejouee->convocation_code)->toBe($convoquee->convocation_code)
            ->and($rejouee->libelleConvocation())->toBe('Envoyée');
    });

    it('tire un code de convocation sans caractère ambigu', function (): void {
        // O/0, I/1 et S/5 se confondent quand on recopie un code à la main.
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]));
        $inscription = $this->enrolement->envoyerLaConvocation(
            $this->enrolement->inscrire($session, $this->candidat)
        );

        expect(substr((string) $inscription->convocation_code, 5))->not->toMatch('/[OIS015]/');
    });

    it('alimente le taux de présence à l\'émargement', function (): void {
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]));
        $this->enrolement->inscrire($session, $this->candidat);
        $autre = $this->enrolement->inscrire($session->refresh(), User::factory()->actif()->create());

        $this->enrolement->emarger($autre);

        expect($session->refresh()->tauxDePresence())->toBe(50);
    });

    it('ne donne pas de taux de présence sur une session vide', function (): void {
        // « 0 % » serait un chiffre inventé, et un chiffre inventé rassure.
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]));

        expect($session->tauxDePresence())->toBeNull();
    });

    it('déplace le candidat et repart d\'une convocation neuve au report', function (): void {
        $examen = examenPublie([questionQcmDExamen()]);
        $aout = sessionOuverte($examen);
        $septembre = $this->planification->planifierUneSession($examen, now()->addDays(10));

        $inscription = $this->enrolement->envoyerLaConvocation(
            $this->enrolement->inscrire($aout, $this->candidat)
        );
        $this->enrolement->demanderUnReport($inscription, 'Déplacement professionnel');

        $reportee = $this->enrolement->arbitrerLeReport($inscription->refresh(), $septembre);

        expect($reportee->exam_session_id)->toBe($septembre->id)
            ->and($reportee->convocation_code)->toBeNull()
            ->and($reportee->status)->toBe(StatutInscriptionExamen::Inscrit)
            ->and($aout->refresh()->registrations_count)->toBe(0)
            ->and($septembre->refresh()->registrations_count)->toBe(1);
    });

    it('libère la place à l\'annulation', function (): void {
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]), places: 1);
        $inscription = $this->enrolement->inscrire($session, $this->candidat);

        $this->enrolement->annuler($inscription);

        expect($session->refresh()->registrations_count)->toBe(0)
            ->and($session->estComplete())->toBeFalse();
    });
});

// ------------------------------------------------------------- PASSATION

describe('la passation', function (): void {
    it('refuse de composer sans être convoqué', function (): void {
        // L'absence de bouton n'est pas une protection : le refus est ici.
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]));

        $this->passation->demarrer($this->candidat, $session);
    })->throws(CandidatNonInscrit::class);

    it('refuse de composer sur une session qui n\'est pas ouverte', function (): void {
        $examen = examenPublie([questionQcmDExamen()]);
        $session = $this->planification->planifierUneSession($examen, now()->addDay());
        $this->enrolement->inscrire($session, $this->candidat);

        $this->passation->demarrer($this->candidat, $session);
    })->throws(ExamenIndisponible::class);

    it('rend la copie déjà ouverte plutôt que d\'en consommer une seconde', function (): void {
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]));
        $this->enrolement->inscrire($session, $this->candidat);

        $premiere = $this->passation->demarrer($this->candidat, $session);
        $seconde = $this->passation->demarrer($this->candidat, $session->refresh());

        expect($seconde->id)->toBe($premiere->id)
            ->and($seconde->attempt_no)->toBe(1);
    });

    it('épuise les tentatives, rattrapage compris', function (): void {
        $question = questionQcmDExamen();
        $examen = examenPublie([$question], tentatives: 1);
        $session = sessionOuverte($examen);
        $this->enrolement->inscrire($session, $this->candidat);

        $copie = $this->passation->demarrer($this->candidat, $session);
        $this->passation->soumettre($copie, []);

        $this->passation->demarrer($this->candidat, $session->refresh());
    })->throws(TentativesEpuisees::class);

    it('fige le seuil et le barème au moment de composer', function (): void {
        $examen = examenPublie([questionQcmDExamen(10)], seuil: 70);
        $session = sessionOuverte($examen);
        $this->enrolement->inscrire($session, $this->candidat);

        $copie = $this->passation->demarrer($this->candidat, $session);

        // On relève le seuil et on alourdit l'épreuve APRÈS l'ouverture.
        $this->planification->mettreAJour($examen, ['pass_score' => 95]);
        $this->planification->ajouterUneQuestion($examen->refresh(), questionQcmDExamen(50));

        expect($copie->refresh()->pass_score)->toBe(70)
            ->and($copie->max_points)->toBe(10);
    });

    it('rend la note tout de suite quand rien n\'exige un correcteur', function (): void {
        $question = questionQcmDExamen();
        $bonne = (int) $question->loadMissing('options')->options->firstWhere('is_correct', true)?->id;

        $examen = examenPublie([$question]);
        $session = sessionOuverte($examen);
        $this->enrolement->inscrire($session, $this->candidat);
        $copie = $this->passation->demarrer($this->candidat, $session);

        $rendue = $this->passation->soumettre($copie, [$question->id => ['options' => [$bonne]]]);

        expect($rendue->status)->toBe(StatutCopieExamen::Corrigee)
            ->and($rendue->score_percent)->toBe(100)
            ->and($rendue->passed)->toBeTrue();
    });

    it('compte une réponse manquante comme fausse, jamais comme absente', function (): void {
        // Ignorer les questions non répondues permettrait d'obtenir 100 % en
        // répondant à une seule.
        $premiere = questionQcmDExamen();
        $seconde = questionQcmDExamen();
        $bonne = (int) $premiere->loadMissing('options')->options->firstWhere('is_correct', true)?->id;

        $examen = examenPublie([$premiere, $seconde]);
        $session = sessionOuverte($examen);
        $this->enrolement->inscrire($session, $this->candidat);
        $copie = $this->passation->demarrer($this->candidat, $session);

        $rendue = $this->passation->soumettre($copie, [$premiere->id => ['options' => [$bonne]]]);

        expect($rendue->score_percent)->toBe(50)
            ->and($rendue->passed)->toBeFalse();
    });

    it('ne récompense pas qui coche tout sur un multi-select', function (): void {
        $banque = $this->banque;
        $question = $banque->enregistrer([
            'type' => TypeQuestion::MultiSelect,
            'prompt' => 'Sélectionnez les indices d\'un deepfake vocal.',
        ]);
        $question = $banque->definirLesPropositions($question, [
            ['label' => 'Respiration absente', 'is_correct' => true],
            ['label' => 'Prosodie plate', 'is_correct' => true],
            ['label' => 'Fichier volumineux'],
        ]);
        $question = $banque->publier($question);

        $toutes = $question->loadMissing('options')->options->pluck('id')->all();

        $examen = examenPublie([$question]);
        $session = sessionOuverte($examen);
        $this->enrolement->inscrire($session, $this->candidat);
        $copie = $this->passation->demarrer($this->candidat, $session);

        $rendue = $this->passation->soumettre($copie, [$question->id => ['options' => $toutes]]);

        expect($rendue->score_percent)->toBe(0);
    });

    it('envoie en file de correction dès qu\'une réponse est libre', function (): void {
        $qcm = questionQcmDExamen();
        $libre = questionLibreDExamen();
        $bonne = (int) $qcm->loadMissing('options')->options->firstWhere('is_correct', true)?->id;

        $examen = examenPublie([$qcm, $libre]);
        $session = sessionOuverte($examen);
        $this->enrolement->inscrire($session, $this->candidat);
        $copie = $this->passation->demarrer($this->candidat, $session);

        $rendue = $this->passation->soumettre($copie, [
            $qcm->id => ['options' => [$bonne]],
            $libre->id => ['text' => 'La transparence des sources.'],
        ]);

        expect($rendue->status)->toBe(StatutCopieExamen::EnAttente)
            // Aucun score partiel affiché : il serait pris pour la note finale.
            ->and($rendue->score_percent)->toBeNull()
            ->and($rendue->passed)->toBeNull();
    });

    it('refuse de rendre deux fois la même copie', function (): void {
        $session = sessionOuverte(examenPublie([questionQcmDExamen()]));
        $this->enrolement->inscrire($session, $this->candidat);
        $copie = $this->passation->demarrer($this->candidat, $session);

        $this->passation->soumettre($copie, []);
        $this->passation->soumettre($copie->refresh(), []);
    })->throws(CopieNonCorrigeable::class);
});

// ------------------------------------------------------------ CORRECTION

/** Une copie rendue qui contient une réponse libre, donc en attente. */
function copieEnAttente(User $candidat, int $pointsLibres = 10): ExamAttempt
{
    $libre = questionLibreDExamen($pointsLibres);
    $examen = examenPublie([$libre]);
    $session = sessionOuverte($examen);

    app(EnrolementExamen::class)->inscrire($session, $candidat);
    $copie = app(PassationExamen::class)->demarrer($candidat, $session);

    return app(PassationExamen::class)->soumettre($copie, [
        $libre->id => ['text' => 'La transparence des sources.'],
    ]);
}

describe('la correction', function (): void {
    it('assigne la copie à un mentor et la sort de l\'attente', function (): void {
        $copie = $this->correction->assigner(copieEnAttente($this->candidat), $this->mentor);

        expect($copie->status)->toBe(StatutCopieExamen::EnCorrection)
            ->and($copie->assigned_to)->toBe($this->mentor->id);
    });

    it('refuse de rendre une note tant qu\'une réponse n\'est pas lue', function (): void {
        $this->correction->finaliser(copieEnAttente($this->candidat), $this->mentor);
    })->throws(CopieNonCorrigeable::class);

    it('borne les points accordés au barème de la question', function (): void {
        $copie = copieEnAttente($this->candidat, pointsLibres: 10);
        $reponse = $copie->loadMissing('reponses')->reponses->first();

        $notee = $this->correction->noter($reponse, 200);

        expect($notee->points_awarded)->toBe(10);
    });

    it('rend la note, recalcule les compteurs et annonce la réussite', function (): void {
        Event::fake([ExamenReussi::class]);

        $copie = copieEnAttente($this->candidat, pointsLibres: 10);
        $reponse = $copie->loadMissing('reponses')->reponses->first();

        $this->correction->noter($reponse, 10, 'Réponse complète.');
        $rendue = $this->correction->finaliser($copie->refresh(), $this->mentor, 'Bon travail.');

        $examen = $rendue->loadMissing('exam')->exam;

        expect($rendue->status)->toBe(StatutCopieExamen::Corrigee)
            ->and($rendue->score_percent)->toBe(100)
            ->and($rendue->passed)->toBeTrue()
            ->and($rendue->graded_by)->toBe($this->mentor->id)
            ->and($examen?->candidates_count)->toBe(1)
            ->and($examen?->passed_count)->toBe(1)
            ->and($examen?->tauxDeReussite())->toBe(100);

        Event::assertDispatched(ExamenReussi::class);
    });

    it('n\'annonce rien quand la copie est sous le seuil', function (): void {
        Event::fake([ExamenReussi::class]);

        $copie = copieEnAttente($this->candidat, pointsLibres: 10);
        $reponse = $copie->loadMissing('reponses')->reponses->first();

        $this->correction->noter($reponse, 3);
        $rendue = $this->correction->finaliser($copie->refresh(), $this->mentor);

        expect($rendue->passed)->toBeFalse()
            ->and($rendue->loadMissing('exam')->exam?->passed_count)->toBe(0);

        Event::assertNotDispatched(ExamenReussi::class);
    });

    it('refuse de rouvrir une note déjà rendue', function (): void {
        // Une note est un fait daté : la reprendre changerait l'histoire.
        $copie = copieEnAttente($this->candidat);
        $reponse = $copie->loadMissing('reponses')->reponses->first();

        $this->correction->noter($reponse, 10);
        $rendue = $this->correction->finaliser($copie->refresh(), $this->mentor);

        $this->correction->noter($rendue->loadMissing('reponses')->reponses->first(), 0);
    })->throws(CopieNonCorrigeable::class);

    it('nourrit le taux de réussite de la question corrigée', function (): void {
        $copie = copieEnAttente($this->candidat);
        $reponse = $copie->loadMissing('reponses')->reponses->first();

        $this->correction->noter($reponse, 10);
        $this->correction->finaliser($copie->refresh(), $this->mentor);

        $question = QuestionBankItem::findOrFail($reponse->question_bank_item_id);

        expect($question->answers_count)->toBe(1)
            ->and($question->correct_count)->toBe(1)
            ->and($question->tauxDeReussite())->toBe(100);
    });

    it('ne donne pas de taux à une question jamais posée', function (): void {
        // Zéro pour cent la ferait passer pour impossible et déclencherait une
        // révision que rien ne justifie.
        expect(questionQcmDExamen()->tauxDeReussite())->toBeNull();
    });

    it('sort une copie abandonnée des statistiques sans la compter en échec', function (): void {
        $copie = copieEnAttente($this->candidat);

        $abandonnee = $this->correction->abandonner($copie);

        expect($abandonnee->status)->toBe(StatutCopieExamen::Abandonnee)
            ->and($abandonnee->status->compteDansLesStatistiques())->toBeFalse();
    });
});

// ------------------------------------------------------- LES INDICATEURS

describe('les indicateurs', function (): void {
    it('ne compte comme candidat que ce qui a été corrigé', function (): void {
        // 612 candidats dont 400 absents donnerait un taux de réussite faux.
        $copie = copieEnAttente($this->candidat);

        expect(app(DepotExamens::class)->indicateurs()['candidats_30j'])->toBe(0);

        $this->correction->noter($copie->loadMissing('reponses')->reponses->first(), 10);
        $this->correction->finaliser($copie->refresh(), $this->mentor);

        $indicateurs = app(DepotExamens::class)->indicateurs();

        expect($indicateurs['candidats_30j'])->toBe(1)
            ->and($indicateurs['taux_reussite'])->toBe(100)
            ->and($indicateurs['a_corriger'])->toBe(0);
    });

    it('range les abandons à part, jamais parmi les échecs', function (): void {
        $this->correction->abandonner(copieEnAttente($this->candidat));

        $indicateurs = app(DepotExamens::class)->indicateurs();

        expect($indicateurs['abandons'])->toBe(1)
            ->and($indicateurs['candidats_30j'])->toBe(0)
            ->and($indicateurs['taux_reussite'])->toBeNull();
    });

    it('donne à chaque examen ses sessions et son score moyen', function (): void {
        $copie = copieEnAttente($this->candidat);
        $this->correction->noter($copie->loadMissing('reponses')->reponses->first(), 8);
        $this->correction->finaliser($copie->refresh(), $this->mentor);

        $ligne = app(DepotExamens::class)->statistiques()->items()[0];

        expect($ligne->sessions_count)->toBe(1)
            ->and((int) round((float) $ligne->average_score))->toBe(80);
    });

    it('ne prétend à aucun délai de correction tant que rien n\'est corrigé', function (): void {
        expect(app(DepotCopiesExamen::class)->indicateurs()['delai_moyen_jours'])->toBeNull();
    });
});

// ------------------------------------------------------- ÉTATS DE SESSION

it('ferme une session sans toucher aux copies déjà rendues', function (): void {
    $copie = copieEnAttente($this->candidat);
    $session = $copie->loadMissing('session')->session;

    $this->planification->fermerUneSession($session);

    expect($session->refresh()->status)->toBe(StatutSessionExamen::Fermee)
        ->and($copie->refresh()->status)->toBe(StatutCopieExamen::EnAttente);
});
