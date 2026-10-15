<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\Lms\Exceptions\CoursIndisponible;
use App\Core\Lms\Services\EvaluationQuiz;
use App\Core\Lms\Services\InscriptionAuCours;
use App\Core\Lms\Services\ProgressionAcademie;
use App\Enums\NiveauCours;
use App\Http\Api\V1\Resources\CoursResource;
use App\Http\Api\V1\Resources\InscriptionResource;
use App\Http\Api\V1\Resources\LeconResource;
use App\Http\Api\V1\Resources\QuizResource;
use App\Http\Api\V1\Support\Chronometre;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Http\Api\V1\Support\PageCurseur;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'Académie : catalogue, fiche de cours, leçon, quiz, progression.
 *
 * Le déverrouillage séquentiel, l'inscription payante, la correction et la
 * délivrance du certificat vivent tous dans `app/Core/Lms/Services`. Ce
 * contrôleur ne fait que trois choses : borner l'entrée, appeler, sérialiser.
 *
 * PARCOURS HORS LIGNE (M3). Les lectures de cette section sont conçues pour
 * être mises en cache par le client : le catalogue et les fiches ne dépendent
 * que du contenu publié. Les ÉCRITURES — inscription, leçon terminée,
 * tentative de quiz — sont rejouables : les deux premières sont idempotentes
 * par contrainte d'unicité en base, la troisième par la clé
 * `Idempotency-Key`.
 */
final class AcademieController
{
    /**
     * `GET /courses`
     *
     * Catalogue des cours PUBLIÉS. Un cours non publié n'existe pas pour
     * l'API : le rendre visible « puisqu'on connaît son slug » reviendrait à
     * publier par accident tout ce qui est en préparation.
     */
    public function catalogue(Request $requete): JsonResponse
    {
        $donnees = $requete->validate([
            'level' => ['nullable', 'string', 'in:'.implode(',', array_column(NiveauCours::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer'],
            'cursor' => ['nullable', 'string'],
        ]);

        $requeteSql = Course::with('instructor')->where('is_published', true);

        if (($donnees['level'] ?? null) !== null) {
            $requeteSql->where('level', $donnees['level']);
        }

        if (($donnees['q'] ?? null) !== null && trim((string) $donnees['q']) !== '') {
            $recherche = trim((string) $donnees['q']);
            $requeteSql->where(
                fn ($r) => $r->where('title', 'like', '%'.$recherche.'%')
                    ->orWhere('description', 'like', '%'.$recherche.'%'),
            );
        }

        $page = $requeteSql->orderByDesc('id')
            ->cursorPaginate(PageCurseur::taille($requete))
            ->withQueryString();

        return Enveloppe::page($page, CoursResource::collection($page->items()));
    }

    /**
     * `GET /courses/{slug}`
     *
     * La fiche porte les leçons AVEC leur état de déverrouillage pour la
     * personne connectée : c'est le serveur qui tranche `ouverte`, jamais le
     * client (voir `LeconResource`).
     */
    public function cours(Request $requete, Course $course, ProgressionAcademie $progression): JsonResponse
    {
        if (! $course->is_published) {
            return Enveloppe::erreur(CodeErreur::Introuvable);
        }

        $utilisateur = $this->utilisateur($requete);
        $course->loadMissing(['instructor', 'lessons', 'quizzes']);

        $course->lessons->each(function (Lesson $lecon) use ($progression, $utilisateur): void {
            $lecon->setAttribute('ouverte', $progression->leconOuverte($utilisateur, $lecon));
            $lecon->setAttribute('acquise', $progression->leconAcquise($utilisateur, $lecon));
        });

        $inscription = Enrollment::with('course')
            ->where('user_id', $utilisateur->id)
            ->where('course_id', $course->id)
            ->first();

        return Enveloppe::donnee(CoursResource::make($course), meta: [
            // `null` et non un objet vide : « je ne suis pas inscrit » est un
            // état, pas une inscription aux champs à zéro.
            'inscription' => $inscription === null ? null : InscriptionResource::make($inscription),
        ]);
    }

    /**
     * `POST /courses/{slug}/enroll`
     *
     * IDEMPOTENT par construction : `InscriptionAuCours` rend l'inscription
     * existante plutôt que de débiter deux fois. C'est ce qui rend l'appel
     * rejouable depuis la file d'attente hors ligne du téléphone sans risquer
     * de facturer deux fois le même cours.
     */
    public function inscrire(Request $requete, Course $course, InscriptionAuCours $inscriptions): JsonResponse
    {
        try {
            $inscription = $inscriptions->inscrire($this->utilisateur($requete), $course);
        } catch (CoursIndisponible $indisponible) {
            return Enveloppe::erreur(CodeErreur::ActionImpossible, $indisponible->getMessage());
        } catch (SoldeInsuffisant $manque) {
            return Enveloppe::erreur(CodeErreur::SoldeInsuffisant, $manque->messageUtilisateur());
        }

        return Enveloppe::donnee(
            InscriptionResource::make($inscription->loadMissing('course')),
            meta: ['solde' => $this->utilisateur($requete)->fresh()?->units],
            statut: 201,
        );
    }

    /**
     * Cette personne est-elle inscrite à ce cours ?
     *
     * La garde que l'API n'avait pas et que le web posait déjà. Sans elle,
     * `leconOuverte()` suffit à lire la première leçon de n'importe quel cours
     * payant — puis, de proche en proche, toutes les autres.
     *
     * Écrite ici plutôt que répétée : trois endpoints en ont besoin, et un
     * quatrième s'ajoutera. Une garde recopiée est une garde qu'on oublie.
     */
    private function estInscrit(User $utilisateur, Course $cours): bool
    {
        return Enrollment::where('user_id', $utilisateur->id)
            ->where('course_id', $cours->id)
            ->exists();
    }

    /**
     * `GET /courses/{slug}/lessons/{uuid}`
     *
     * Ouvre le chronomètre de lecture. Le serveur note l'heure d'ouverture :
     * la validation d'une leçon exige une durée de lecture minimale, et
     * accepter une durée annoncée par le client rendrait la règle décorative —
     * il suffirait d'envoyer « j'ai lu pendant deux heures ».
     */
    public function lecon(
        Request $requete,
        Course $course,
        Lesson $lesson,
        ProgressionAcademie $progression,
    ): JsonResponse {
        if (! $course->is_published || $lesson->course_id !== $course->id) {
            return Enveloppe::erreur(CodeErreur::Introuvable);
        }

        $utilisateur = $this->utilisateur($requete);

        /*
         | L'INSCRIPTION D'ABORD. C'est elle qui a été payée ; l'ouverture
         | séquentielle ne dit que dans quel ORDRE lire, pas si l'on y a droit.
         */
        if (! $this->estInscrit($utilisateur, $course)) {
            return Enveloppe::erreur(
                CodeErreur::DroitManquant,
                'Inscrivez-vous à ce cours pour en ouvrir les leçons.',
            );
        }

        if (! $progression->leconOuverte($utilisateur, $lesson)) {
            return Enveloppe::erreur(
                CodeErreur::DroitManquant,
                'Terminez la leçon précédente pour ouvrir celle-ci.',
            );
        }

        $lesson->setAttribute('ouverte', true);
        $lesson->setAttribute('acquise', $progression->leconAcquise($utilisateur, $lesson));

        Chronometre::demarrer('lecon', $utilisateur, (string) $lesson->uuid);

        return Enveloppe::donnee(LeconResource::make($lesson), meta: [
            'secondes_requises' => ProgressionAcademie::SECONDES_DE_LECTURE_REQUISES,
        ]);
    }

    /**
     * `POST /me/lessons/{uuid}/complete`
     *
     * La durée retenue est le MINIMUM entre ce que le client annonce et ce que
     * le serveur a mesuré depuis l'ouverture. Le client peut annoncer moins —
     * l'application met son compteur en pause quand elle passe en arrière-plan,
     * et c'est légitime. Il ne peut jamais annoncer plus.
     */
    public function terminerLaLecon(Request $requete, Lesson $lesson, ProgressionAcademie $progression): JsonResponse
    {
        $donnees = $requete->validate([
            'secondes' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        $utilisateur = $this->utilisateur($requete);

        /*
         | Même garde qu'à la lecture. Sans elle, valider une leçon sans être
         | inscrit ouvre la suivante — c'est ce maillon qui transformait la
         | lecture de la première leçon en accès au cours entier.
         */
        $lesson->loadMissing('course');

        if ($lesson->course === null || ! $this->estInscrit($utilisateur, $lesson->course)) {
            return Enveloppe::erreur(
                CodeErreur::DroitManquant,
                'Inscrivez-vous à ce cours pour en valider les leçons.',
            );
        }

        $ecoulees = Chronometre::ecoulees('lecon', $utilisateur, (string) $lesson->uuid);
        $retenues = min((int) $donnees['secondes'], $ecoulees);

        if (! $progression->marquerLeconLue($utilisateur, $lesson, $retenues)) {
            return Enveloppe::erreur(CodeErreur::ActionImpossible, sprintf(
                'Prenez le temps de lire : %d secondes de lecture active sont nécessaires.',
                ProgressionAcademie::SECONDES_DE_LECTURE_REQUISES,
            ));
        }

        $lesson->loadMissing('course');
        $inscription = $progression->rafraichir($utilisateur, $lesson->course);

        return Enveloppe::donnee(
            $inscription === null ? null : InscriptionResource::make($inscription->loadMissing('course')),
        );
    }

    /**
     * `GET /courses/{slug}/quiz`
     *
     * Rend le quiz du cours SANS les bonnes réponses, et démarre le
     * chronomètre côté serveur : `commence_le` est l'heure qui fait foi, pas
     * celle du téléphone.
     */
    public function quiz(Request $requete, Course $course): JsonResponse
    {
        if (! $course->is_published) {
            return Enveloppe::erreur(CodeErreur::Introuvable);
        }

        $quiz = $course->quizzes()->orderBy('position')->first();

        if ($quiz === null) {
            return Enveloppe::erreur(CodeErreur::Introuvable, "Ce cours ne comporte pas d'évaluation.");
        }

        $quiz->loadMissing('questions.answers');
        $debut = Chronometre::demarrer('quiz', $this->utilisateur($requete), (string) $quiz->uuid);

        return Enveloppe::donnee(QuizResource::make($quiz), meta: [
            'commence_le' => $debut->toIso8601String(),
        ]);
    }

    /**
     * `POST /me/quiz/{uuid}/attempt`
     *
     * La correction est SERVEUR. Le client n'envoie que ses choix ; il ne
     * reçoit ni le corrigé avant l'envoi, ni le moyen de le deviner.
     *
     * Les réponses arrivent indexées par UUID DE QUESTION — le client n'a
     * jamais vu d'identifiant entier de question. Le contrôleur retraduit vers
     * ce qu'attend `EvaluationQuiz` : c'est une traduction, pas une règle.
     */
    public function tenterLeQuiz(Request $requete, Quiz $quiz, EvaluationQuiz $evaluation): JsonResponse
    {
        $donnees = $requete->validate([
            /*
             | `present` et non `required` : un tableau VIDE est une copie
             | parfaitement légitime — celle de quelqu'un dont le chronomètre
             | est arrivé au bout sans qu'il réponde. `required` la refuserait,
             | et l'écran resterait bloqué sur un quiz qu'on ne peut plus
             | rendre.
             */
            'reponses' => ['present', 'array'],
            'reponses.*' => ['nullable', 'integer'],
        ]);

        $utilisateur = $this->utilisateur($requete);

        /*
         | Aucune garde n'existait ici — ni inscription, ni publication, ni
         | appartenance au cours. Or `EvaluationQuiz::corriger` incrémente l'XP
         | à la première réussite : n'importe quel compte pouvait parcourir le
         | catalogue et moissonner l'XP de TOUS les quiz de la plateforme, ce
         | qui fausse les niveaux et le classement.
         */
        $quiz->loadMissing(['questions', 'course']);

        if ($quiz->course === null
            || ! $quiz->course->is_published
            || ! $this->estInscrit($utilisateur, $quiz->course)) {
            return Enveloppe::erreur(
                CodeErreur::DroitManquant,
                'Inscrivez-vous à ce cours pour passer son quiz.',
            );
        }

        // Question inconnue = client modifié ou quiz remanié entre-temps : on
        // ignore plutôt que d'échouer. Une réponse manquante compte comme
        // fausse, ce que l'écran annonce avant de commencer.
        $parIdentifiant = [];

        foreach ($quiz->questions as $question) {
            $choix = $donnees['reponses'][$question->uuid] ?? null;
            $parIdentifiant[$question->id] = $choix === null ? null : (int) $choix;
        }

        $tentative = $evaluation->corriger(
            $utilisateur,
            $quiz,
            $parIdentifiant,
            Chronometre::ecoulees('quiz', $utilisateur, (string) $quiz->uuid),
        );

        return Enveloppe::donnee([
            'uuid' => $tentative->uuid,
            'score' => $tentative->score,
            'reussi' => $tentative->passed,
            'duree_s' => $tentative->duration_s,
            /*
             | L'explication n'arrive QU'ICI, une fois la copie rendue : c'est
             | la carte pédagogique du prototype. La livrer avec les questions
             | aurait donné le corrigé avant l'épreuve.
             */
            'corrige' => $quiz->questions->loadMissing('answers')->map(fn ($question): array => [
                'question' => $question->uuid,
                'bonne_reponse' => $question->answers->firstWhere('is_correct', true)?->id,
                'explication' => $question->explanation,
            ])->values(),
        ], statut: 201);
    }

    /** `GET /me/enrollments` */
    public function mesInscriptions(Request $requete): JsonResponse
    {
        $page = Enrollment::with('course')
            ->where('user_id', $this->utilisateur($requete)->id)
            ->orderByDesc('id')
            ->cursorPaginate(PageCurseur::taille($requete))
            ->withQueryString();

        return Enveloppe::page($page, InscriptionResource::collection($page->items()));
    }

    /**
     * `GET /me/courses/resume` — « Reprendre où j'en étais ».
     *
     * Rend `null` plutôt qu'un 404 quand il n'y a rien à reprendre : ce n'est
     * pas une erreur, c'est un état vide, et l'écran d'accueil doit pouvoir
     * l'afficher comme tel.
     */
    public function reprendre(Request $requete, ProgressionAcademie $progression): JsonResponse
    {
        $utilisateur = $this->utilisateur($requete);

        $inscription = Enrollment::with('course')
            ->where('user_id', $utilisateur->id)
            ->whereNotNull('course_id')
            ->orderByDesc('last_activity_at')
            ->first();

        if ($inscription === null || $inscription->course === null) {
            return Enveloppe::donnee(null);
        }

        $prochaine = $progression->prochaineLecon($utilisateur, $inscription->course);

        return Enveloppe::donnee([
            'inscription' => InscriptionResource::make($inscription),
            'cours' => CoursResource::make($inscription->course),
            'prochaine_lecon' => $prochaine === null ? null : LeconResource::make($prochaine),
        ]);
    }

    /** `GET /me/courses/{slug}/progress` */
    public function progression(Request $requete, Course $course, ProgressionAcademie $progression): JsonResponse
    {
        $utilisateur = $this->utilisateur($requete);

        $inscription = Enrollment::with('course')
            ->where('user_id', $utilisateur->id)
            ->where('course_id', $course->id)
            ->first();

        if ($inscription === null) {
            return Enveloppe::erreur(
                CodeErreur::Introuvable,
                "Vous n'êtes pas inscrit à ce cours.",
            );
        }

        $prochaine = $progression->prochaineLecon($utilisateur, $course);

        return Enveloppe::donnee([
            'inscription' => InscriptionResource::make($inscription),
            'avancement' => $progression->avancement($utilisateur, $course),
            'acheve' => $progression->estAcheve($utilisateur, $course),
            'prochaine_lecon' => $prochaine === null ? null : LeconResource::make($prochaine),
        ]);
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
