<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Core\Application\Contracts\DepotExamens;
use App\Core\Application\Contracts\DepotInscriptionsExamen;
use App\Core\Exams\Exceptions\CandidatNonInscrit;
use App\Core\Exams\Exceptions\CopieNonCorrigeable;
use App\Core\Exams\Exceptions\ExamenIndisponible;
use App\Core\Exams\Exceptions\TentativesEpuisees;
use App\Enums\StatutCopieExamen;
use App\Enums\TypeQuestion;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamRegistration;
use App\Models\ExamSession;
use App\Models\QuestionBankItem;
use App\Models\QuestionBankOption;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * La composition : ouvrir une copie, la rendre, la corriger si c'est possible.
 *
 * Le sujet est TIRÉ AU DÉMARRAGE. Les lignes de réponse sont écrites vides dès
 * l'ouverture de la copie, avec l'énoncé, le type et le barème recopiés. Sans
 * cela, un mentor qui ajoute une question pendant qu'une épreuve est en cours
 * changerait le sujet sous les yeux du candidat — et le total sur lequel on le
 * note ne serait plus celui qu'on lui a annoncé.
 *
 * La correction automatique s'arrête aux réponses libres : elles partent dans
 * la file de `correction-examens.html`, et la copie n'est notée qu'après.
 */
final class PassationExamen
{
    /** Sans O/0, I/1, S/5 : une référence de copie se dicte au téléphone. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    public function __construct(
        private readonly DepotCopiesExamen $copies,
        private readonly DepotExamens $examens,
        private readonly DepotInscriptionsExamen $inscriptions,
        private readonly DepotBanqueQuestions $banque,
        private readonly CorrectionExamen $correction,
    ) {}

    /**
     * Ouvre une copie — ou rend celle qui est déjà ouverte.
     *
     * Idempotent : recharger la page d'épreuve ne doit pas consommer une
     * tentative de rattrapage.
     */
    public function demarrer(User $candidat, ExamSession $session): ExamAttempt
    {
        $examen = $session->loadMissing('exam')->exam;

        if (! $examen instanceof Exam || ! $examen->estPublie()) {
            throw new ExamenIndisponible('Cet examen n\'est pas ouvert à la passation.');
        }

        if (! $session->status->accepteLaComposition()) {
            throw new ExamenIndisponible(
                'La session est '.$session->status->libelle().' : la composition n\'est pas ouverte.'
            );
        }

        $inscription = $this->inscriptions->pour($candidat, $session);

        if (! $inscription instanceof ExamRegistration || ! $inscription->status->autoriseLaComposition()) {
            throw new CandidatNonInscrit('Ce candidat n\'est pas convoqué à cette session.');
        }

        $ouverte = $this->copies->copieEnCours($candidat, $examen);

        if ($ouverte instanceof ExamAttempt) {
            return $ouverte;
        }

        $deja = $this->copies->nombreDeTentatives($candidat, $examen);

        if ($deja >= $examen->max_attempts) {
            throw new TentativesEpuisees(
                'Toutes les tentatives ont été utilisées ('.$examen->max_attempts.').'
            );
        }

        $questions = $this->examens->questionsDe($examen);

        if ($questions->isEmpty()) {
            throw new ExamenIndisponible('Cet examen ne contient aucune question.');
        }

        $copie = $this->copies->creer([
            'uuid' => (string) Str::uuid(),
            'reference' => $this->referenceUnique(),
            'exam_id' => $examen->id,
            'exam_session_id' => $session->id,
            'user_id' => $candidat->id,
            'attempt_no' => $deja + 1,
            'status' => StatutCopieExamen::EnComposition->value,
            'started_at' => now(),
            // FIGÉS : le seuil et le barème du jour où l'on compose.
            'pass_score' => $examen->pass_score,
            'max_points' => (int) $questions->sum('points'),
        ]);

        $this->copies->enregistrerLesReponses($copie, $this->sujet($questions));

        return $copie;
    }

    /**
     * Rend la copie et corrige tout ce qui se corrige seul.
     *
     * @param  array<int, array{options?: list<int>, text?: string|null, pairs?: array<int, string>}>  $reponses
     *                                                                                                            indexé par identifiant de question
     */
    public function soumettre(ExamAttempt $copie, array $reponses): ExamAttempt
    {
        if ($copie->status !== StatutCopieExamen::EnComposition) {
            throw new CopieNonCorrigeable('Cette copie a déjà été rendue.');
        }

        $examen = $copie->loadMissing('exam')->exam;

        if (! $examen instanceof Exam) {
            throw new ExamenIndisponible('L\'examen de cette copie a disparu.');
        }

        $questions = $this->examens->questionsDe($examen)->keyBy('id');

        foreach ($this->copies->reponsesDe($copie) as $ligne) {
            $this->remplir($ligne, $questions, $reponses[$ligne->question_bank_item_id] ?? []);
        }

        $rendue = $this->copies->mettreAJour($copie, [
            'submitted_at' => now(),
            'status' => StatutCopieExamen::EnAttente->value,
        ]);

        $resteDuLibre = $this->copies->reponsesDe($rendue)
            ->contains(fn (ExamAnswer $r): bool => $r->attendUneCorrection());

        if ($resteDuLibre) {
            // La note attend un mentor : la copie reste dans la file, et le
            // candidat le sait plutôt que de voir un score partiel.
            return $rendue;
        }

        return $this->correction->finaliser($rendue);
    }

    /**
     * Les lignes vides du sujet, dans l'ordre de l'épreuve.
     *
     * @param  Collection<int, QuestionBankItem>  $questions
     * @return list<array<string, mixed>>
     */
    private function sujet(Collection $questions): array
    {
        $lignes = [];
        $rang = 1;

        foreach ($questions as $question) {
            $lignes[] = [
                'question_bank_item_id' => $question->id,
                'position' => $rang,
                'prompt' => $question->prompt,
                'type' => $question->type->value,
                'points_max' => $question->points,
            ];
            $rang++;
        }

        return $lignes;
    }

    /**
     * Écrit ce qui a été répondu, et tranche si la machine sait le faire.
     *
     * @param  Collection<int, QuestionBankItem>  $questions
     * @param  array{options?: list<int>, text?: string|null, pairs?: array<int, string>}  $donnee
     */
    private function remplir(ExamAnswer $ligne, Collection $questions, array $donnee): void
    {
        $question = $questions->get($ligne->question_bank_item_id);

        $choisies = array_values(array_map('intval', $donnee['options'] ?? []));
        $paires = $donnee['pairs'] ?? [];
        $texte = isset($donnee['text']) ? trim((string) $donnee['text']) : null;

        $attributs = [
            'given_text' => $texte === '' ? null : $texte,
            'given_options' => $choisies === [] && $paires === [] ? null : ['options' => $choisies, 'pairs' => $paires],
        ];

        /*
         | Question absente de l'épreuve au moment de rendre — retirée entre le
         | démarrage et la soumission. La copie part en correction humaine : le
         | mentor a l'énoncé recopié sous les yeux et tranche. Noter zéro
         | d'office punirait le candidat d'une décision qui n'est pas la sienne.
         */
        if (! $question instanceof QuestionBankItem || ! $ligne->type->correctionAutomatique()) {
            $this->copies->mettreAJourUneReponse($ligne, $attributs);

            return;
        }

        foreach ($choisies as $optionId) {
            $this->banque->noterUnChoix($optionId);
        }

        $juste = $this->estJuste($question, $choisies, $paires);

        $this->copies->mettreAJourUneReponse($ligne, $attributs + [
            'is_correct' => $juste,
            // Tout ou rien : aucun écran ne propose de note partielle sur une
            // question fermée, et l'inventer rendrait les scores incomparables.
            'points_awarded' => $juste ? $ligne->points_max : 0,
        ]);
    }

    /**
     * @param  list<int>  $choisies
     * @param  array<int, string>  $paires
     */
    private function estJuste(QuestionBankItem $question, array $choisies, array $paires): bool
    {
        $options = $question->loadMissing('options')->options;

        return match ($question->type) {
            TypeQuestion::Qcm, TypeQuestion::VraiFaux => count($choisies) === 1
                && $options->firstWhere('id', $choisies[0])?->is_correct === true,

            // Ni oubli ni excédent : cocher toutes les cases donnerait
            // autrement 100 % à qui ne trie rien.
            TypeQuestion::MultiSelect => $this->memesIdentifiants(
                $choisies,
                $options->where('is_correct', true)->pluck('id')->all(),
            ),

            TypeQuestion::Appariement => $this->apparieTout($options, $paires),

            TypeQuestion::ReponseCourte => false,
        };
    }

    /**
     * @param  list<int>  $choisies
     * @param  list<int>  $attendues
     */
    private function memesIdentifiants(array $choisies, array $attendues): bool
    {
        sort($choisies);
        sort($attendues);

        return $attendues !== [] && $choisies === $attendues;
    }

    /**
     * @param  Collection<int, QuestionBankOption>  $options
     * @param  array<int, string>  $paires
     */
    private function apparieTout(Collection $options, array $paires): bool
    {
        $aRelier = $options->filter(
            static fn (QuestionBankOption $o): bool => $o->match_label !== null && $o->match_label !== ''
        );

        if ($aRelier->isEmpty()) {
            return false;
        }

        foreach ($aRelier as $option) {
            $propose = $paires[$option->id] ?? null;

            // Comparaison indulgente sur la casse et les espaces : un
            // appariement se juge sur l'appariement, pas sur la frappe.
            if ($propose === null || Str::lower(trim($propose)) !== Str::lower(trim((string) $option->match_label))) {
                return false;
            }
        }

        return true;
    }

    private function referenceUnique(): string
    {
        do {
            $reference = 'EX-'.$this->bloc(6);
        } while (! $this->copies->referenceLibre($reference));

        return $reference;
    }

    private function bloc(int $longueur): string
    {
        $sortie = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $longueur; $i++) {
            // random_int et non rand() : une référence prévisible laisserait
            // deviner l'URL d'une copie qui n'est pas la sienne.
            $sortie .= self::ALPHABET[random_int(0, $max)];
        }

        return $sortie;
    }
}
