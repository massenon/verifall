<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NiveauCours;
use App\Enums\TypeLecon;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cinq cours au contenu RÉEL.
 *
 * ## Ce qui les distingue du catalogue existant
 *
 * `AcademieSeeder` reprend les quatre cours du prototype. Ses leçons portent
 * toutes le même texte d'illustration — et le seeder le dit lui-même :
 * « c'est une limite du prototype, pas un choix ». Un apprenant qui ouvre
 * quatre leçons différentes y lit quatre fois la même chose.
 *
 * Ceux-ci ont un contenu écrit. Chaque leçon enseigne un geste précis,
 * vérifiable, qu'on peut refaire le soir même sur son téléphone.
 *
 * ## Pourquoi ces cinq sujets
 *
 * Ils viennent des modes de désinformation que le produit dit combattre, dans
 * l'ordre où ils frappent le public visé : l'image recontextualisée, la vidéo
 * sortie de son contexte, l'arnaque au recrutement, la rumeur de santé, et la
 * méthode de sourçage qui sous-tend les quatre autres.
 *
 * L'arnaque au recrutement n'est pas là par hasard : c'est la seule
 * désinformation de cette liste qui coûte directement de l'argent à celui qui
 * y croit, et elle vise des jeunes diplômés.
 *
 * ## Ce qu'aucun de ces cours ne fait
 *
 * Aucun n'affiche de note ni de nombre d'inscrits. Ils partent à zéro, comme
 * les autres : une moyenne calculée sur aucun avis serait un chiffre inventé,
 * sur la plateforme dont le métier est de dire ce qui est vrai.
 */
final class CoursReelsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->cours() as $donnees) {
            DB::transaction(fn () => $this->planter($donnees));
        }

        $this->command?->info(sprintf(
            '  %d cours réels · %d leçons écrites.',
            count($this->cours()),
            Lesson::whereIn('course_id', Course::whereIn('slug', array_column($this->cours(), 'slug'))->pluck('id'))->count(),
        ));
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function planter(array $donnees): void
    {
        $cours = Course::withTrashed()->firstOrNew(['slug' => $donnees['slug']]);

        $cours->forceFill([
            'uuid' => $cours->uuid ?? (string) Str::uuid(),
            'title' => $donnees['titre'],
            'description' => $donnees['description'],
            'category' => $donnees['categorie'],
            'level' => $donnees['niveau']->value,
            'duration_label' => $donnees['duree'],
            'cover_path' => $donnees['couverture'],
            'cost_units' => $donnees['cout'],
            'outcomes' => $donnees['acquis'],
            'instructor_id' => $this->formateur()?->id,
            'delivers_certificate' => true,
            // Zéro, comme partout : voir l'en-tête de classe.
            'rating' => 0,
            'students_count' => 0,
            'is_published' => true,
            'published_at' => $cours->published_at ?? now(),
            'deleted_at' => null,
        ])->save();

        /*
         | Remplacé, pas complété. Un seeder qui ajoute sans nettoyer double les
         | leçons au second passage — et l'avancement de chaque apprenant passe
         | silencieusement de 100 % à 50 %.
         */
        $cours->lessons()->delete();

        foreach ($donnees['lecons'] as $rang => $lecon) {
            Lesson::create([
                'uuid' => (string) Str::uuid(),
                'course_id' => $cours->id,
                'position' => $rang + 1,
                'title' => $lecon['titre'],
                'type' => TypeLecon::Texte->value,
                'minutes' => $lecon['minutes'],
                'content' => $lecon['contenu'],
            ]);
        }
    }

    private function formateur(): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'Mentor'))->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cours(): array
    {
        return [
            [
                'slug' => 'verifier-une-image-recyclee',
                'titre' => 'Vérifier une image recyclée',
                'categorie' => 'investigation',
                'niveau' => NiveauCours::Debutant,
                'duree' => '45 min',
                'cout' => 0,
                'couverture' => 'assets/images/courses/placeholder-a.svg',
                'description' => "Le mode de désinformation le plus courant n'est pas l'image truquée : "
                    ."c'est l'image AUTHENTIQUE sortie de son contexte. Apprenez à retrouver d'où "
                    .'elle vient et quand elle est apparue.',
                'acquis' => [
                    'Faire une recherche inversée depuis un téléphone',
                    "Lire les indices internes d'une photographie",
                    'Dater une image sans outil payant',
                    'Formuler un verdict qui distingue « faux » de « hors contexte »',
                ],
                'lecons' => [
                    [
                        'titre' => 'Pourquoi le recyclage bat le trucage',
                        'minutes' => 8,
                        'contenu' => <<<'HTML'
                        <p>Truquer une image demande du temps, un logiciel et un savoir-faire. La recycler n'en demande aucun : il suffit d'enregistrer une photographie ancienne et de la republier avec une légende neuve.</p>

                        <p>C'est pourquoi la <strong>recontextualisation</strong> est, de très loin, le mode de désinformation visuelle le plus répandu. Elle a aussi un avantage redoutable pour qui la pratique : l'image <em>est</em> vraie. Elle résiste donc à tous les outils qui cherchent des traces de retouche — il n'y en a pas.</p>

                        <h3>La conséquence pour votre méthode</h3>

                        <p>La bonne question n'est pas « cette image a-t-elle été modifiée ? » mais <strong>« cette image montre-t-elle ce qu'on prétend qu'elle montre ? »</strong>. Ce sont deux enquêtes différentes, et seule la seconde répond au cas majoritaire.</p>

                        <p>Concrètement, vous cherchez trois choses, dans cet ordre :</p>

                        <ol>
                        <li><strong>Où</strong> cette image est-elle déjà apparue ?</li>
                        <li><strong>Quand</strong> est-elle apparue pour la première fois ?</li>
                        <li><strong>Que</strong> disait-on d'elle à ce moment-là ?</li>
                        </ol>

                        <p>Si la première apparition est antérieure à l'événement qu'on lui prête, l'affaire est close — et vous n'avez examiné aucun pixel.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'La recherche inversée depuis un téléphone',
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>La recherche inversée consiste à donner une image à un moteur et à lui demander où il l'a déjà vue. Elle se fait entièrement depuis un téléphone, sans application supplémentaire.</p>

                        <h3>Le geste, pas à pas</h3>

                        <ol>
                        <li>Enregistrez l'image (appui long, « Télécharger l'image »). Si elle vient d'une capture d'écran, <strong>recadrez d'abord</strong> pour ne garder que la photographie : l'interface de WhatsApp ou de Facebook autour de l'image brouille la recherche.</li>
                        <li>Ouvrez le moteur d'images de votre navigateur et déposez le fichier.</li>
                        <li>Lisez les résultats <strong>par date croissante</strong>, pas par pertinence. C'est la page la plus ANCIENNE qui vous intéresse, pas la plus populaire.</li>
                        </ol>

                        <h3>Ce que la recherche inversée ne dit pas</h3>

                        <p>Elle ne donne <strong>pas</strong> la date de prise de vue. Elle donne la date des pages où l'image apparaît, ce qui n'est pas la même chose. Une image de 2015 publiée pour la première fois en ligne en 2019 ressortira comme « 2019 ».</p>

                        <p>Autrement dit, la recherche inversée établit une <em>borne</em> : l'image existe <strong>au moins</strong> depuis cette date. C'est suffisant pour démonter la majorité des recyclages, et c'est tout ce qu'elle prouve. Écrire « première apparition le 3 mars 2019 » quand on veut dire « déjà en ligne le 3 mars 2019 » est une affirmation de trop.</p>

                        <h3>Quand elle ne trouve rien</h3>

                        <p>Zéro résultat ne veut pas dire « image inédite ». Cela veut dire que ce moteur-là n'a rien indexé. Essayez un second moteur avant de conclure quoi que ce soit — et si les deux restent muets, votre verdict est <strong>« non vérifiable »</strong>, pas « authentique ».</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Lire les indices internes',
                        'minutes' => 12,
                        'contenu' => <<<'HTML'
                        <p>Quand la recherche inversée ne donne rien, l'image elle-même reste bavarde. Voici ce qu'on y lit, du plus fiable au moins fiable.</p>

                        <h3>Ce qui trahit un lieu</h3>

                        <ul>
                        <li><strong>La langue des enseignes et des panneaux.</strong> Un panneau en anglais dans une scène qu'on situe à Cotonou mérite une explication.</li>
                        <li><strong>Les plaques d'immatriculation.</strong> Leur format et leurs couleurs varient d'un pays à l'autre.</li>
                        <li><strong>Le côté de circulation.</strong> Le Bénin roule à droite ; le Nigéria voisin aussi, mais le Ghana également et le Togo aussi — cet indice élimine des continents, pas des pays.</li>
                        <li><strong>La végétation et l'architecture.</strong> Le moins précis, mais suffisant pour écarter une image manifestement asiatique ou européenne.</li>
                        </ul>

                        <h3>Ce qui trahit une date</h3>

                        <ul>
                        <li><strong>La saison.</strong> Une rue inondée pendant la saison sèche pose une question.</li>
                        <li><strong>Les vêtements et les objets.</strong> Un modèle de téléphone, une affiche électorale, un logo d'entreprise dans sa version précédente.</li>
                        <li><strong>Les ombres.</strong> Elles donnent une heure approximative — utile surtout pour repérer une <em>incohérence</em> entre deux éléments d'une même image.</li>
                        </ul>

                        <h3>Le piège des métadonnées</h3>

                        <p>Les données EXIF d'une photographie contiennent parfois la date et le lieu. Deux réserves, et elles sont sérieuses :</p>

                        <p><strong>Elles disparaissent au partage.</strong> WhatsApp, Facebook et la plupart des réseaux les effacent. Une image reçue par WhatsApp n'en a plus, et leur absence ne prouve donc <em>rien</em>.</p>

                        <p><strong>Elles se modifient en trois clics.</strong> Une date EXIF n'est pas une preuve : c'est une déclaration, qui vaut ce que vaut celui qui l'a écrite. Elle appuie une conclusion ; elle ne la fonde pas.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Rédiger un verdict qui tient',
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>Un verdict mal formulé se retourne contre celui qui l'écrit. Trois règles suffisent à l'éviter.</p>

                        <h3>1. Distinguer « faux » de « hors contexte »</h3>

                        <p>Ce sont deux verdicts différents, et les confondre est l'erreur la plus fréquente des débutants.</p>

                        <p><strong>Faux</strong> : l'image a été fabriquée ou modifiée pour tromper.<br>
                        <strong>Hors contexte</strong> : l'image est authentique, mais la légende ment.</p>

                        <p>Écrire « cette photo est fausse » à propos d'une vraie photographie donne à celui qui la partage un argument imparable : il montrera qu'elle est authentique, et c'est <em>vous</em> qui perdrez votre crédit. Or vous aviez raison sur le fond.</p>

                        <h3>2. Dire ce qu'on a vérifié, et ce qu'on n'a pas vérifié</h3>

                        <p>« L'image circule en ligne depuis au moins mars 2019, soit trois ans avant l'événement auquel on l'associe » est solide : c'est une constatation, avec sa borne.</p>

                        <p>« L'image date de 2019 » est plus court, plus net — et invérifiable. La première formulation résiste à la contradiction ; la seconde s'écroule dès qu'on demande la preuve.</p>

                        <h3>3. Donner le chemin, pas seulement l'arrivée</h3>

                        <p>Un verdict sans méthode demande qu'on vous croie sur parole. C'est exactement ce que fait la rumeur d'en face.</p>

                        <p>Indiquez l'outil employé, la page la plus ancienne trouvée, sa date. Quelqu'un doit pouvoir refaire votre vérification et arriver au même endroit. C'est cette reproductibilité — et non votre autorité — qui fait la différence entre un fact-check et une opinion.</p>
                        HTML,
                    ],
                ],
            ],

            [
                'slug' => 'verifier-une-video-partagee',
                'titre' => 'Vérifier une vidéo partagée',
                'categorie' => 'investigation',
                'niveau' => NiveauCours::Intermediaire,
                'duree' => '50 min',
                'cout' => 0,
                'couverture' => 'assets/images/courses/placeholder-b.svg',
                'description' => "Une vidéo emporte l'adhésion plus vite qu'un texte, et se vérifie "
                    .'presque aussi simplement — à condition de la traiter comme une suite '
                    .'d\'images et un son, pas comme un bloc.',
                'acquis' => [
                    'Extraire des images clés et les vérifier une par une',
                    'Repérer une vidéo remontée ou raccourcie',
                    'Écouter ce que la bande son révèle',
                    "Savoir ce qu'on ne peut PAS détecter, et le dire",
                ],
                'lecons' => [
                    [
                        'titre' => 'Décomposer plutôt que regarder',
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>Une vidéo n'est pas un objet indivisible. C'est une suite d'images et une bande son — chacune vérifiable séparément, avec les méthodes que vous connaissez déjà.</p>

                        <h3>La méthode en trois passages</h3>

                        <p><strong>Premier passage — sans le son.</strong> Coupez le son et regardez. Vous cherchez le décor, les enseignes, les véhicules, les vêtements : exactement les indices d'une photographie. Le son porte l'émotion et le commentaire ; il vous empêche de voir.</p>

                        <p><strong>Deuxième passage — sans l'image.</strong> Écoutez seulement. La langue parlée, l'accent, les bruits de fond, la présence ou l'absence d'écho.</p>

                        <p><strong>Troisième passage — les coupes.</strong> Repérez chaque changement de plan. Une vidéo « brute » qui contient six coupes n'est pas brute : quelqu'un a choisi ce qu'on voit et ce qu'on ne voit pas.</p>

                        <h3>Extraire une image clé</h3>

                        <p>Mettez en pause sur un plan qui montre le décor, faites une capture d'écran, recadrez pour retirer l'interface du lecteur, puis lancez une recherche inversée. C'est tout.</p>

                        <p>Choisissez de préférence le <strong>premier</strong> et le <strong>dernier</strong> plan : ce sont ceux qu'on oublie de recadrer quand on republie, et ils portent souvent un logo de chaîne ou un bandeau d'origine.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Ce que la bande son raconte',
                        'minutes' => 12,
                        'contenu' => <<<'HTML'
                        <p>Le son est le parent pauvre de la vérification. C'est un tort : il se falsifie moins souvent que l'image, et il trahit davantage.</p>

                        <h3>Quatre signaux à écouter</h3>

                        <p><strong>La langue et l'accent.</strong> Une scène qu'on situe au Bénin où l'on parle une langue qui n'y est pas courante mérite une explication. Attention toutefois : la région est très plurilingue, et l'argument doit rester prudent.</p>

                        <p><strong>Les bruits de fond.</strong> Une sirène a une signature différente selon les pays. Un appel à la prière, un chant d'oiseau, un type de klaxon situent une scène mieux qu'un décor flou.</p>

                        <p><strong>La cohérence acoustique.</strong> Une voix sans écho sur une scène tournée dans une grande salle a été enregistrée ailleurs. C'est le signe d'un commentaire ajouté après coup.</p>

                        <p><strong>Le silence.</strong> Une foule visible mais inaudible signale que le son d'origine a été remplacé — souvent par une musique, précisément pour éviter qu'on entende ce qui s'y disait.</p>

                        <h3>Le cas des voix synthétiques</h3>

                        <p>Les outils actuels produisent des voix crédibles. Certains indices subsistent : respiration absente, débit trop régulier, liaisons étranges sur les noms propres.</p>

                        <p>Mais soyons nets : <strong>ces indices ne suffisent pas à conclure</strong>, et ils s'effacent d'année en année. Face à une voix suspecte, la bonne démarche n'est pas d'analyser le timbre — c'est de chercher si la personne a réellement tenu ces propos, ailleurs, dans une source vérifiable.</p>
                        HTML,
                    ],
                    [
                        'titre' => "Les limites qu'il faut annoncer",
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>Une leçon honnête doit dire ce que la méthode ne permet pas. Sur la vidéo, c'est beaucoup.</p>

                        <h3>Ce que vous ne pouvez pas détecter de façon fiable</h3>

                        <ul>
                        <li><strong>Un hypertrucage bien fait.</strong> Les artefacts visibles — clignements absents, contours flous autour du visage — appartiennent aux générations précédentes. S'appuyer dessus aujourd'hui produit des faux négatifs.</li>
                        <li><strong>Une vidéo entièrement générée.</strong> Aucun indice interne fiable, à l'échelle d'un examen humain.</li>
                        <li><strong>Un ralenti ou une accélération légers.</strong> Ils changent la perception d'une scène — une démarche devient titubante — sans laisser de trace visible.</li>
                        </ul>

                        <h3>Ce que vous pouvez faire à la place</h3>

                        <p>Renverser la charge. Plutôt que de prouver qu'une vidéo est fausse, cherchez si elle est <strong>vraie</strong> : la scène a-t-elle été rapportée par ailleurs ? Existe-t-il un autre angle, un autre témoin, une couverture par un média identifiable ?</p>

                        <p>Un événement réel de cette importance laisse des traces multiples. Une vidéo unique, sans aucun écho, est en elle-même un signal — non pas la preuve d'un trucage, mais la raison de suspendre son jugement.</p>

                        <h3>Et si vous ne pouvez pas conclure</h3>

                        <p>Dites-le. <strong>« Non vérifiable »</strong> est un verdict, pas un échec. Il informe : il dit que la prudence est de mise et que le partage est prématuré. C'est déjà beaucoup plus utile qu'un « probablement vrai » qui n'engage personne.</p>
                        HTML,
                    ],
                ],
            ],

            [
                'slug' => 'dejouer-une-arnaque-au-recrutement',
                'titre' => 'Déjouer une arnaque au recrutement',
                'categorie' => 'securite',
                'niveau' => NiveauCours::Debutant,
                'duree' => '40 min',
                'cout' => 0,
                'couverture' => 'assets/images/courses/placeholder-c.svg',
                'description' => 'La seule désinformation de ce catalogue qui coûte directement de '
                    ."l'argent à celui qui y croit. Elle vise les jeunes diplômés, et elle se "
                    .'reconnaît à cinq signes.',
                'acquis' => [
                    "Reconnaître les cinq signes d'une fausse offre",
                    "Vérifier l'existence réelle d'un employeur",
                    'Distinguer un frais légitime d\'un frais inventé',
                    'Savoir quoi faire quand on a déjà payé',
                ],
                'lecons' => [
                    [
                        'titre' => 'Les cinq signes',
                        'minutes' => 12,
                        'contenu' => <<<'HTML'
                        <p>Une fausse offre d'emploi n'a pas besoin d'être sophistiquée. Elle a besoin d'arriver au bon moment, sur quelqu'un qui cherche. Voici ce qui la distingue.</p>

                        <h3>1. On vous demande de l'argent</h3>

                        <p>C'est le signe décisif, et il suffit à lui seul. <strong>Un employeur ne fait jamais payer pour recruter.</strong> Ni « frais de dossier », ni « frais de formation obligatoire », ni « caution pour le matériel », ni « frais de visite médicale à régler d'avance ».</p>

                        <p>Toute demande d'argent avant le premier salaire est une arnaque. Il n'existe pas d'exception à connaître.</p>

                        <h3>2. Le salaire ne correspond à rien</h3>

                        <p>Comparez au marché réel du poste, dans votre pays. Un montant très au-dessus n'est pas une chance : c'est l'appât, et il est calibré pour couvrir votre prudence.</p>

                        <h3>3. Le recrutement va trop vite</h3>

                        <p>Embauché après un échange WhatsApp de dix minutes, sans entretien, sans vérification de vos diplômes ? Aucun employeur sérieux ne procède ainsi. La vitesse sert à vous empêcher de vérifier.</p>

                        <h3>4. Le contact ne ressemble pas à une entreprise</h3>

                        <p>Une adresse en <code>@gmail.com</code> pour un « service des ressources humaines ». Un numéro WhatsApp comme seul point de contact. Aucune adresse postale. Un site web créé le mois dernier — ou pas de site du tout.</p>

                        <h3>5. On vous presse</h3>

                        <p>« Cinq places, réponse avant ce soir. » L'urgence est l'outil principal de l'escroquerie : elle empêche exactement ce que vous apprenez ici, c'est-à-dire prendre vingt minutes pour vérifier.</p>

                        <p><strong>Une offre légitime survit à vingt minutes de vérification.</strong> Aucune escroquerie n'y survit. C'est la seule règle à retenir de cette leçon.</p>
                        HTML,
                    ],
                    [
                        'titre' => "Vérifier qu'un employeur existe",
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>Une entreprise réelle laisse des traces multiples et anciennes. Une entreprise inventée laisse une page et un numéro.</p>

                        <h3>Les quatre vérifications, dans l'ordre</h3>

                        <p><strong>Cherchez le nom seul, puis le nom avec « arnaque » ou « scam ».</strong> Le second test est le plus rentable : quelqu'un a souvent signalé avant vous.</p>

                        <p><strong>Cherchez l'adresse.</strong> Une entreprise qui recrute a des locaux. Une adresse introuvable, ou qui correspond à un terrain vague sur une carte, tranche la question.</p>

                        <p><strong>Remontez à la source de l'offre.</strong> Si elle circule sur WhatsApp, cherchez-la sur le site officiel de l'entreprise. Une offre réelle y figure. Une offre absente du site officiel n'existe pas — même si elle porte le logo.</p>

                        <p><strong>Appelez le standard officiel, pas le numéro de l'offre.</strong> Trouvez le numéro sur le site de l'entreprise, appelez, demandez si ce recrutement existe. C'est la vérification la plus efficace du lot, et la moins pratiquée.</p>

                        <h3>Le piège du logo</h3>

                        <p>Un logo se copie en trois secondes. Sa présence ne prouve rien — absolument rien. Les escroqueries les plus efficaces empruntent le nom d'entreprises réelles et connues, précisément parce que le nom rassure.</p>

                        <p>La question n'est jamais « ce logo est-il le bon ? » mais <strong>« cette offre vient-elle de cette entreprise ? »</strong>. Seul le canal officiel répond.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Quand on a déjà payé',
                        'minutes' => 8,
                        'contenu' => <<<'HTML'
                        <p>Cette leçon existe parce que la honte fait plus de dégâts que l'escroquerie elle-même : elle empêche de signaler, et le suivant tombe dans le même piège.</p>

                        <h3>Ce qu'il faut faire tout de suite</h3>

                        <ol>
                        <li><strong>Cessez tout paiement.</strong> L'escroquerie fonctionne par paliers : le premier versement en appelle un second, « pour débloquer le dossier ». Le seul montant que vous maîtrisez encore est le prochain.</li>
                        <li><strong>Gardez tout.</strong> Captures d'écran de la conversation, numéro de transaction, numéro de téléphone, nom du bénéficiaire. Ces éléments sont ce qui rend un signalement exploitable.</li>
                        <li><strong>Signalez à l'opérateur.</strong> Un transfert Mobile Money laisse une trace ; le numéro bénéficiaire peut être suspendu, ce qui protège les suivants même si votre argent est perdu.</li>
                        <li><strong>Signalez publiquement.</strong> Sur Vérif'All, dans le forum, ou à l'entreprise dont le nom a été usurpé — elle a intérêt à le savoir.</li>
                        </ol>

                        <h3>Sur la honte</h3>

                        <p>Ces escroqueries sont conçues par des gens dont c'est le métier, et elles visent un moment de vulnérabilité — la recherche d'emploi. Y avoir cru ne dit rien de votre intelligence.</p>

                        <p>Ce qui compte maintenant est ce que vous en faites : un signalement transforme une perte individuelle en protection collective. C'est exactement le geste que cette plateforme cherche à rendre banal.</p>
                        HTML,
                    ],
                ],
            ],

            [
                'slug' => 'les-rumeurs-de-sante',
                'titre' => 'Les rumeurs de santé',
                'categorie' => 'sante',
                'niveau' => NiveauCours::Intermediaire,
                'duree' => '45 min',
                'cout' => 0,
                'couverture' => 'assets/images/courses/placeholder-a.svg',
                'description' => 'Un faux remède ne se contente pas de tromper : il retarde un vrai '
                    .'traitement. Apprenez à évaluer une affirmation médicale sans être médecin.',
                'acquis' => [
                    "Reconnaître la forme d'une fausse promesse thérapeutique",
                    'Remonter à une source médicale fiable',
                    'Comprendre pourquoi « naturel » ne veut pas dire « sans risque »',
                    'Corriger sans humilier celui qui a partagé',
                ],
                'lecons' => [
                    [
                        'titre' => 'La forme trahit avant le fond',
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>Vous n'avez pas besoin de connaissances médicales pour repérer une fausse promesse thérapeutique. Sa <em>forme</em> suffit, et elle est remarquablement stable.</p>

                        <h3>Les marqueurs</h3>

                        <p><strong>Le remède unique et universel.</strong> « Guérit le diabète, le cancer et l'hypertension. » La médecine réelle ne fonctionne pas ainsi : des maladies différentes ont des mécanismes différents.</p>

                        <p><strong>La guérison rapide et totale.</strong> « En sept jours, définitivement. » Les affections chroniques se gèrent, elles ne se suppriment pas en une semaine.</p>

                        <p><strong>Le complot commercial.</strong> « Les laboratoires ne veulent pas que vous le sachiez. » Cet argument est là pour désarmer la vérification : si toute source officielle est suspecte, plus rien ne peut contredire l'affirmation. C'est sa fonction, pas un contenu.</p>

                        <p><strong>Le témoignage à la place de l'étude.</strong> « Ma tante l'a fait, elle est guérie. » Un cas n'établit rien : ni ce qu'elle avait exactement, ni ce qui l'a soignée, ni combien de personnes ont essayé sans résultat.</p>

                        <p><strong>L'absence de dose et de contre-indication.</strong> Tout vrai traitement en a. Leur absence signale que personne n'a étudié la question.</p>

                        <h3>Pourquoi la forme suffit</h3>

                        <p>Ces marqueurs ne prouvent pas que l'affirmation est fausse. Ils prouvent qu'elle <strong>n'est pas étayée</strong> — et sur un sujet où l'erreur se paie en santé, c'est la même conclusion pratique : ne pas relayer.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Remonter à une source qui vaut quelque chose',
                        'minutes' => 12,
                        'contenu' => <<<'HTML'
                        <p>« Selon une étude » est la formule la plus employée par la désinformation médicale, précisément parce qu'elle rassure sans rien dire.</p>

                        <h3>Les trois questions</h3>

                        <p><strong>Quelle étude, exactement ?</strong> Un titre, une revue, une année, des auteurs. Sans ces quatre éléments, il n'y a pas d'étude — il y a une invocation.</p>

                        <p><strong>Sur qui, et combien ?</strong> Une étude sur douze souris ne dit rien de ce qui se passera chez un humain. Ce n'est pas un détail technique : la majorité des « percées » relayées sont des résultats sur cellules ou sur animaux, présentés comme des traitements disponibles.</p>

                        <p><strong>Que disent les autres ?</strong> Un résultat isolé n'est pas un savoir. Le savoir naît de la convergence. Une étude unique contre le consensus est une piste, jamais une conclusion.</p>

                        <h3>Où chercher, dans l'ordre</h3>

                        <ol>
                        <li>Le ministère de la Santé de votre pays — c'est lui qui décide de ce qui est autorisé <em>ici</em>.</li>
                        <li>L'Organisation mondiale de la santé, pour les recommandations générales.</li>
                        <li>Un professionnel de santé joignable, pour votre cas personnel.</li>
                        </ol>

                        <p>Cet ordre n'est pas une hiérarchie de prestige : c'est une hiérarchie de <strong>pertinence pour vous</strong>. Une recommandation internationale ne dit pas ce qui est disponible et autorisé dans votre pharmacie.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Corriger sans humilier',
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>Un fact-check qui vexe ne convainc personne. Sur la santé, il fait pire : il pousse la personne à défendre publiquement ce qu'elle a partagé, et donc à s'y attacher davantage.</p>

                        <h3>Ce qui ne marche pas</h3>

                        <ul>
                        <li>Corriger en public quand un message privé suffit.</li>
                        <li>Commencer par « c'est faux ».</li>
                        <li>Se moquer de la source, ou de celui qui l'a crue.</li>
                        <li>Envoyer un lien sans un mot d'explication.</li>
                        </ul>

                        <p>Ces quatre gestes ont un point commun : ils font de la correction une <em>confrontation</em>. Or la personne qui a partagé voulait presque toujours aider quelqu'un.</p>

                        <h3>Ce qui marche</h3>

                        <p><strong>Commencez par l'intention.</strong> « Je vois que tu voulais aider ta cousine » n'est pas une politesse : c'est vrai, et le dire retire l'enjeu d'amour-propre.</p>

                        <p><strong>Montrez votre chemin.</strong> « J'ai cherché cette étude et je ne l'ai trouvée nulle part » se discute. « C'est faux » ne se discute pas — cela s'accepte ou se refuse.</p>

                        <p><strong>Donnez la suite utile.</strong> Quelqu'un cherche une solution à un problème réel. Un démenti sans alternative laisse le problème entier, et la rumeur reviendra le combler.</p>

                        <p><strong>Acceptez le désaccord.</strong> Vous n'avez pas besoin qu'on vous donne raison. Il suffit que le message cesse d'être relayé — et cela arrive souvent sans que personne ne l'admette.</p>
                        HTML,
                    ],
                ],
            ],

            [
                'slug' => 'sourcer-et-citer',
                'titre' => 'Sourcer et citer',
                'categorie' => 'methode',
                'niveau' => NiveauCours::Avance,
                'duree' => '55 min',
                'cout' => 0,
                'couverture' => 'assets/images/courses/placeholder-b.svg',
                'description' => 'La compétence qui sous-tend toutes les autres : établir la '
                    ."traçabilité d'une information, et l'écrire de façon que quelqu'un d'autre "
                    .'puisse la refaire.',
                'acquis' => [
                    'Distinguer source primaire, secondaire et relais',
                    "Remonter une chaîne de citations jusqu'à son origine",
                    'Archiver une page avant qu’elle disparaisse',
                    'Rédiger une note de sources reproductible',
                ],
                'lecons' => [
                    [
                        'titre' => 'Primaire, secondaire, relais',
                        'minutes' => 12,
                        'contenu' => <<<'HTML'
                        <p>Toute information a une origine. La retrouver est l'essentiel du travail ; le reste en découle.</p>

                        <h3>Les trois niveaux</h3>

                        <p><strong>La source primaire</strong> produit l'information : le communiqué de l'institution, le rapport lui-même, la personne qui a parlé, le document original.</p>

                        <p><strong>La source secondaire</strong> rapporte et interprète : l'article de presse qui rend compte du rapport.</p>

                        <p><strong>Le relais</strong> transmet sans rien vérifier : le message WhatsApp qui reprend le titre de l'article, souvent en le durcissant.</p>

                        <h3>Ce qui se perd à chaque étage</h3>

                        <p>La déformation n'est presque jamais un mensonge. C'est une <em>perte de nuance</em>, et elle s'accumule :</p>

                        <ul>
                        <li>Le rapport écrit « pourrait, dans certaines conditions, atteindre 30 % ».</li>
                        <li>L'article titre « jusqu'à 30 % ».</li>
                        <li>Le message WhatsApp annonce « 30 % ».</li>
                        </ul>

                        <p>Personne n'a menti. L'affirmation finale est pourtant fausse — elle a changé de nature en perdant ses conditions.</p>

                        <p>C'est pourquoi la règle est : <strong>on ne vérifie jamais un relais, on remonte à la source primaire.</strong> Vérifier le relais revient à vérifier une copie de copie.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Remonter une chaîne de citations',
                        'minutes' => 12,
                        'contenu' => <<<'HTML'
                        <p>Remonter une chaîne, c'est demander à chaque étage : « d'où tenez-vous cela ? » — jusqu'à ce que la question n'ait plus de réponse.</p>

                        <h3>La méthode</h3>

                        <ol>
                        <li><strong>Notez l'affirmation exacte</strong>, avec ses chiffres et ses qualificatifs. C'est votre point de comparaison ; sans lui, vous ne verrez pas la déformation.</li>
                        <li><strong>Cherchez la formulation la plus ancienne.</strong> Une expression identique reprise partout signale un point d'origine unique.</li>
                        <li><strong>À chaque étage, cherchez le lien sortant.</strong> Un article sérieux cite sa source. Un article qui n'en cite aucune est lui-même un relais.</li>
                        <li><strong>Arrêtez-vous quand vous tenez le document.</strong> Et alors seulement, lisez ce qu'il dit — pas ce qu'on en rapporte.</li>
                        </ol>

                        <h3>Les deux fins de parcours</h3>

                        <p><strong>La chaîne aboutit.</strong> Vous avez le document. Comparez avec l'affirmation de départ : la déformation, si elle existe, saute aux yeux.</p>

                        <p><strong>La chaîne tourne en rond.</strong> A cite B, qui cite C, qui cite A. C'est le signe caractéristique d'une information sans origine — et c'est, en soi, un résultat publiable : « aucune source primaire n'a pu être identifiée » est une conclusion, pas un aveu d'impuissance.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Archiver avant que ça disparaisse',
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>Une page citée dans un fact-check disparaît régulièrement — suppression, modification discrète, site fermé. Sans archive, votre travail devient invérifiable, et c'est votre crédit qui en souffre.</p>

                        <h3>Trois niveaux, du plus solide au plus faible</h3>

                        <p><strong>L'archive publique.</strong> Un service d'archivage web enregistre la page et lui donne une adresse permanente que <em>n'importe qui</em> peut consulter. C'est le seul niveau qui rend votre vérification reproductible par autrui.</p>

                        <p><strong>Le PDF de la page.</strong> Conserve la mise en page et la date d'impression. Utile, mais c'est <em>votre</em> copie : quelqu'un qui doute de vous a une raison de plus de douter.</p>

                        <p><strong>La capture d'écran.</strong> Le minimum. Se falsifie en trois minutes, et tout le monde le sait. Elle documente, elle ne prouve pas.</p>

                        <h3>La règle</h3>

                        <p><strong>Archivez AVANT de publier votre vérification</strong>, jamais après. Une page qui contredit un fact-check attendu disparaît parfois dans l'heure qui suit sa parution — et l'archive faite après ne montre plus rien.</p>

                        <p>Notez toujours la date et l'heure de consultation à côté du lien. C'est ce qui permet de dire « la page affichait ceci le 12 mars à 14 h », même si elle affiche autre chose aujourd'hui.</p>
                        HTML,
                    ],
                    [
                        'titre' => 'Écrire une note de sources',
                        'minutes' => 10,
                        'contenu' => <<<'HTML'
                        <p>Une note de sources n'est pas une bibliographie décorative. C'est le mode d'emploi qui permet à quelqu'un d'autre de refaire votre vérification et d'arriver au même endroit.</p>

                        <h3>Ce qu'elle contient</h3>

                        <ul>
                        <li><strong>L'affirmation examinée</strong>, citée exactement, avec l'endroit et la date où vous l'avez rencontrée.</li>
                        <li><strong>Chaque source consultée</strong> : titre, auteur ou institution, date de publication, adresse, adresse d'archive, date de consultation.</li>
                        <li><strong>Ce que chaque source établit</strong> — en une phrase. Une liste de liens sans cette phrase oblige le lecteur à refaire tout le travail.</li>
                        <li><strong>Ce qui n'a PAS pu être établi.</strong> La partie la plus souvent omise, et la plus utile : elle dit où s'arrête votre certitude.</li>
                        </ul>

                        <h3>Le test qui la valide</h3>

                        <p>Donnez votre note à quelqu'un qui ne connaît pas le dossier. S'il arrive à la même conclusion sans vous poser de question, elle est bonne.</p>

                        <p>S'il doit vous demander quoi que ce soit, ce qui manque est exactement ce que vous avez tenu pour évident — et c'est presque toujours là que se logent les erreurs.</p>

                        <h3>Pourquoi cela compte plus qu'ailleurs</h3>

                        <p>Un fact-check demande qu'on lui fasse confiance sur un sujet où la confiance est justement l'enjeu. La note de sources est ce qui remplace « croyez-moi » par « vérifiez vous-même ».</p>

                        <p>C'est la différence entre une plateforme de vérification et une nouvelle autorité qu'il faudrait croire sur parole.</p>
                        HTML,
                    ],
                ],
            ],
        ];
    }
}
