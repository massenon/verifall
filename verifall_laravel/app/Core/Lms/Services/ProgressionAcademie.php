<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Core\Gamification\Services\JournalDXp;
use App\Core\Marketing\Emails\ExpediteurDeCourriels;
use App\Enums\MotifGainXp;
use App\Enums\PreferenceNotification;
use App\Enums\StatutDevoir;
use App\Enums\StatutInscription;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Où en est quelqu'un dans un cours, et ce qu'il a le droit d'ouvrir.
 *
 * Toutes les règles d'avancement de l'Académie vivent ICI, à un seul endroit.
 * Le prototype les répartit entre six pages ; les recopier dans six composants
 * Livewire garantirait qu'un jour la fiche du cours et la page « Mes tutoriels »
 * affichent deux pourcentages différents pour la même personne.
 *
 * Trois règles, reprises du prototype :
 *
 *   · L'avancement est un RAPPORT d'étapes franchies sur étapes totales —
 *     leçons, quiz réussis, exercices remis. Recalculé, jamais incrémenté : un
 *     compteur dérive au premier événement rejoué.
 *   · Les leçons se débloquent DANS L'ORDRE. Sauter à la dernière pour cocher
 *     le cours viderait le certificat de son sens.
 *   · Une leçon n'est acquise qu'après un TEMPS DE LECTURE minimal. Sans lui,
 *     terminer un cours se réduit à cliquer six fois sur « Suivant ».
 */
final class ProgressionAcademie
{
    public function __construct(
        private readonly DelivranceCertificat $certificats,
        private readonly JournalDXp $journalXp,
        private readonly ExpediteurDeCourriels $courriels,
    ) {}

    /**
     * Secondes de lecture active exigées par leçon.
     *
     * Le prototype fixe 45 (`LESSON_REQUIRED_SECONDS`). C'est court — et c'est
     * voulu : le seuil doit décourager le clic mécanique, pas punir quelqu'un
     * qui lit vite.
     */
    public const SECONDES_DE_LECTURE_REQUISES = 45;

    /**
     * Avancement en pourcentage, arrondi.
     *
     * Un cours sans aucune étape rend 0 et non 100 : annoncer « terminé » sur
     * un cours vide serait absurde, et délivrerait un certificat pour rien.
     */
    public function avancement(User $utilisateur, Course $cours): int
    {
        $total = $cours->nombreDEtapes();

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->etapesFranchies($utilisateur, $cours) / $total) * 100);
    }

    /**
     * Le cours est-il achevé ?
     *
     * Non pas « avancement à 100 % » mais chaque condition vérifiée
     * séparément : un arrondi à 100 % sur un cours à 199 étapes laisserait
     * passer un certificat pour un cours inachevé.
     */
    public function estAcheve(User $utilisateur, Course $cours): bool
    {
        $total = $cours->nombreDEtapes();

        return $total > 0 && $this->etapesFranchies($utilisateur, $cours) === $total;
    }

    /**
     * Cette leçon est-elle ouverte à cette personne ?
     *
     * La première l'est toujours ; les suivantes attendent que la précédente
     * soit acquise.
     */
    public function leconOuverte(User $utilisateur, Lesson $lecon): bool
    {
        $precedente = Lesson::where('course_id', $lecon->course_id)
            ->where('position', '<', $lecon->position)
            ->orderByDesc('position')
            ->first();

        return $precedente === null || $this->leconAcquise($utilisateur, $precedente);
    }

    public function leconAcquise(User $utilisateur, Lesson $lecon): bool
    {
        return LessonCompletion::where('user_id', $utilisateur->id)
            ->where('lesson_id', $lecon->id)
            ->exists();
    }

    /**
     * La prochaine leçon à lire — celle sur laquelle « Continuer » atterrit.
     */
    public function prochaineLecon(User $utilisateur, Course $cours): ?Lesson
    {
        $acquises = LessonCompletion::where('user_id', $utilisateur->id)
            ->whereIn('lesson_id', $cours->lessons()->select('id'))
            ->pluck('lesson_id')
            ->all();

        return $cours->lessons()->whereNotIn('id', $acquises)->first();
    }

    /**
     * Enregistre une leçon lue.
     *
     * Refuse si le temps de lecture est insuffisant, et le dit franchement en
     * rendant `false` : l'écran doit pouvoir expliquer, pas échouer en silence.
     *
     * `firstOrCreate` et non `create` : un double-clic sur « Terminer » ne doit
     * pas produire deux lignes — la contrainte d'unicité les refuserait, mais
     * avec une exception au visage de la personne.
     */
    /**
     * La prime de ce cours a-t-elle déjà été versée a cette personne ?
     *
     * Sert a l'écran de leçon, qui compare AVANT et APRES pour savoir s'il doit
     * annoncer la prime. Sans cette comparaison, il l'annoncerait à chaque
     * relecture de la dernière leçon d'un cours déjà terminé.
     */
    public function primeDejaVersee(User $utilisateur, Course $cours): bool
    {
        return Enrollment::where('user_id', $utilisateur->id)
            ->where('course_id', $cours->id)
            ->whereNotNull('xp_awarded_at')
            ->exists();
    }

    public function marquerLeconLue(User $utilisateur, Lesson $lecon, int $secondes): bool
    {
        if ($secondes < self::SECONDES_DE_LECTURE_REQUISES) {
            return false;
        }

        if (! $this->leconOuverte($utilisateur, $lecon)) {
            return false;
        }

        LessonCompletion::firstOrCreate(
            ['user_id' => $utilisateur->id, 'lesson_id' => $lecon->id],
            ['seconds' => $secondes, 'completed_at' => now()],
        );

        // Chargé explicitement : un accès paresseux ajoute une requête à
        // chaque leçon terminée, et casse net dès qu'on l'interdit.
        $this->rafraichir($utilisateur, $lecon->loadMissing('course')->course);

        return true;
    }

    /**
     * Recalcule l'inscription après un événement.
     *
     * Appelé après CHAQUE progression. L'avancement, le statut et la date de
     * dernière activité sont dérivés des faits, jamais bricolés à la main.
     */
    public function rafraichir(User $utilisateur, Course $cours): ?Enrollment
    {
        $inscription = Enrollment::where('user_id', $utilisateur->id)
            ->where('course_id', $cours->id)
            ->first();

        if ($inscription === null) {
            return null;
        }

        $avancement = $this->avancement($utilisateur, $cours);
        $acheve = $this->estAcheve($utilisateur, $cours);

        // Relevé AVANT : c'est la TRANSITION qui se félicite. `xp_awarded_at`
        // n'est posé qu'une fois, donc il dit exactement « ce cours vient de
        // s'achever », là où `completed_at` reste vrai à chaque relecture.
        $deja = $inscription->xp_awarded_at !== null;

        // Remonté de la transaction : la félicitation en a besoin pour son
        // lien, et tous les cours n'en délivrent pas.
        $certificat = null;

        $inscription = DB::transaction(function () use ($utilisateur, $cours, $inscription, $avancement, $acheve, &$certificat): Enrollment {
            $inscription->forceFill([
                'progress' => $avancement,
                'status' => match (true) {
                    $acheve => StatutInscription::Termine->value,
                    $avancement > 0 => StatutInscription::EnCours->value,
                    default => StatutInscription::Inscrit->value,
                },
                'last_activity_at' => now(),
                // Figée à la PREMIÈRE complétion : la réécrire à chaque
                // événement ferait d'un cours suivi sur trois mois un cours
                // terminé le jour même.
                'completed_at' => $acheve ? ($inscription->completed_at ?? now()) : null,
            ])->save();

            /*
             | Le diplôme suit la complétion, immédiatement. L'attendre d'un job
             | ou d'un clic supplémentaire ferait arriver quelqu'un sur une page
             | « certificat » vide au moment précis où il vient de finir.
             */
            if ($acheve) {
                $certificat = $this->certificats->delivrer($utilisateur, $cours);
                $this->verserLaPrime($utilisateur, $cours, $inscription);
            }

            return $inscription->refresh();
        });

        /*
         | La félicitation part APRÈS la transaction, jamais dedans.
         |
         | `Mail::queue()` écrit dans la table `jobs`, qui participe à la même
         | transaction : un rollback plus loin laisserait un courriel annoncé
         | pour un cours qui n'est plus terminé. L'inverse — un courriel perdu
         | sur un cours bel et bien fini — est le moindre mal.
         */
        if ($acheve && ! $deja) {
            $this->courriels->envoyer(
                $utilisateur,
                'cours_termine',
                [
                    'prenom' => $utilisateur->name,
                    'cours' => $cours->title,
                    'xp' => (int) $cours->xp_reward,
                ],
                /*
                 | Le certificat quand il existe, la fiche du cours sinon.
                 |
                 | `delivrer()` rend `null` dès que `delivers_certificate` est
                 | faux — et `route('citizen.certificate')` attend un
                 | certificat, pas un cours. Lui passer le cours levait une
                 | `UrlGenerationException` À L'ENVOI : le courriel n'échouait
                 | pas, c'est la fin de la leçon qui plantait.
                 */
                $certificat !== null
                    ? route('citizen.certificate', ['certificate' => $certificat])
                    : route('citizen.course', ['course' => $cours]),
                // Le seul des quatre interrupteurs existants qui recouvre
                // l'Académie. Une félicitation n'est pas tout à fait un rappel,
                // mais inventer une cinquième préférence créerait un réglage
                // qu'aucun écran n'affiche.
                PreferenceNotification::RappelsApprentissage,
            );
        }

        return $inscription;
    }

    /**
     * Verse la prime d'XP du cours, UNE FOIS.
     *
     * ## La marque, et pourquoi elle est indispensable
     *
     * `rafraichir()` s'exécute à chaque leçon lue, chaque quiz corrigé, chaque
     * exercice remis. Une personne qui relit une leçon après avoir terminé
     * repasse par la branche d'achèvement : sans `xp_awarded_at`, la prime
     * serait versée autant de fois qu'on rouvre le cours. Cinq relectures d'un
     * cours à 250 XP, et voilà deux paliers de niveau gagnés en rouvrant une page.
     *
     * `completed_at` ne suffit pas : elle dit QUAND on a fini, pas si la prime
     * a été payée.
     *
     * ## La marque est posée même quand la prime vaut zéro
     *
     * Elle enregistre que le moment de l'attribution est PASSÉ, pas qu'une
     * somme a été versée. Un mentor qui fixe la récompense après coup ne paiera
     * donc pas rétroactivement ceux qui avaient déjà terminé : la prime est
     * celle qui était annoncée quand ils ont suivi le cours — la seule règle
     * qu'on puisse leur expliquer.
     *
     * ## Indépendante du certificat
     *
     * Un cours peut récompenser sans diplômer, et l'inverse. Les deux gestes se
     * suivent ici parce qu'ils partagent le même déclencheur, pas parce que
     * l'un dépend de l'autre.
     */
    private function verserLaPrime(User $utilisateur, Course $cours, Enrollment $inscription): void
    {
        if ($inscription->xp_awarded_at !== null) {
            return;
        }

        $prime = (int) $cours->xp_reward;

        if ($prime > 0) {
            $this->journalXp->crediter($utilisateur, $prime, MotifGainXp::Cours, $cours);
        }

        $inscription->forceFill(['xp_awarded_at' => now()])->save();
    }

    /**
     * Étapes franchies : leçons lues + quiz réussis + exercices remis.
     *
     * Un exercice REMIS compte, même non corrigé : quelqu'un qui a fait le
     * travail ne doit pas voir son avancement bloqué parce qu'un mentor n'a pas
     * encore eu le temps de le lire.
     */
    private function etapesFranchies(User $utilisateur, Course $cours): int
    {
        $lecons = LessonCompletion::where('user_id', $utilisateur->id)
            ->whereIn('lesson_id', $cours->lessons()->select('id'))
            ->count();

        $quiz = QuizAttempt::where('user_id', $utilisateur->id)
            ->where('passed', true)
            ->whereIn('quiz_id', $cours->quizzes()->select('id'))
            // Plusieurs tentatives réussies sur le même quiz ne comptent
            // qu'une fois, sinon repasser un quiz gonflerait l'avancement.
            ->distinct('quiz_id')
            ->count('quiz_id');

        $exercices = AssignmentSubmission::where('user_id', $utilisateur->id)
            ->whereIn('status', [StatutDevoir::Remis->value, StatutDevoir::Valide->value])
            ->whereIn('assignment_id', $cours->assignments()->select('id'))
            ->count();

        return $lecons + $quiz + $exercices;
    }

    /** Ce quiz a-t-il déjà été réussi ? */
    public function quizReussi(User $utilisateur, Quiz $quiz): bool
    {
        return QuizAttempt::where('user_id', $utilisateur->id)
            ->where('quiz_id', $quiz->id)
            ->where('passed', true)
            ->exists();
    }

    /** Cet exercice a-t-il déjà été remis ? */
    public function remiseDe(User $utilisateur, Assignment $exercice): ?AssignmentSubmission
    {
        return AssignmentSubmission::where('user_id', $utilisateur->id)
            ->where('assignment_id', $exercice->id)
            ->first();
    }
}
