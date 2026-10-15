<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Enums\StatutInscription;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `--seulement=academie` — les inscriptions de l'ancienne Académie, recâblées
 * sur les cours du nouveau catalogue.
 *
 * **Le raisonnement complet est dans `docs/MIGRATION_ANCIENNE_BASE.md`, §24 à
 * §28.** L'essentiel :
 *
 * ## Aucun cours n'est créé
 *
 * Deux cours anciens sur six ont un équivalent dans le catalogue semé
 * (`AcademieSeeder`) — même titre une fois normalisé. Leurs inscriptions sont
 * RECÂBLÉES sur le cours existant ; aucun doublon n'est créé.
 *
 * Les quatre autres sont ÉCARTÉS : deux déchets de test supprimés, et deux cours
 * réels dont la reprise produirait des cours cassés — voir `COURS_ECARTES`.
 *
 * ## La progression ne se transpose pas
 *
 * Le cours ancien et son équivalent nouveau portent le même titre, pas les
 * mêmes leçons : « Introduction au fact-checking » avait 2 leçons, le nouveau
 * en a 5 et un quiz ; « Un siècle de fact-checking » avait une vidéo et un
 * quiz, le nouveau a 6 leçons et un autre quiz. Aucune leçon ni aucun quiz
 * ancien n'a de jumeau : les 7 leçons lues et les 13 tentatives sont SAUTÉES et
 * comptées, jamais rattachées au hasard.
 *
 * L'avancement de l'inscription est donc 0 — ce que le produit calculerait
 * lui-même (`ProgressionAcademie` le dérive des leçons lues, jamais d'un
 * pourcentage reçu). Écrire l'ancien « 50 % » ferait mentir la jauge dès le
 * prochain recalcul.
 *
 * ## Aucun certificat n'est fabriqué
 *
 * L'ancienne table est vide. Si un dump plus récent en contient, l'import
 * S'ARRÊTE : un certificat porte un code vérifiable publiquement, et en
 * émettre un pour une formation qu'on n'a pas vu s'achever serait un faux.
 */
final class RepriseDeLAcademie
{
    use LitLAncienneBase;

    /**
     * Ancien slug → slug du cours nouveau.
     *
     * Chaque ligne est gardée par une comparaison des titres NORMALISÉS
     * (`Str::slug`) : si le cours cible est renommé un jour, l'import s'arrête
     * au lieu de rattacher des inscriptions à un autre cours.
     *
     * @var array<string, string>
     */
    private const COURS = [
        // « Introduction au fact-checking -  CLOM Vérif'All » (ancien)
        // « Introduction au fact-checking, CLOM Vérif'All » (nouveau). Même
        // description d'ouverture : « Ce premier cours constitue votre point
        // d'entrée dans le parcours de formation… ».
        'introduction-au-fact-checking-clom-verifall' => 'introduction-fact-checking',
        'un-siecle-de-fact-checking' => 'un-siecle-de-fact-checking',
    ];

    /**
     * Les cours anciens NON repris, chacun avec sa raison.
     *
     * Un cours ancien absent de `COURS` et d'ici arrête l'import en se nommant.
     *
     * @var array<string, string>
     */
    private const COURS_ECARTES = [
        'test-test-test-test-test' => 'déchet de test (titre « TEST TEST… », description de lettres au hasard)',
        'mljkhgfdxcgvbjklmlkjghgxgfchjk' => 'déchet de test (titre de lettres au hasard)',
        /*
         | Deux cours réels, et pourtant écartés.
         |
         | « Généralités sur les fake news » n'a plus qu'UNE leçon (deux ont été
         | supprimées dans l'ancienne base) : 243 caractères de consignes
         | d'exercice, ni vidéo ni quiz.
         |
         | « Journalistes vs Infox » serait CASSÉ : sa vidéo est un lien
         | YouTube, que le lecteur de leçon (`<video src>`) ne sait pas lire ;
         | son quiz n'a AUCUNE option ni bonne réponse (`options_data` et
         | `correct_answer` sont NULL sur toutes les questions du dump) ; son
         | PDF est un fichier de l'ancien disque, absent ici.
         |
         | Et pour les deux : publier un cours est une décision éditoriale qui
         | n'appartient pas à une reprise, et un cours en BROUILLON ne peut pas
         | porter d'inscriptions — le produit les refuse
         | (`InscriptionAuCours::inscrire`). Le contenu est inventorié dans le
         | document, §26. Une fois le cours récrit et publié, ajouter sa ligne à
         | `COURS` et relancer cette reprise rattachera ses inscrits.
         */
        'generalites-sur-les-fake-news-comprendre-pour-mieux-detecter' => 'sans équivalent dans le catalogue (une seule leçon restante de 243 caractères, ni vidéo ni quiz)',
        'journalistes-vs-infox' => 'sans équivalent dans le catalogue (vidéo YouTube illisible par le lecteur, quiz sans options ni réponses)',
    ];

    /**
     * Les tables pour lesquelles AUCUNE règle de reprise n'est écrite, parce
     * qu'elles sont vides dans le dump analysé. Non vides, elles arrêtent
     * l'import : écrire une règle à l'aveugle serait deviner.
     *
     * @var array<string, string>
     */
    private const TABLES_SANS_REGLE = [
        'certificates' => 'un certificat porte un code vérifiable : il ne se reprend pas sans décision',
        'assignment_submissions' => 'les devoirs anciens sont rattachés à des leçons, les nouveaux à des exercices de cours',
        'user_progress' => 'doublon probable de lesson_user, à examiner',
        'course_faqs' => 'le cours nouveau n’a pas de FAQ',
        'live_classes' => 'les classes en direct portent des liens Zoom qui ont expiré',
    ];

    /** @var array<string, int> */
    private array $compteurs = [];

    /** @var array<string, int> raison → nombre */
    private array $sauts = [];

    /** @var list<array{0: string, 1: string}> titre ancien, sort */
    private array $sortDesCours = [];

    public function __construct(private readonly Command $sortie) {}

    public function executer(string $chemin, bool $essai): int
    {
        $this->compteurs = ['cours_lus' => 0, 'cours_rapproches' => 0, 'cours_ecartes' => 0, 'inscriptions_lues' => 0, 'inscriptions_importees' => 0, 'achevements_non_transposes' => 0];
        $this->sauts = [];
        $this->sortDesCours = [];

        $cours = $this->lireTable($chemin, 'courses');
        $inscriptions = $this->lireTable($chemin, 'course_user');
        $leconsLues = $this->lireTable($chemin, 'lesson_user');
        $tentatives = $this->lireTable($chemin, 'quiz_attempts');
        $pieces = $this->lireTable($chemin, 'lesson_resources');

        $this->compteurs['cours_lus'] = count($cours);
        $this->compteurs['inscriptions_lues'] = count($inscriptions);

        $this->sortie->line(sprintf(
            '  %d cours, %d inscriptions, %d leçons lues, %d tentatives de quiz.',
            count($cours), count($inscriptions), count($leconsLues), count($tentatives),
        ));

        // 1. Le contrôle préalable, AVANT la première écriture.
        [$cibles, $faute] = $this->verifier($chemin, $cours);

        if ($faute !== null) {
            $this->sortie->error($faute);
            $this->sortie->line('  Rien n’a été écrit. Complétez la table de correspondance, puis relancez.');

            return Command::FAILURE;
        }

        $comptes = $this->comptesParAdresse($chemin);

        // 2. Les inscriptions — le seul fait qui se transpose.
        foreach ($inscriptions as $ancienne) {
            $cible = $cibles[(int) ($ancienne['course_id'] ?? 0)] ?? null;

            if ($cible === null) {
                $this->sauter('Inscription à un cours écarté ou introuvable');

                continue;
            }

            $compte = $comptes[(int) ($ancienne['user_id'] ?? 0)] ?? null;

            if ($compte === null) {
                $this->sauter('Inscription dont le compte est introuvable');

                continue;
            }

            if (Enrollment::query()->where('user_id', $compte)->where('course_id', $cible->id)->exists()) {
                // L'existant gagne — et c'est aussi ce qui rend l'import idempotent.
                $this->sauter('Inscription déjà présente (l’existant gagne)');

                continue;
            }

            if ($this->date($ancienne['completed_at'] ?? null) !== null) {
                // Compté sans empêcher l'inscription : l'achèvement portait sur
                // les leçons ANCIENNES, qui n'ont pas de jumeau.
                $this->compteurs['achevements_non_transposes']++;
            }

            $this->compteurs['inscriptions_importees']++;

            if ($essai) {
                continue;
            }

            DB::transaction(function () use ($ancienne, $compte, $cible): void {
                $inscrite = $this->date($ancienne['enrolled_at'] ?? null) ?? $this->date($ancienne['created_at'] ?? null) ?? now()->toDateTimeString();

                (new Enrollment)->forceFill([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $compte,
                    'course_id' => $cible->id,
                    'status' => StatutInscription::Inscrit->value,
                    // Voir l'en-tête : dérivé des leçons lues, et aucune ne se transpose.
                    'progress' => 0,
                    // Les deux cours cibles sont gratuits, et le contrôle
                    // préalable l'exige : rien n'a été payé, rien n'est dû.
                    'paid_units' => 0,
                    'started_at' => $inscrite,
                    'last_activity_at' => $this->date($ancienne['updated_at'] ?? null) ?? $inscrite,
                    'created_at' => $this->date($ancienne['created_at'] ?? null) ?? $inscrite,
                    'updated_at' => $this->date($ancienne['updated_at'] ?? null) ?? $inscrite,
                ])->save();

                // Le compteur dénormalisé, tenu dans la même transaction —
                // exactement comme `InscriptionAuCours::inscrire()`.
                Course::query()->whereKey($cible->id)->increment('students_count');
            });
        }

        // 3. Ce qui ne se transpose pas : compté, jamais rattaché au hasard.
        $coursDesLecons = $this->coursDesLecons($chemin);

        foreach ($leconsLues as $lue) {
            $this->sauter(isset($cibles[$coursDesLecons[(int) ($lue['lesson_id'] ?? 0)] ?? 0])
                ? 'Leçon lue sans leçon équivalente (structure du cours changée)'
                : 'Leçon lue d’un cours écarté');
        }

        $coursDesQuiz = $this->coursDesQuiz($chemin, $coursDesLecons);

        foreach ($tentatives as $tentative) {
            $this->sauter(isset($cibles[$coursDesQuiz[(int) ($tentative['quiz_id'] ?? 0)] ?? 0])
                ? 'Tentative de quiz sans quiz équivalent (questions anciennes sans réponses)'
                : 'Tentative de quiz d’un cours écarté');
        }

        foreach ($pieces as $piece) {
            $this->sauter('Pièce jointe non reprise (fichier de l’ancien disque, absent ici)');
        }

        $this->rapport($essai);

        return Command::SUCCESS;
    }

    /**
     * @param  list<array<string, string|null>>  $cours
     * @return array{0: array<int, Course>, 1: string|null} ancien `courses.id` → cours cible
     */
    private function verifier(string $chemin, array $cours): array
    {
        foreach (self::TABLES_SANS_REGLE as $table => $raison) {
            $lignes = count($this->lireTable($chemin, $table));

            if ($lignes > 0) {
                return [[], sprintf('La table ancienne « %s » contient %d ligne(s), et aucune règle de reprise n’est écrite pour elle : %s.', $table, $lignes, $raison)];
            }
        }

        $cibles = [];
        $fautes = [];

        foreach ($cours as $ancien) {
            $slug = Str::slug((string) ($ancien['slug'] ?? '')) ?: Str::slug((string) ($ancien['title'] ?? ''));
            $titre = $this->propre($ancien['title'] ?? null) ?? '(sans titre)';

            if (isset(self::COURS[$slug], self::COURS_ECARTES[$slug])) {
                $fautes[] = sprintf('« %s » est à la fois rapproché et écarté', $titre);

                continue;
            }

            if (isset(self::COURS_ECARTES[$slug])) {
                $this->sortDesCours[] = [$titre, 'écarté : '.self::COURS_ECARTES[$slug]];
                $this->compteurs['cours_ecartes']++;

                continue;
            }

            if (! isset(self::COURS[$slug])) {
                if ($this->date($ancien['deleted_at'] ?? null) !== null) {
                    // Supprimé dans l'ancienne base : il ne revient pas.
                    $this->sortDesCours[] = [$titre, 'écarté : supprimé dans l’ancienne base'];
                    $this->compteurs['cours_ecartes']++;

                    continue;
                }

                $fautes[] = sprintf('cours ancien sans correspondance « %s » (slug « %s ») — à ajouter à COURS ou COURS_ECARTES', $titre, $slug);

                continue;
            }

            $cible = Course::query()->where('slug', self::COURS[$slug])->first();

            if ($cible === null || ! $cible->is_published) {
                $fautes[] = sprintf('le cours cible « %s » est absent ou non publié — semez AcademieSeeder avant la reprise', self::COURS[$slug]);

                continue;
            }

            if (Str::slug($titre) !== Str::slug((string) $cible->title) && $slug !== $cible->slug) {
                $fautes[] = sprintf('« %s » ne ressemble plus à sa cible « %s » : la correspondance est périmée', $titre, $cible->title);

                continue;
            }

            if ($cible->cost_units > 0) {
                $fautes[] = sprintf('le cours cible « %s » est payant (%d unités) : l’inscription gratuite d’hier ne peut pas y ouvrir un accès sans décision', $cible->title, $cible->cost_units);

                continue;
            }

            $cibles[(int) ($ancien['id'] ?? 0)] = $cible;
            $this->sortDesCours[] = [$titre, 'rapproché de « '.$cible->title.' »'];
            $this->compteurs['cours_rapproches']++;
        }

        return $fautes === [] ? [$cibles, null] : [[], 'Académie : '.implode(' | ', $fautes)];
    }

    /**
     * Ancien `lessons.id` → ancien `courses.id`, par la section.
     *
     * @return array<int, int>
     */
    private function coursDesLecons(string $chemin): array
    {
        $coursDesSections = [];

        foreach ($this->lireTable($chemin, 'sections') as $section) {
            $coursDesSections[(int) ($section['id'] ?? 0)] = (int) ($section['course_id'] ?? 0);
        }

        $coursDesLecons = [];

        foreach ($this->lireTable($chemin, 'lessons') as $lecon) {
            $coursDesLecons[(int) ($lecon['id'] ?? 0)] = $coursDesSections[(int) ($lecon['section_id'] ?? 0)] ?? 0;
        }

        return $coursDesLecons;
    }

    /**
     * Ancien `quizzes.id` → ancien `courses.id`, par la leçon qui le porte.
     *
     * @param  array<int, int>  $coursDesLecons
     * @return array<int, int>
     */
    private function coursDesQuiz(string $chemin, array $coursDesLecons): array
    {
        $coursDesQuiz = [];

        foreach ($this->lireTable($chemin, 'quizzes') as $quiz) {
            $coursDesQuiz[(int) ($quiz['id'] ?? 0)] = $coursDesLecons[(int) ($quiz['lesson_id'] ?? 0)] ?? 0;
        }

        return $coursDesQuiz;
    }

    private function sauter(string $raison): void
    {
        $this->sauts[$raison] = ($this->sauts[$raison] ?? 0) + 1;
    }

    private function rapport(bool $essai): void
    {
        $this->sortie->newLine();
        $this->sortie->info($essai ? '— Académie : essai à blanc, rien n’a été écrit —' : '— Académie : reprise terminée —');

        $lignes = [
            ['Cours lus dans l’ancienne base', $this->compteurs['cours_lus']],
            ['Cours rapprochés d’un cours existant (aucun créé)', $this->compteurs['cours_rapproches']],
            ['Cours écartés', $this->compteurs['cours_ecartes']],
            ['Inscriptions lues', $this->compteurs['inscriptions_lues']],
            ['Inscriptions importées', $this->compteurs['inscriptions_importees']],
            ['dont achèvement ancien NON transposé (avancement repris à 0)', $this->compteurs['achevements_non_transposes']],
        ];

        foreach ($this->sauts as $raison => $nombre) {
            $lignes[] = ['Sautées · '.$raison, $nombre];
        }

        $lignes[] = ['Certificats repris', 0];

        $this->sortie->table(['Académie', 'Nombre'], $lignes);

        foreach ($this->sortDesCours as [$titre, $sort]) {
            $this->sortie->line(sprintf('  Cours · « %s » → %s', $titre, $sort));
        }
    }
}
