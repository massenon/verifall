<?php

declare(strict_types=1);

namespace App\Core\Exams\Services;

use App\Core\Application\Contracts\DepotExamens;
use App\Core\Exams\Exceptions\ExamenIndisponible;
use App\Core\Exams\Exceptions\QuestionInutilisable;
use App\Enums\StatutExamen;
use App\Enums\StatutSessionExamen;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSession;
use App\Models\QuestionBankItem;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Le montage d'une épreuve : l'examen, ses questions, ses sessions.
 *
 * Deux refus tiennent tout le service :
 *
 *   · on ne PUBLIE pas un examen sans question — il serait réussi à 100 % par
 *     tout le monde, et délivrerait une certification pour rien ;
 *   · on ne PLANIFIE pas une session sur un examen non publié — les candidats
 *     recevraient une convocation pour une épreuve qui n'existe pas encore.
 */
final class PlanificationExamen
{
    public function __construct(private readonly DepotExamens $depot) {}

    /**
     * @param  array{title: string, course_id?: int|null, description?: string|null, instructions?: string|null, pass_score?: int, duration_minutes?: int, max_attempts?: int}  $donnees
     */
    public function creer(array $donnees, ?User $auteur = null): Exam
    {
        return $this->depot->creer([
            'uuid' => (string) Str::uuid(),
            'slug' => $this->slugUnique($donnees['title']),
            'course_id' => $donnees['course_id'] ?? null,
            'title' => trim($donnees['title']),
            'description' => $donnees['description'] ?? null,
            'instructions' => $this->purifier($donnees['instructions'] ?? null),
            'pass_score' => $this->seuilValide($donnees['pass_score'] ?? 70),
            'duration_minutes' => max(1, $donnees['duration_minutes'] ?? 60),
            'max_attempts' => max(1, $donnees['max_attempts'] ?? 2),
            'status' => StatutExamen::Brouillon->value,
            'created_by' => $auteur?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function mettreAJour(Exam $examen, array $donnees): Exam
    {
        $attributs = [];

        foreach (['title', 'description', 'course_id'] as $champ) {
            if (array_key_exists($champ, $donnees)) {
                $attributs[$champ] = $donnees[$champ];
            }
        }

        if (array_key_exists('instructions', $donnees)) {
            $attributs['instructions'] = $this->purifier($donnees['instructions']);
        }

        if (isset($donnees['pass_score'])) {
            $attributs['pass_score'] = $this->seuilValide((int) $donnees['pass_score']);
        }

        if (isset($donnees['duration_minutes'])) {
            $attributs['duration_minutes'] = max(1, (int) $donnees['duration_minutes']);
        }

        if (isset($donnees['max_attempts'])) {
            $attributs['max_attempts'] = max(1, (int) $donnees['max_attempts']);
        }

        return $this->depot->mettreAJour($examen, $attributs);
    }

    public function publier(Exam $examen): Exam
    {
        if ($this->depot->questionsDe($examen)->isEmpty()) {
            throw new ExamenIndisponible(
                'Un examen sans question serait réussi par tout le monde : ajoutez au moins une question.'
            );
        }

        return $this->depot->mettreAJour($examen, [
            'status' => StatutExamen::Publie->value,
            'published_at' => now(),
        ]);
    }

    /**
     * Archive l'épreuve — les copies, les notes et les sessions restent.
     */
    public function archiver(Exam $examen): Exam
    {
        return $this->depot->mettreAJour($examen, ['status' => StatutExamen::Archive->value]);
    }

    // ----------------------------------------------------------- QUESTIONS

    public function ajouterUneQuestion(Exam $examen, QuestionBankItem $question, ?int $position = null): ExamQuestion
    {
        if (! $question->status->utilisable()) {
            throw new QuestionInutilisable(
                'Cette question est '.$question->status->libelle().' : seule une question publiée se pose en examen.'
            );
        }

        return $this->depot->attacherUneQuestion($examen, $question, $position);
    }

    public function retirerUneQuestion(Exam $examen, QuestionBankItem $question): void
    {
        $this->depot->detacherUneQuestion($examen, $question);
    }

    /**
     * Le barème total de l'épreuve, somme des points de ses questions.
     *
     * Calculé et non stocké sur l'examen : une colonne aurait dérivé au premier
     * changement de barème d'une question. La valeur est en revanche FIGÉE sur
     * chaque copie, au moment de composer.
     */
    public function baremeTotal(Exam $examen): int
    {
        return (int) $this->depot->questionsDe($examen)->sum('points');
    }

    // ------------------------------------------------------------ SESSIONS

    /**
     * @param  array{seats?: int|null, is_proctored?: bool, location?: string|null}  $options
     */
    public function planifierUneSession(Exam $examen, DateTimeInterface $debut, array $options = []): ExamSession
    {
        if (! $examen->estPublie()) {
            throw new ExamenIndisponible(
                'On ne convoque personne à un examen qui n\'est pas publié.'
            );
        }

        if ($this->depot->sessionExisteA($examen, $debut)) {
            /*
             | La base refuserait de toute façon (index unique), mais une
             | violation de contrainte remonte une erreur SQL illisible à
             | l'écran. Le service dit ce qui se passe.
             */
            throw new ExamenIndisponible('Une session de cet examen commence déjà à cette heure-là.');
        }

        return $this->depot->creerSession($examen, [
            'uuid' => (string) Str::uuid(),
            'starts_at' => $debut,
            'seats' => $options['seats'] ?? null,
            'is_proctored' => $options['is_proctored'] ?? true,
            'location' => $options['location'] ?? null,
            'status' => StatutSessionExamen::Planifiee->value,
        ]);
    }

    public function ouvrirUneSession(ExamSession $session): ExamSession
    {
        return $this->changerLEtatDeSession($session, StatutSessionExamen::Ouverte);
    }

    public function fermerUneSession(ExamSession $session): ExamSession
    {
        return $this->changerLEtatDeSession($session, StatutSessionExamen::Fermee);
    }

    public function annulerUneSession(ExamSession $session): ExamSession
    {
        return $this->changerLEtatDeSession($session, StatutSessionExamen::Annulee);
    }

    private function changerLEtatDeSession(ExamSession $session, StatutSessionExamen $etat): ExamSession
    {
        if ($session->status === StatutSessionExamen::Annulee) {
            throw new ExamenIndisponible(
                'Cette session est annulée : les candidats en ont été prévenus, elle ne se rouvre pas.'
            );
        }

        return $this->depot->mettreAJourSession($session, ['status' => $etat->value]);
    }

    /**
     * Un seuil hors de [1, 100] ne veut rien dire.
     *
     * Zéro rendrait l'examen impossible à rater, et 120 impossible à réussir :
     * deux façons différentes de ne rien évaluer.
     */
    private function seuilValide(int $seuil): int
    {
        return max(1, min(100, $seuil));
    }

    private function slugUnique(string $titre): string
    {
        $base = Str::slug($titre) ?: 'examen';
        $slug = $base;
        $suffixe = 2;

        while (! $this->depot->slugLibre($slug)) {
            $slug = $base.'-'.$suffixe;
            $suffixe++;
        }

        return $slug;
    }

    private function purifier(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return trim((string) Purifier::clean($html, 'default'));
    }
}
