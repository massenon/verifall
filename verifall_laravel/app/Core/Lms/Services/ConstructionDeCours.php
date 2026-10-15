<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Enums\TypeLecon;
use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;
use RuntimeException;

/**
 * L'écriture du contenu d'un cours — leçons, quiz, questions.
 *
 * ## Pourquoi un service, et pas le composant Livewire
 *
 * `CLAUDE.md` règle 1, mais surtout : deux invariants d'ici ne survivraient pas
 * à une réécriture depuis un écran.
 *
 * ### 1. Les positions sont UNIQUES par cours, et c'est un piège
 *
 * `lessons` porte `unique(['course_id', 'position'])`. Échanger deux leçons en
 * écrivant l'une puis l'autre viole la contrainte AU MILIEU de l'opération :
 *
 *     leçon A : position 2 → 3     ← 3 est déjà pris par B. Échec.
 *
 * Le réflexe est de retirer la contrainte. Ce serait perdre la seule chose qui
 * garantit qu'un cours n'a pas deux leçons n° 3 — et un plan de cours dont
 * l'ordre dépend de l'ordre d'insertion est un plan de cours qu'on ne peut pas
 * réparer. On passe donc par une position TAMPON, hors de portée.
 *
 * ### 2. Le HTML est purifié AVANT persistance
 *
 * Le contenu d'une leçon vient de Quill. `CLAUDE.md` l'exige aux deux bouts, et
 * la raison est nette : la leçon est rendue à des citoyens. Un seul chemin
 * d'écriture qui oublierait la purification suffirait à exécuter chez eux ce
 * qui a été stocké.
 */
final class ConstructionDeCours
{
    /**
     * Position hors d'atteinte, le temps d'un échange.
     *
     * `position` est un `unsignedSmallInteger` : 65 535 est son maximum, donc
     * une valeur qu'aucun plan de cours réel n'atteindra jamais.
     */
    private const TAMPON = 65535;

    // ————————————————————————————————————————————————— leçons

    /**
     * @param  array{titre: string, type: string, minutes: int, contenu: ?string, video: ?string, audio?: ?string}  $donnees
     */
    public function ajouterUneLecon(Course $cours, array $donnees): Lesson
    {
        return DB::transaction(function () use ($cours, $donnees): Lesson {
            $lecon = new Lesson;

            $lecon->forceFill([
                'uuid' => (string) Str::uuid(),
                'course_id' => $cours->id,
                // Toujours en fin de plan : insérer au milieu se fait ensuite
                // par déplacement, où la contrainte est gérée.
                'position' => $this->prochainePosition($cours),
            ] + $this->attributsDeLecon($donnees))->save();

            return $lecon;
        });
    }

    /**
     * @param  array{titre: string, type: string, minutes: int, contenu: ?string, video: ?string, audio?: ?string}  $donnees
     */
    public function modifierUneLecon(Lesson $lecon, array $donnees): Lesson
    {
        $lecon->forceFill($this->attributsDeLecon($donnees))->save();

        return $lecon;
    }

    /**
     * Supprime une leçon et REFERME le trou qu'elle laisse.
     *
     * Sans le resserrement, les positions deviendraient 1, 2, 4, 5. L'ordre
     * resterait juste — mais `prochainePosition()` et les échanges raisonnent
     * sur des rangs contigus, et le premier déplacement tomberait sur une
     * position libre en croyant l'échanger avec quelqu'un.
     */
    public function supprimerUneLecon(Lesson $lecon): void
    {
        DB::transaction(function () use ($lecon): void {
            $coursId = $lecon->course_id;
            $position = (int) $lecon->position;

            $lecon->delete();

            Lesson::query()
                ->where('course_id', $coursId)
                ->where('position', '>', $position)
                ->decrement('position');
        });
    }

    /**
     * Déplace une leçon d'un rang, vers le haut ou vers le bas.
     *
     * @param  'haut'|'bas'  $sens
     */
    public function deplacerUneLecon(Lesson $lecon, string $sens): void
    {
        DB::transaction(function () use ($lecon, $sens): void {
            $position = (int) $lecon->position;
            $cible = $sens === 'haut' ? $position - 1 : $position + 1;

            $voisine = Lesson::query()
                ->where('course_id', $lecon->course_id)
                ->where('position', $cible)
                ->first();

            // Déjà en bout de plan : rien à faire, et surtout pas d'erreur.
            // Le bouton est masqué, mais un appel direct ne doit pas casser.
            if ($voisine === null) {
                return;
            }

            /*
             | L'ÉCHANGE EN TROIS TEMPS, imposé par `unique(course_id, position)`.
             | En deux temps — A prend la place de B, puis B celle de A — la
             | première écriture heurte déjà la contrainte.
             */
            $lecon->forceFill(['position' => self::TAMPON])->save();
            $voisine->forceFill(['position' => $position])->save();
            $lecon->forceFill(['position' => $cible])->save();
        });
    }

    // ————————————————————————————————————————————————— quiz

    /**
     * @param  array{titre: string, minutes: int, seuil: int, xp: int}  $donnees
     */
    public function ajouterUnQuiz(Course $cours, array $donnees): Quiz
    {
        $quiz = new Quiz;

        $quiz->forceFill([
            'uuid' => (string) Str::uuid(),
            'course_id' => $cours->id,
            'position' => (int) Quiz::query()->where('course_id', $cours->id)->max('position') + 1,
            'title' => $donnees['titre'],
            'minutes' => $donnees['minutes'],
            'pass_score' => $donnees['seuil'],
            'xp_reward' => $donnees['xp'],
        ])->save();

        return $quiz;
    }

    /**
     * @param  array{titre: string, minutes: int, seuil: int, xp: int}  $donnees
     */
    public function modifierUnQuiz(Quiz $quiz, array $donnees): Quiz
    {
        $quiz->forceFill([
            'title' => $donnees['titre'],
            'minutes' => $donnees['minutes'],
            'pass_score' => $donnees['seuil'],
            'xp_reward' => $donnees['xp'],
        ])->save();

        return $quiz;
    }

    public function supprimerUnQuiz(Quiz $quiz): void
    {
        // `questions` et `answers` sont en cascade au schéma : rien à défaire
        // à la main, et surtout rien à oublier.
        $quiz->delete();
    }

    // ————————————————————————————————————————————————— questions

    /**
     * Écrit une question et SES propositions, d'un bloc.
     *
     * Les deux ne se séparent pas : une question sans propositions ne peut pas
     * être posée, et une question dont on aurait enregistré l'énoncé mais pas
     * les réponses casserait le quiz pour tous les inscrits — silencieusement,
     * puisque rien n'oblige un quiz à avoir des questions valides.
     *
     * @param  list<array{label: string, correcte: bool}>  $propositions
     *
     * @throws RuntimeException si aucune proposition n'est marquée correcte
     */
    public function enregistrerUneQuestion(
        Quiz $quiz,
        ?Question $question,
        string $enonce,
        ?string $explication,
        array $propositions,
    ): Question {
        $propositions = array_values(array_filter(
            $propositions,
            static fn (array $p): bool => trim($p['label']) !== '',
        ));

        if (count($propositions) < 2) {
            throw new RuntimeException('Une question demande au moins deux propositions.');
        }

        /*
         | LA GARDE QUI COMPTE. Une question sans bonne réponse est notée
         | « fausse » quoi que le citoyen coche : il perd le quiz sans pouvoir
         | comprendre pourquoi, et le mentor ne le découvre que par des
         | statistiques d'échec à 100 %.
         */
        if (! collect($propositions)->contains(fn (array $p): bool => $p['correcte'])) {
            throw new RuntimeException('Marquez au moins une proposition comme correcte.');
        }

        return DB::transaction(function () use ($quiz, $question, $enonce, $explication, $propositions): Question {
            $question ??= new Question;

            $question->forceFill([
                'uuid' => $question->uuid ?? (string) Str::uuid(),
                'quiz_id' => $quiz->id,
                'position' => $question->position
                    ?? (int) Question::query()->where('quiz_id', $quiz->id)->max('position') + 1,
                'prompt' => $enonce,
                'explanation' => $explication,
            ])->save();

            /*
             | Les propositions sont REMPLACÉES, pas fusionnées. Fusionner
             | demanderait de faire correspondre les anciennes aux nouvelles par
             | leur libellé — et corriger une faute de frappe créerait alors une
             | proposition de plus au lieu de la corriger.
             |
             | Le compteur `picked_count` repart donc à zéro. C'est le bon
             | comportement : il compte combien de fois une proposition a été
             | choisie, et une proposition réécrite n'est plus la même question
             | posée.
             */
            $question->answers()->delete();

            foreach ($propositions as $rang => $proposition) {
                Answer::query()->create([
                    'question_id' => $question->id,
                    'position' => $rang + 1,
                    'label' => trim($proposition['label']),
                    'is_correct' => $proposition['correcte'],
                ]);
            }

            return $question->refresh();
        });
    }

    public function supprimerUneQuestion(Question $question): void
    {
        DB::transaction(function () use ($question): void {
            $quizId = $question->quiz_id;
            $position = (int) $question->position;

            $question->delete();

            Question::query()
                ->where('quiz_id', $quizId)
                ->where('position', '>', $position)
                ->decrement('position');
        });
    }

    // ————————————————————————————————————————————————— internes

    /**
     * @param  array{titre: string, type: string, minutes: int, contenu: ?string, video: ?string, audio?: ?string}  $donnees
     * @return array<string, mixed>
     */
    private function attributsDeLecon(array $donnees): array
    {
        $type = TypeLecon::from($donnees['type']);

        return [
            'title' => $donnees['titre'],
            'type' => $type->value,
            'minutes' => $donnees['minutes'],
            /*
             | PURIFIÉ AVANT PERSISTANCE. La leçon est rendue à des citoyens ;
             | un seul chemin d'écriture qui l'oublierait suffirait à exécuter
             | chez eux ce qui a été stocké.
             */
            'content' => $this->purifier($donnees['contenu']),
            /*
             | L'adresse de la vidéo n'est gardée que pour une leçon vidéo.
             | Changer le type d'une leçon sans vider l'autre champ laisserait
             | une leçon « texte » pointant encore sur une vidéo — invisible à
             | l'écran, et prête à ressurgir au prochain changement de type.
             */
            'video_url' => $type === TypeLecon::Video ? $donnees['video'] : null,

            /*
             | Même règle pour l'audio, et pour la même raison : une leçon qui
             | change de type ne doit pas conserver l'adresse de l'autre
             | support. Sans ce vidage, une leçon repassée en « texte »
             | garderait un enregistrement invisible à l'écran, prêt à
             | ressurgir au prochain changement de type.
             */
            'audio_url' => $type === TypeLecon::Audio ? ($donnees['audio'] ?? null) : null,
        ];
    }

    private function purifier(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $propre = trim((string) Purifier::clean($html, 'default'));

        return $propre === '' ? null : $propre;
    }

    private function prochainePosition(Course $cours): int
    {
        return (int) Lesson::query()->where('course_id', $cours->id)->max('position') + 1;
    }
}
