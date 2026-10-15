<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Core\Lms\Services\ConstructionDeCours;
use App\Core\Lms\Services\PiecesJointesDeLecon;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reprise ponctuelle et nommée des 4 cours historiques de `verifall_ancienne`
 * (NEWINSTRUCTION25) — commande séparée de `ImporterAncienneBase` et de
 * `RepriseDeLAcademie` pour ne rien risquer sur leur baseline déjà verte.
 *
 * ## Ce que `RepriseDeLAcademie` avait déjà tranché, et ce qui change ici
 *
 * `docs/MIGRATION_ANCIENNE_BASE.md` §24-28 documentait, le 14 septembre 2026,
 * que 2 des 4 cours anciens ont un jumeau DÉJÀ PUBLIÉ dans le catalogue semé
 * (contenu différent, sans aucune leçon ni quiz jumeau), et que les 2 autres
 * étaient ÉCARTÉS (contenu cassé ou quasi vide). Cette commande exécute une
 * décision éditoriale explicite, prise le 2 octobre 2026 avec l'auteur du
 * projet après lui avoir présenté ce même constat : les 4 cours reçoivent
 * leur contenu réel, les 2 existants par AJOUT (jamais en remplacement — zéro
 * risque sur les inscrits actuels), les 2 absents par création.
 *
 * ## Les quiz sans réponses enregistrées
 *
 * Trois des quatre quiz anciens ont des questions dont `options_data` et
 * `correct_answer` sont NULL en base — le nouveau schéma n'accepte pas une
 * question sans proposition correcte (`ConstructionDeCours::enregistrerUneQuestion`
 * le refuse). Deux correspondances réelles ont pu être RETROUVÉES, pas
 * inventées : la leçon ancienne id 7 (« Quiz - Journalistes vs Infox »,
 * supprimée en douceur) porte dans son HTML les propositions des questions de
 * quiz_id=1, et son texte sert aussi pour la question identique de quiz_id=2
 * (même énoncé mot pour mot : « Selon la Charte de Munich… »). Pour les
 * questions sans aucune trace de propositions nulle part dans le dump, les
 * distracteurs sont ÉCRITS, sur décision explicite de l'auteur du projet
 * (« si elles n'ont pas de quiz, il faudra en créer en rapport avec le
 * contenu ») — chaque bonne réponse reste ancrée dans le texte réel de
 * `explanation_xai`, jamais un fait inventé. Une question (`short_answer`,
 * « citez deux critiques… ») est sans équivalent MCQ fondé dans le texte
 * source : elle est VOLONTAIREMENT omise plutôt que de fabriquer un contenu
 * que personne n'a écrit.
 *
 * ## Les liens vidéo restent des liens YouTube
 *
 * `resources/views/livewire/citizen/lecon.blade.php:46` rend `video_url` dans
 * une balise `<video src>` native, qui ne lira pas un lien `youtu.be`. Décision
 * explicite de l'auteur du projet : les liens sont conservés tels quels malgré
 * cette limite connue du lecteur actuel — ce n'est pas corrigé ici.
 */
final class RepriseDuContenuDes4CoursHistoriques extends Command
{
    protected $signature = 'verifall:reprendre-contenu-4-cours-historiques
        {--essai : Lecture seule, n\'écrit rien}
        {--dossier-media=C:/Projects/verifall/courses : Dossier contenant les images de couverture et le sous-dossier ressources/ avec les PDF}';

    protected $description = 'Importe les leçons, ressources et quiz réels des 4 cours historiques (NEWINSTRUCTION25)';

    /** Posé au début de {@see handle()}, lu par les méthodes qui attachent un fichier réel. */
    private string $dossierMedia;

    public function handle(ConstructionDeCours $construction, PiecesJointesDeLecon $pieces): int
    {
        $essai = (bool) $this->option('essai');
        $this->dossierMedia = rtrim((string) $this->option('dossier-media'), '/');

        if ($essai) {
            $this->warn('--essai : lecture seule, rien ne sera écrit.');
        }

        $this->info('Base cible : '.DB::connection()->getDatabaseName().' (doit être "verifall").');

        if (DB::connection()->getDatabaseName() !== 'verifall') {
            $this->error('STOP : la base cible n\'est pas "verifall".');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $this->reprendreIntroduction($construction);
            $this->reprendreUnSiecle($construction, $pieces);
            $this->creerGeneralites($construction);
            $this->creerJournalistesVsInfox($construction, $pieces);
        } catch (RuntimeException $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($essai) {
            DB::rollBack();
            $this->warn('--essai : annulation de la transaction, rien n\'est resté en base.');
        } else {
            DB::commit();
        }

        $this->rapport();

        return self::SUCCESS;
    }

    // ───────────────────────────────────────────── Introduction (append)

    private function reprendreIntroduction(ConstructionDeCours $construction): void
    {
        $cours = Course::where('slug', 'introduction-fact-checking')->first();

        if ($cours === null) {
            throw new RuntimeException('Cours cible introuvable : introduction-fact-checking');
        }

        $this->ajouterSiAbsente($construction, $cours,
            'Partagez nous vos connaissances et/ou les sujets qui vous intéressent!',
            type: 'text', minutes: 5,
            contenu: "Dites nous ce que vous savez du fact-checking ou que vous souhaitez vous apprendre en venant ici ?\nDites nous les sujets qui vous intéressent!",
            video: null,
        );

        $this->ajouterSiAbsente($construction, $cours,
            "Le fact-checking, qu'est-ce que c'est ?",
            type: 'video', minutes: 5,
            contenu: "<p><em>Des journalistes internationaux de RFI et France 24 t'expliquent ce qu'est le fact-checking, pourquoi il est indispensable et comment il a évolué à l'ère des réseaux sociaux.</em></p><p><em>Source : Conseils de Journalistes  -  CFI &amp; France Médias Monde</em></p>",
            video: 'https://youtu.be/owOeqzExFUQ?si=PmG-lOWZrYmyvQcm',
        );

        if ($this->quizExiste($cours, 'Évaluation : Introduction au fact-checking (contenu historique)')) {
            return;
        }

        $quiz = $construction->ajouterUnQuiz($cours, [
            'titre' => 'Évaluation : Introduction au fact-checking (contenu historique)',
            'minutes' => 5, 'seuil' => 70, 'xp' => 0,
        ]);

        $construction->enregistrerUneQuestion($quiz, null,
            'Que propose cette première leçon du CLOM ?',
            'La leçon invite chacun à partager ce qu\'il connaît déjà du fact-checking et les sujets qu\'il aimerait explorer.',
            [
                ['label' => 'Partager ce qu\'on connaît déjà du fact-checking et les sujets qu\'on aimerait explorer', 'correcte' => true],
                ['label' => 'Passer un examen de niveau avant de commencer le cours', 'correcte' => false],
                ['label' => 'Choisir un mentor parmi la liste des formateurs', 'correcte' => false],
            ],
        );

        $construction->enregistrerUneQuestion($quiz, null,
            'Qui présente, dans la vidéo de cette leçon, ce qu\'est le fact-checking ?',
            'La vidéo est présentée par des journalistes de RFI et France 24, dans le cadre d\'un projet de Conseils de Journalistes (CFI et France Médias Monde).',
            [
                ['label' => 'Des journalistes de RFI et France 24', 'correcte' => true],
                ['label' => 'Une équipe de chercheurs universitaires', 'correcte' => false],
                ['label' => 'Le réseau Africa Check', 'correcte' => false],
                ['label' => 'La rédaction du Monde', 'correcte' => false],
            ],
        );
    }

    // ───────────────────────────────────────────── Un siècle de fact-checking (append)

    private function reprendreUnSiecle(ConstructionDeCours $construction, PiecesJointesDeLecon $pieces): void
    {
        $cours = Course::where('slug', 'un-siecle-de-fact-checking')->first();

        if ($cours === null) {
            throw new RuntimeException('Cours cible introuvable : un-siecle-de-fact-checking');
        }

        $titreLecon = 'Un siècle de fact-checking (vidéo historique)';
        $lecon = Lesson::where('course_id', $cours->id)->where('title', $titreLecon)->first();

        if ($lecon === null) {
            $lecon = $construction->ajouterUneLecon($cours, [
                'titre' => $titreLecon, 'type' => 'video', 'minutes' => 4,
                'contenu' => "<p>Saviez-vous que le fact-checking existe depuis plus d'un siècle ? Bien avant Internet et les réseaux sociaux, les journalistes avaient déjà développé des méthodes rigoureuses de vérification des faits.</p><p>Dans cette vidéo proposée par <strong>CFI Médias</strong>, découvrez l'histoire fascinante du fact-checking de sa naissance dans les rédactions américaines des années 1920 jusqu'à l'explosion des plateformes spécialisées à l'ère numérique.</p><p><em>Source : CFI Médias - Conseils de Journalistes</em></p>",
                'video' => 'https://youtu.be/yJbthP2Koxw?si=ScDXzzorzNltm_Sp',
            ]);
        }

        $this->attacherPdfSiAbsent($pieces, $lecon, 'Un siècle de fact-checking.pdf',
            $this->dossierMedia.'/ressources/y5xRKI7Y1ZMW6boOm22G7r1OWWfgv0UdfUZNWboM.pdf');

        if ($this->quizExiste($cours, 'Évaluation : Quiz (contenu historique)')) {
            return;
        }

        $quiz = $construction->ajouterUnQuiz($cours, [
            'titre' => 'Évaluation : Quiz (contenu historique)',
            'minutes' => 10, 'seuil' => 70, 'xp' => 0,
        ]);

        $construction->enregistrerUneQuestion($quiz, null,
            'Quelle est la première publication à avoir créé une équipe de fact-checkeurs ?',
            'Le Time Magazine est le pionnier du fact-checking institutionnel. En 1923, il crée la première équipe dédiée à la vérification des faits dans ses pages.',
            [
                ['label' => 'Time Magazine', 'correcte' => true],
                ['label' => 'Le New York Times', 'correcte' => false],
                ['label' => 'Reuters', 'correcte' => false],
                ['label' => 'The Washington Post', 'correcte' => false],
            ],
        );

        $construction->enregistrerUneQuestion($quiz, null,
            'Selon la Charte de Munich, quel est le premier devoir d\'un journaliste ?',
            'La Charte de Munich, adoptée en 1971, liste plusieurs devoirs fondamentaux du journalisme. Mais tous découlent du premier : respecter la vérité.',
            [
                ['label' => 'Publier rapidement avant les autres médias', 'correcte' => false],
                ['label' => 'Respecter la vérité quelles qu\'en soient les conséquences', 'correcte' => true],
                ['label' => 'Protéger ses sources à tout prix', 'correcte' => false],
                ['label' => 'Obtenir le maximum de vues', 'correcte' => false],
            ],
        );

        $construction->enregistrerUneQuestion($quiz, null,
            'Quels sont les deux facteurs qui ont provoqué la deuxième révolution du fact-checking dans les années 2000 ?',
            'Internet a permis à tout le monde de publier sans règles déontologiques. Le 11 septembre a généré une profusion de théories du complot. Ces deux phénomènes ont poussé les journalistes à créer des sites dédiés au fact-checking.',
            [
                ['label' => 'Internet et les attentats du 11 septembre 2001', 'correcte' => true],
                ['label' => 'La télévision et la guerre du Golfe', 'correcte' => false],
                ['label' => 'Les réseaux sociaux et la pandémie de Covid-19', 'correcte' => false],
                ['label' => 'La radio et la Seconde Guerre mondiale', 'correcte' => false],
            ],
        );

        $construction->enregistrerUneQuestion($quiz, null,
            'Combien de sites spécialisés dans le fact-checking compte-t-on aujourd\'hui dans le monde ?',
            'Le fact-checking s\'est considérablement développé grâce à Internet, portant le nombre total à plus de 300 sites spécialisés dans le monde.',
            [
                ['label' => 'Plus de 300', 'correcte' => true],
                ['label' => 'Une dizaine', 'correcte' => false],
                ['label' => 'Environ 50', 'correcte' => false],
                ['label' => 'Plus de 1 000', 'correcte' => false],
            ],
        );

        // La 5e question ancienne (« citez deux critiques… ») était de type
        // `short_answer`, sans équivalent QCM fondé dans le texte source :
        // omise plutôt qu'inventée.
    }

    // ───────────────────────────────────────────── Généralités sur les fake news (création)

    private function creerGeneralites(ConstructionDeCours $construction): void
    {
        $titre = 'Généralités sur les fake news - Comprendre pour mieux détecter';

        if (Course::where('title', $titre)->exists()) {
            return;
        }

        $instructeur = User::where('email', 'e.codjo@verifall.com')->first();

        $cours = new Course;
        $cours->forceFill([
            'uuid' => (string) Str::uuid(),
            'slug' => Str::slug($titre).'-'.Str::lower(Str::random(5)),
            'title' => $titre,
            'description' => "<p>Ce cours pose les bases conceptuelles indispensables avant d'entrer dans la pratique du fact-checking. Vous apprendrez à distinguer les différents types de fausses informations (fake news, hoax, infox…).</p>",
            'category' => 'Investigation',
            'course_category_id' => null,
            'level' => 'beginner',
            'duration_label' => '10 min',
            'cost_units' => 0,
            'outcomes' => [
                'Définir les concepts de fake news, hoax, infox, clickbait et deepfake',
                'Comprendre pourquoi et comment les fausses informations se propagent',
                'Identifier les différents formats de fake news',
                'Reconnaître les types de contenus manipulateurs dans son environnement numérique',
            ],
            'prerequisites' => ['Avoir complété le Cours : Introduction au fact-checking'],
            'language' => 'fr',
            'xp_reward' => 0,
            'instructor_id' => $instructeur?->id,
            'delivers_certificate' => true,
            'is_published' => true,
            'published_at' => now(),
        ])->save();

        $this->rangerLaCouverture($cours, $this->dossierMedia.'/w7MoPEx0YfE0y0MrBl72jJOnkqzkHRsrrCQsKF32.jpg');

        $lecon = $construction->ajouterUneLecon($cours, [
            'titre' => 'Fake news, hoax, infox ! De quoi parle-t-on ?',
            'type' => 'text', 'minutes' => 10,
            'contenu' => '<p>Exercices pratiques</p><ul><li>Prenez une info virale sur les réseaux sociaux.</li><li>Demandez-vous : qui publie ? Pourquoi ? quelles preuves ?</li><li>Cherchez si des médias fiables en parlent.</li><li>Classez-la : info fiable, fake news, hoax ou clickbait.</li></ul>',
            'video' => null,
        ]);

        $quiz = $construction->ajouterUnQuiz($cours, [
            'titre' => 'Évaluation : Généralités sur les fake news',
            'minutes' => 5, 'seuil' => 70, 'xp' => 0,
        ]);

        $construction->enregistrerUneQuestion($quiz, null,
            'Face à une info virale sur les réseaux sociaux, que recommande la méthode proposée dans cette leçon ?',
            'La leçon invite à se demander qui publie l\'information, pourquoi, et quelles preuves sont apportées.',
            [
                ['label' => 'Se demander qui publie, pourquoi, et quelles preuves sont apportées', 'correcte' => true],
                ['label' => 'La partager immédiatement pour prévenir ses proches', 'correcte' => false],
                ['label' => 'Juger uniquement sur le titre, sans ouvrir l\'article', 'correcte' => false],
            ],
        );

        $construction->enregistrerUneQuestion($quiz, null,
            'Que recommande cette leçon en cas de doute sur une information ?',
            'La leçon recommande de chercher si des médias fiables relaient la même information avant de la classer.',
            [
                ['label' => 'Chercher si des médias fiables en parlent', 'correcte' => true],
                ['label' => 'Supprimer la publication sans vérifier', 'correcte' => false],
                ['label' => 'Republier en ajoutant un point d\'interrogation', 'correcte' => false],
            ],
        );
    }

    // ───────────────────────────────────────────── Journalistes vs Infox (création)

    private function creerJournalistesVsInfox(ConstructionDeCours $construction, PiecesJointesDeLecon $pieces): void
    {
        $titre = 'Journalistes vs Infox';

        if (Course::where('title', $titre)->exists()) {
            return;
        }

        $instructeur = User::where('email', 'cfi@verifall.com')->first();

        $cours = new Course;
        $cours->forceFill([
            'uuid' => (string) Str::uuid(),
            'slug' => Str::slug($titre).'-'.Str::lower(Str::random(5)),
            'title' => $titre,
            'description' => "<p>Depuis le Moyen-Âge jusqu'aux réseaux sociaux, les fausses informations ont toujours existé. Mais jamais elles n'ont circulé aussi vite qu'aujourd'hui. Dans ce cours, des journalistes de RFI et France 24 reviennent sur l'histoire et l'impact des infox.</p>",
            'category' => 'Investigation',
            'course_category_id' => null,
            'level' => 'beginner',
            'duration_label' => '5 min',
            'cost_units' => 0,
            'outcomes' => [
                'Comprendre l\'histoire et l\'évolution des fausses informations',
                'Mesurer l\'impact réel des infox sur nos sociétés',
                'Comprendre le rôle du journaliste face aux fake news',
                'Saisir pourquoi la vérification est devenue un enjeu citoyen majeur',
            ],
            'prerequisites' => ['Avoir complété le Cours : Généralités sur les fake news'],
            'language' => 'fr',
            'xp_reward' => 0,
            'instructor_id' => $instructeur?->id,
            'delivers_certificate' => true,
            'is_published' => true,
            'published_at' => now(),
        ])->save();

        $this->rangerLaCouverture($cours, $this->dossierMedia.'/JebknidPbqSKhAc27dk3Ypz05zXtVpeh7B3HvPJq.jpg');

        $lecon = $construction->ajouterUneLecon($cours, [
            'titre' => 'Journalistes vs Infox - Vidéo CFI',
            'type' => 'video', 'minutes' => 5,
            'contenu' => "<p>En une minute, près de 42 millions de messages sont échangés sur WhatsApp et 500 heures de vidéos publiées sur YouTube. Dans ce flot d'informations, comment distinguer le vrai du faux ? Le fact-checking est la réponse.</p><p>Source : Conseils de Journalistes - un projet CFI et France Médias Monde</p>",
            'video' => 'https://youtu.be/qkvKmy6xXUY?si=0yFnysBm_5mGQxBQ',
        ]);

        $this->attacherPdfSiAbsent($pieces, $lecon, 'Journalistes vs Infox.pdf',
            $this->dossierMedia.'/ressources/Y0HGJCnP48Vmyk93EG5LkrFqK3klhr0nYtYzjTqZ.pdf');

        $quiz = $construction->ajouterUnQuiz($cours, [
            'titre' => 'Évaluation : Quiz - Journalistes vs Infox',
            'minutes' => 10, 'seuil' => 70, 'xp' => 0,
        ]);

        $construction->enregistrerUneQuestion($quiz, null,
            "L'eau salée présentée comme remède contre la Covid-19 est un exemple de :",
            "L'eau salée peut effectivement soigner une pharyngite, c'est vrai. Mais l'appliquer à la Covid-19 est faux. C'est exactement le mécanisme d'une infox dangereuse : elle emprunte une vérité partielle pour paraître crédible.",
            [
                ['label' => 'Une information vraie confirmée par des experts', 'correcte' => false],
                ['label' => 'Une infox qui s\'appuie sur une situation réelle pour tromper', 'correcte' => true],
                ['label' => 'Une blague inventée par des journalistes', 'correcte' => false],
                ['label' => 'Un canular sans conséquences', 'correcte' => false],
            ],
        );

        $construction->enregistrerUneQuestion($quiz, null,
            'Selon la Charte de Munich, quel est le premier devoir d\'un journaliste ?',
            'La Charte de Munich est le texte de référence de la déontologie journalistique. Elle place la vérité au-dessus de tout, même au-dessus des intérêts personnels du journaliste.',
            [
                ['label' => 'Publier rapidement avant les autres médias', 'correcte' => false],
                ['label' => 'Respecter la vérité quelles qu\'en soient les conséquences', 'correcte' => true],
                ['label' => 'Protéger ses sources à tout prix', 'correcte' => false],
                ['label' => 'Obtenir le maximum de vues', 'correcte' => false],
            ],
        );
    }

    // ───────────────────────────────────────────── internes

    private function ajouterSiAbsente(
        ConstructionDeCours $construction,
        Course $cours,
        string $titre,
        string $type,
        int $minutes,
        string $contenu,
        ?string $video,
    ): void {
        if (Lesson::where('course_id', $cours->id)->where('title', $titre)->exists()) {
            return;
        }

        $construction->ajouterUneLecon($cours, [
            'titre' => $titre, 'type' => $type, 'minutes' => $minutes,
            'contenu' => $contenu, 'video' => $video,
        ]);
    }

    private function quizExiste(Course $cours, string $titre): bool
    {
        return Quiz::where('course_id', $cours->id)->where('title', $titre)->exists();
    }

    private function attacherPdfSiAbsent(PiecesJointesDeLecon $pieces, Lesson $lecon, string $nomOriginal, string $cheminSource): void
    {
        if ($lecon->pieces()->where('original_name', $nomOriginal)->exists()) {
            return;
        }

        if (! is_file($cheminSource)) {
            throw new RuntimeException('FICHIER NON ASSOCIÉ : '.$cheminSource);
        }

        $fichier = new UploadedFile($cheminSource, $nomOriginal, 'application/pdf', null, true);

        $pieces->attacher($lecon, $fichier);
    }

    private function rangerLaCouverture(Course $cours, string $cheminSource): void
    {
        if (! is_file($cheminSource)) {
            throw new RuntimeException('FICHIER NON ASSOCIÉ : '.$cheminSource);
        }

        $extension = strtolower(pathinfo($cheminSource, PATHINFO_EXTENSION));
        $chemin = 'courses/'.$cours->uuid.'.'.$extension;

        Storage::disk('public')->put($chemin, file_get_contents($cheminSource));

        $cours->forceFill(['cover_path' => $chemin])->save();
    }

    private function rapport(): void
    {
        $this->info('');
        $this->info('--- Rapport ---');

        foreach (['introduction-fact-checking', 'un-siecle-de-fact-checking'] as $slug) {
            $c = Course::where('slug', $slug)->first();
            if ($c) {
                $this->line($slug.' : '.$c->lessons()->count().' leçons, '.$c->quizzes()->count().' quiz');
            }
        }

        foreach (['Généralités sur les fake news - Comprendre pour mieux détecter', 'Journalistes vs Infox'] as $titre) {
            $c = Course::where('title', $titre)->first();
            $this->line($titre.' : '.($c ? 'présent, '.$c->lessons()->count().' leçons, '.$c->quizzes()->count().' quiz' : 'ABSENT'));
        }
    }
}
