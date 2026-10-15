<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Les quatre annonces réelles de la plateforme, avec leurs photographies.
 *
 * ## Ce qui distingue ce seeder de `BlogSeeder`
 *
 * `BlogSeeder` posait deux annonces en deux paragraphes chacune. Celui-ci
 * publie les quatre événements réels, texte complet et images dans le corps.
 *
 * ## D'où viennent les faits
 *
 * De trois sources, et d'aucune autre :
 *
 * - **Le prototype** (`blog-details.html`) pour le Prix WSIS. Il porte
 *   l'article rédigé, ses citations et ses chiffres. On le transpose.
 * - **Les affiches officielles**, pour les dates, les heures et les lieux des
 *   deux séances de lancement.
 * - **La diapositive de remise du prix**, pour le concours d'Aix-Marseille :
 *   rang, montant, noms des porteurs, financeur.
 *
 * Rien n'est complété « dans le même esprit ». Là où une donnée manque — la
 * date du concours ODD, par exemple — l'article ne la mentionne pas, plutôt que
 * d'avancer une date plausible. Sur un produit dont le métier est de distinguer
 * ce qui est établi de ce qui ne l'est pas, s'appliquer la règle à soi-même
 * n'est pas une coquetterie.
 *
 * ## Les images
 *
 * Chaque `<img>` porte un `alt` qui DÉCRIT la photographie — pas qui répète le
 * titre — et un `title` qui donne le contexte au survol. Les clichés du
 * concours sont crédités « © Eléa Ropiot — Aix-Marseille Université » dans leur
 * légende : ce sont les photographies de quelqu'un.
 */
final class ActualitesVerifallSeeder extends Seeder
{
    public function run(): void
    {
        $rubriques = $this->rubriques();
        $auteur = $this->auteur();

        foreach ($this->annonces() as $donnees) {
            $article = BlogPost::firstOrNew(['slug' => $donnees['slug']]);

            $article->forceFill([
                'uuid' => $article->uuid ?? (string) Str::uuid(),
                'blog_category_id' => $rubriques[$donnees['rubrique']]->id,
                'author_id' => $auteur?->id,
                'title' => $donnees['titre'],
                'excerpt' => $donnees['chapo'],
                'body' => $donnees['corps'],
                'cover_path' => $donnees['image'],
                'meta_title' => $donnees['meta_titre'],
                'meta_description' => $donnees['meta_description'],
                'is_featured' => $donnees['mise_en_avant'],
                'is_published' => true,
                'published_at' => $donnees['publie_le'],
            ])->save();
        }

        $this->command?->info(sprintf(
            '  %d annonces Vérif\'All publiées, images comprises.',
            count($this->annonces()),
        ));
    }

    /**
     * @return list<array{slug: string, rubrique: string, titre: string, chapo: string, corps: string, image: string, meta_titre: string, meta_description: string, mise_en_avant: bool, publie_le: Carbon}>
     */
    private function annonces(): array
    {
        return [
            [
                'slug' => 'verifall-remporte-le-prix-wsis-2026',
                'rubrique' => 'Partenariats',
                'titre' => "Vérif'All remporte le Prix WSIS 2026",
                'chapo' => 'La plateforme béninoise de vérification des faits a remporté le Prix WSIS 2026 '
                    ."dans la catégorie « Media », décerné par l'Union internationale des télécommunications "
                    .'à Genève, parmi 1 595 projets issus de 122 pays.',
                'meta_titre' => "Vérif'All remporte le Prix WSIS 2026, catégorie « Media »",
                'meta_description' => "Le 9 juillet 2026 à Genève, l'UIT décerne à Vérif'All le WSIS Prize 2026 "
                    .'dans la catégorie « Media », parmi 1 595 projets de 122 pays.',
                'image' => 'assets/images/blog/verifall-prix-wsis-2026-annonce.jpg',
                'mise_en_avant' => true,
                'publie_le' => Carbon::create(2026, 7, 9, 12),
                'corps' => $this->corpsWsis(),
            ],
            [
                'slug' => 'deuxieme-prix-concours-objectif-developpement-durable',
                'rubrique' => 'Partenariats',
                'titre' => '2ᵉ prix du Concours « Objectif : Développement Durable ! »',
                'chapo' => "Face à des projets engagés venus de toute l'Académie, Vérif'All a décroché le "
                    ."2ᵉ prix du concours d'Aix-Marseille Université. Une reconnaissance qui salue un projet "
                    ."où l'esprit critique devient un levier de développement durable.",
                'meta_titre' => "Vérif'All, 2ᵉ prix du concours « Objectif : Développement Durable ! »",
                'meta_description' => "Aix-Marseille Université distingue Vérif'All d'un 2ᵉ prix de 800 €, "
                    .'porté par Dagbégnon René Fadonougbo et Darline Cadet (IMPGT).',
                'image' => 'assets/images/blog/2eme-prix-concours-objectif-developpement-durable.jpg',
                'mise_en_avant' => false,
                /*
                 | La date du concours n'est établie par aucune de nos sources.
                 | Celle-ci est la date de PUBLICATION de l'annonce sur notre
                 | blog, ce qui est notre choix éditorial — et l'article, lui,
                 | n'avance aucune date d'événement.
                 */
                'publie_le' => Carbon::create(2026, 6, 20, 12),
                'corps' => $this->corpsObjectifDeveloppementDurable(),
            ],
            [
                'slug' => 'lancement-officiel-verifall-cdij-cotonou',
                'rubrique' => 'Partenariats',
                'titre' => "Lancement officiel de Vérif'All au CDIJ de Cotonou",
                'chapo' => "Le lancement officiel de la plateforme de vérification des faits Vérif'All a eu "
                    ."lieu le jeudi 16 juillet 2026 à 10h00 au Centre de Documentation et d'Information "
                    ."Juridique (CDIJ) de Cotonou, dans l'enceinte de la Cour d'Appel.",
                'meta_titre' => "Lancement officiel de Vérif'All — 16 juillet 2026, CDIJ Cotonou",
                'meta_description' => "Vérif'All a été lancée officiellement le jeudi 16 juillet 2026 à 10h00 "
                    ."au Centre de Documentation et d'Information Juridique de Cotonou.",
                'image' => 'assets/images/blog/lancement-officiel-cdij-cotonou.jpg',
                'mise_en_avant' => false,
                'publie_le' => Carbon::create(2026, 7, 16, 10),
                'corps' => $this->corpsLancement(),
            ],
            [
                'slug' => 'presentation-officielle-verifall-porto-novo',
                'rubrique' => 'Formation',
                'titre' => "Présentation officielle de Vérif'All à Porto-Novo",
                'chapo' => "Après Cotonou, Vérif'All s'est présentée à Porto-Novo. La séance s'est tenue le "
                    .'18 juillet 2026 à 09h00 au CEMAAC, et associait présentation officielle et formation '
                    .'pratique au fact-checking.',
                'meta_titre' => "Vérif'All à Porto-Novo — présentation et formation, 18 juillet 2026",
                'meta_description' => "Présentation officielle de Vérif'All et formation pratique au "
                    .'fact-checking, le 18 juillet 2026 à 09h00 au CEMAAC de Porto-Novo.',
                'image' => 'assets/images/blog/presentation-officielle-cemaac-porto-novo.jpg',
                'mise_en_avant' => false,
                'publie_le' => Carbon::create(2026, 7, 18, 9),
                'corps' => $this->corpsPortoNovo(),
            ],
        ];
    }

    private function corpsWsis(): string
    {
        return <<<'HTML'
        <p>À l'heure où les fausses informations circulent plus vite que les faits, le Bénin apporte une réponse technologique ambitieuse. Avec Vérif'All, une application de fact-checking développée par René Fadonougbo et son équipe, chacun peut vérifier un texte, une image, une vidéo ou un audio avant de le partager. Récompensée par l'ONU en 2026, cette solution illustre le potentiel de l'innovation africaine face à un défi mondial : restaurer la confiance dans l'information.</p>

        <p>Une rumeur sur WhatsApp, une image sortie de son contexte, une vidéo manipulée par l'intelligence artificielle ou une fausse offre d'emploi publiée sur les réseaux sociaux peuvent désormais toucher des milliers de personnes en quelques minutes. En Afrique, où les plateformes sociales constituent une source majeure d'information, la désinformation influence les comportements, alimente les tensions, favorise les escroqueries et affaiblit la confiance envers les institutions.</p>

        <p>Face à cette réalité, le Béninois René Fadonougbo refuse de considérer la désinformation comme une fatalité. Son objectif est simple : donner à chaque citoyen les moyens de vérifier avant de croire et de partager. De cette ambition est née Vérif'All, une plateforme numérique pensée pour démocratiser le fact-checking et développer l'esprit critique.</p>

        <h2>Une « boîte à outils » pour vérifier l'information en quelques clics</h2>

        <p>Lancée officiellement le jeudi 16 juillet 2026 au Centre de Documentation et d'Information Juridique (CDIJ), dans l'enceinte de la Cour d'Appel de Cotonou, Vérif'All se présente comme une plateforme tout-en-un de fact-checking dédiée à l'Afrique de l'Ouest francophone. Son originalité réside dans la centralisation, au sein d'une interface unique, de nombreux outils de vérification habituellement dispersés sur Internet.</p>

        <p>L'utilisateur peut analyser un texte, une photographie, une vidéo, un fichier audio ou encore un lien web. La plateforme guide ensuite la recherche à travers des sources fiables, des outils spécialisés et des méthodes reconnues de vérification. Elle propose également un répertoire de plus d'une centaine d'outils de fact-checking, un module pédagogique d'éducation aux médias ainsi qu'un espace communautaire destiné à encourager les bonnes pratiques numériques.</p>

        <p>Conçue pour fonctionner même avec une connexion 3G, l'application répond aux contraintes techniques de nombreux utilisateurs africains. Son approche est volontairement pédagogique : il ne s'agit pas seulement de dire si une information est vraie ou fausse, mais d'expliquer la démarche de vérification afin que chacun acquière progressivement les bons réflexes.</p>

        <h2>Une reconnaissance mondiale pour une solution conçue au Bénin</h2>

        <figure><img src="/assets/images/blog/verifall-prix-wsis-2026-remise.jpg" alt="Cérémonie de remise du Prix WSIS 2026 sur la scène du forum, devant les drapeaux de l'UIT, de la Suisse et des Nations unies" title="Cérémonie de remise du Prix WSIS 2026 — Genève" width="1600" height="1200" loading="lazy"><figcaption>Cérémonie de remise du Prix WSIS 2026, décerné par l'UIT à Genève.</figcaption></figure>

        <p>Quelques jours avant son lancement officiel, Vérif'All reçoit une consécration internationale. Le 9 juillet 2026, lors du World Summit on the Information Society (WSIS) Forum organisé à Genève par l'Union internationale des télécommunications (UIT), agence spécialisée des Nations unies, la plateforme remporte le WSIS Prize 2026 dans la catégorie « Media ». Sélectionnée parmi 1 595 projets issus de 122 pays, elle figure parmi les 18 initiatives récompensées à l'échelle mondiale.</p>

        <h3>Détails de la distinction</h3>

        <ul>
        <li><strong>Date</strong> — 9 juillet 2026</li>
        <li><strong>Lieu</strong> — Genève (Suisse), Forum du Sommet mondial sur la société de l'information (SMSI / WSIS)</li>
        <li><strong>Organisateur</strong> — Union internationale des télécommunications (UIT), agence spécialisée des Nations unies</li>
        <li><strong>Compétition</strong> — sélectionnée parmi 1 595 projets provenant de 122 pays</li>
        </ul>

        <p>Les premiers retours des utilisateurs illustrent son utilité. Lors d'une présentation publique à Porto-Novo, Romaric Tomavo, conseiller agricole, salue le projet :</p>

        <blockquote><p>Un projet qui aide à rester au cœur de la réalité et à éviter les fausses informations.</p><cite>Romaric Tomavo, conseiller agricole</cite></blockquote>

        <p>De son côté, Peace Djevoessoun, technicien aquacole et membre d'AIESEC Porto-Novo, estime que la plateforme permet de :</p>

        <blockquote><p>Vérifier plusieurs informations et de mieux se protéger contre les conséquences liées aux fausses informations.</p><cite>Peace Djevoessoun, technicien aquacole, AIESEC Porto-Novo</cite></blockquote>

        <p>Au-delà des particuliers, Vérif'All intéresse aussi les journalistes, les étudiants, les enseignants, les organisations de la société civile et les institutions souhaitant renforcer leurs pratiques de vérification.</p>

        <h2>Un modèle prometteur, mais des défis à relever</h2>

        <p>L'approche développée par Vérif'All présente un fort potentiel de réplication dans d'autres pays africains confrontés aux mêmes enjeux informationnels. Son modèle numérique, son caractère multilingue en développement et sa vocation éducative facilitent son adaptation à différents contextes.</p>

        <p>Le projet ne se limite d'ailleurs pas à la technologie. Son équipe organise des formations, des ateliers d'éducation aux médias et des campagnes de sensibilisation afin d'accompagner les utilisateurs dans l'apprentissage du fact-checking.</p>

        <p>Comme toute solution numérique, Vérif'All devra néanmoins relever plusieurs défis : maintenir l'actualisation permanente de ses outils, suivre l'évolution rapide des contenus générés par l'intelligence artificielle, renforcer la confiance des utilisateurs et assurer un modèle économique durable pour soutenir son développement.</p>

        <p>Malgré les défis liés au changement d'échelle, Vérif'All démontre qu'il est possible de concevoir, depuis le Bénin, une réponse innovante et crédible à un enjeu mondial. En mettant la technologie au service de l'esprit critique, René Fadonougbo et son équipe rappellent que la lutte contre la désinformation ne repose pas uniquement sur les plateformes numériques ou les médias : elle commence aussi entre les mains de chaque citoyen.</p>
        HTML;
    }

    private function corpsObjectifDeveloppementDurable(): string
    {
        return <<<'HTML'
        <p>Face à des projets engagés venus de toute l'Académie, Vérif'All a décroché le 2<sup>e</sup> prix du concours « Objectif : Développement Durable ! » d'Aix-Marseille Université. Une reconnaissance qui salue un projet où l'esprit critique devient un levier de développement durable.</p>

        <h2>Un projet porté par deux étudiants de l'IMPGT</h2>

        <p>Le projet a été présenté par <strong>Dagbégnon René Fadonougbo</strong> et <strong>Darline Cadet</strong>, de l'Institut de Management Public et Gouvernance Territoriale (IMPGT) d'Aix-Marseille Université, sous l'intitulé « Vérif'All — la boussole contre la désinformation numérique ».</p>

        <p>Le dossier retenu par le jury décrit une application mobile et web qui centralise des outils de vérification de contenus pour lutter contre la désinformation, en offrant des résultats rapides et un module pédagogique destiné aux jeunes, avec le soutien de la Fondation de France.</p>

        <figure><img src="/assets/images/blog/2eme-prix-concours-objectif-developpement-durable-1.jpg" alt="Deux membres de l'équipe présentent le catalogue de formation de Vérif'All sur un grand écran, devant un public assis" title="Démonstration de la plateforme Vérif'All" width="1600" height="1067" loading="lazy"><figcaption>Démonstration du catalogue de formation. <small>© Eléa Ropiot — Aix-Marseille Université</small></figcaption></figure>

        <h3>Détails de la distinction</h3>

        <ul>
        <li><strong>Concours</strong> — « Objectif : Développement Durable ! », catégorie « Projets ODD en action »</li>
        <li><strong>Organisateur</strong> — Aix-Marseille Université</li>
        <li><strong>Rang</strong> — 2<sup>e</sup> prix, doté de 800 €</li>
        <li><strong>Financement du prix</strong> — Fonds de dotation Demain</li>
        </ul>

        <h2>Pourquoi le fact-checking relève du développement durable</h2>

        <p>Le lien peut sembler indirect ; il ne l'est pas. Une information fausse sur un traitement médical, sur une offre d'emploi ou sur une échéance administrative produit des décisions coûteuses, prises par des gens qui n'avaient pas les moyens de vérifier. Outiller ces décisions, c'est agir sur la qualité des institutions et sur l'éducation.</p>
        HTML;
    }

    private function corpsLancement(): string
    {
        return <<<'HTML'
        <p>Le lancement officiel de la plateforme de vérification des faits Vérif'All a eu lieu le jeudi 16 juillet 2026 à 10h00 au Centre de Documentation et d'Information Juridique (CDIJ) de Cotonou, dans l'enceinte de la Cour d'Appel.</p>

        <h2>Le lieu et la date</h2>

        <ul>
        <li><strong>Date</strong> — jeudi 16 juillet 2026, à 10h00</li>
        <li><strong>Lieu</strong> — Centre de Documentation et d'Information Juridique (CDIJ), enceinte de la Cour d'Appel de Cotonou</li>
        <li><strong>Renseignements</strong> — +229 01 96 73 04 56</li>
        </ul>

        <h2>Ce que le lancement a présenté</h2>

        <p>La séance a présenté la plateforme telle qu'elle est disponible : la vérification de textes, d'images, de vidéos et de fichiers audio, le répertoire d'outils de fact-checking, le module d'apprentissage et l'espace communautaire.</p>

        <p>Le choix du CDIJ n'est pas anodin. Une plateforme dont l'objet est la fiabilité de l'information avait tout intérêt à se présenter dans un lieu dont le métier est la documentation et l'accès au droit.</p>

        <h2>Les partenaires</h2>

        <p>L'événement a été rendu possible par la Fondation de France, AIESEC, U-Report Porto-Novo, le CEMAAC Bénin, l'Association Mairie des Jeunes du Bénin et le JESPD.</p>
        HTML;
    }

    private function corpsPortoNovo(): string
    {
        return <<<'HTML'
        <p>Après Cotonou, Vérif'All s'est présentée à Porto-Novo. La séance s'est tenue le 18 juillet 2026 à 09h00 au CEMAAC, et associait présentation officielle et formation pratique au fact-checking.</p>

        <h2>Au programme</h2>

        <ul>
        <li>Présentation officielle de la plateforme Vérif'All</li>
        <li>Formation pratique aux techniques de fact-checking</li>
        <li>Sensibilisation aux enjeux de la désinformation numérique</li>
        <li>Échanges avec les participants et les acteurs engagés dans la promotion de l'information fiable</li>
        </ul>

        <h2>À qui s'adressait cette rencontre</h2>

        <p>Aux jeunes, aux journalistes, aux enseignants, aux étudiants, aux acteurs de la société civile, aux communicants, et à toute personne souhaitant renforcer ses compétences en vérification de l'information.</p>

        <h2>Ce que les participants en ont dit</h2>

        <blockquote><p>Un projet qui aide à rester au cœur de la réalité et à éviter les fausses informations.</p><cite>Romaric Tomavo, conseiller agricole</cite></blockquote>

        <blockquote><p>Vérifier plusieurs informations et mieux se protéger contre les conséquences liées aux fausses informations.</p><cite>Peace Djevoessoun, technicien aquacole, AIESEC Porto-Novo</cite></blockquote>
        HTML;
    }

    /**
     * Les rubriques employées ici, créées si elles manquent.
     *
     * @return array<string, BlogCategory>
     */
    private function rubriques(): array
    {
        $rubriques = [];

        foreach (['Analyses', 'Guides', 'Formation', 'Partenariats', 'Outils'] as $rang => $nom) {
            $rubrique = BlogCategory::firstOrNew(['slug' => Str::slug($nom)]);

            $rubrique->forceFill([
                'uuid' => $rubrique->uuid ?? (string) Str::uuid(),
                'name' => $nom,
                'position' => $rang + 1,
            ])->save();

            $rubriques[$nom] = $rubrique;
        }

        return $rubriques;
    }

    private function auteur(): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', UserRole::Admin->value))->first();
    }
}
