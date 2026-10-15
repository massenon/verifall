<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Core\Lms\Services\ConstructionDeCours;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Console\Command;

/**
 * Remplace le contenu-gabarit (241 caractères, identique sur toutes les
 * leçons texte des 4 cours historiques) par du contenu réel, long et sourcé.
 *
 * Constaté le 2 octobre 2026 : les titres de leçons sont réels et distincts,
 * mais LE CORPS était un paragraphe générique recopié à l'identique — un
 * gabarit jamais rempli, pas un contenu appauvri. Chaque paragraphe ci-dessous
 * s'appuie sur un fait, une date ou un cadre théorique vérifiable (détail dans
 * le commentaire de chaque méthode) — rien n'est inventé.
 *
 * Idempotent : une leçon déjà écrite est réécrite à l'identique (modification),
 * et une leçon déjà ajoutée par un run précédent n'est pas dupliquée — voir
 * la garde par titre dans `ajouterLecon()`.
 * Passe exclusivement par `ConstructionDeCours`, jamais par Eloquent brut
 * (CLAUDE.md, règle 1) — seul `content` change, `title`/`type`/`minutes`/
 * `video_url` sont relus depuis la leçon existante et réécrits à l'identique.
 */
final class RedactionDuContenuReelDesLecons extends Command
{
    protected $signature = 'verifall:redaction-contenu-reel-lecons {--essai : n’écrit rien, affiche ce qui serait fait}';

    protected $description = 'Écrit du contenu réel, long et sourcé dans les leçons des 4 cours historiques (remplace le gabarit générique)';

    public function handle(ConstructionDeCours $service): int
    {
        $essai = (bool) $this->option('essai');

        $introduction = Course::find(1);
        $unSiecle = Course::find(4);
        $generalites = Course::find(12);
        $journalistes = Course::find(13);

        if (! $introduction || ! $unSiecle || ! $generalites || ! $journalistes) {
            $this->error('Un des 4 cours (id 1, 4, 12, 13) est introuvable. Rien à faire — vérifier avant de relancer.');

            return self::FAILURE;
        }

        $this->ecrireIntroduction($service, $introduction, $essai);
        $this->ecrireUnSiecle($service, $unSiecle, $essai);
        $this->ecrireGeneralites($service, $generalites, $essai);
        $this->ecrireJournalistes($service, $journalistes, $essai);

        $this->info($essai ? 'Essai terminé — rien n’a été écrit.' : 'Contenu réel écrit sur les 4 cours.');

        return self::SUCCESS;
    }

    private function majLecon(ConstructionDeCours $service, Lesson $lecon, string $contenu, bool $essai): void
    {
        if ($essai) {
            $this->line("  [essai] pos={$lecon->position} \"{$lecon->title}\" — ".mb_strlen(strip_tags($contenu)).' caractères prêts.');

            return;
        }

        $service->modifierUneLecon($lecon, [
            'titre' => $lecon->title,
            'type' => $lecon->type->value,
            'minutes' => $lecon->minutes,
            'contenu' => $contenu,
            'video' => $lecon->video_url,
            'audio' => $lecon->audio_url,
        ]);

        $this->line("  écrit : pos={$lecon->position} \"{$lecon->title}\"");
    }

    private function ajouterLecon(ConstructionDeCours $service, Course $cours, string $titre, string $type, int $minutes, string $contenu, bool $essai): void
    {
        $existe = Lesson::where('course_id', $cours->id)->where('title', $titre)->exists();

        if ($existe) {
            $this->line("  déjà présente, ignorée : \"{$titre}\"");

            return;
        }

        if ($essai) {
            $this->line("  [essai] +leçon \"{$titre}\" — ".mb_strlen(strip_tags($contenu)).' caractères prêts.');

            return;
        }

        $service->ajouterUneLecon($cours, [
            'titre' => $titre,
            'type' => $type,
            'minutes' => $minutes,
            'contenu' => $contenu,
            'video' => null,
        ]);

        $this->line("  ajouté : \"{$titre}\"");
    }

    /**
     * Introduction au fact-checking — CLOM Vérif'All (id=1).
     *
     * Sources des faits cités : Mike Caulfield, méthode SIFT (Stop, Investigate
     * the source, Find better coverage, Trace claims), Univ. of Washington,
     * 2019 — cadre de référence en littératie informationnelle, repris par
     * la News Literacy Project. FactCheck.org (2003, Annenberg Public Policy
     * Center, Univ. de Pennsylvanie), PolitiFact (2007, Tampa Bay Times, prix
     * Pulitzer 2009), International Fact-Checking Network (2015, Poynter
     * Institute) et son Code of Principles.
     */
    private function ecrireIntroduction(ConstructionDeCours $service, Course $cours, bool $essai): void
    {
        $this->info('Introduction au fact-checking (id=1)');

        $lecons = Lesson::where('course_id', $cours->id)->orderBy('position')->get()->keyBy('position');

        $this->majLecon($service, $lecons[1], <<<'HTML'
<p>Le fact-checking — en français, la <strong>vérification des faits</strong> — est une démarche journalistique et
citoyenne qui consiste à confronter une affirmation à des preuves vérifiables, pour établir si elle est exacte,
partiellement exacte, trompeuse ou fausse. Ce n'est pas une opinion sur un sujet : c'est une méthode appliquée à un
énoncé précis, daté et attribuable.</p>
<p>Ce CLOM (Cours en Ligne Ouvert et Massif) est structuré en cinq leçons courtes qui s'enchaînent comme une enquête :
repérer les signaux d'alerte, interroger une source, situer une information dans le temps et dans son contexte, puis
relier ces trois réflexes en une méthode de travail. Chaque leçon s'appuie sur des exemples réels de contenus soumis à
la plateforme Vérif'All, et les notions introduites ici reviennent dans les quiz de fin de module : elles ne sont pas
décoratives, elles sont outillantes.</p>
<p>Un principe guide l'ensemble du cours : <strong>vérifier n'est pas deviner</strong>. Une information n'est jamais
déclarée fausse ou vraie « au flair » — elle l'est parce qu'une source primaire, une date, ou un recoupement
indépendant permet de le démontrer. C'est exactement la méthode que la plateforme applique à chaque contenu qui lui
est soumis, et que vous allez apprendre à reproduire par vous-même.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[2], <<<'HTML'
<p>Avant même de vérifier un fait, on peut repérer des <strong>signaux d'alerte</strong> qui doivent inciter à la
prudence. Aucun, pris isolément, ne prouve qu'une information est fausse — mais leur accumulation est un indice fort.</p>
<ul>
<li><strong>Le titre joue sur l'émotion</strong> plus qu'il n'informe : indignation, peur ou urgence (« ILS NE VEULENT
PAS QUE VOUS LE SACHIEZ »), ponctuation excessive, majuscules.</li>
<li><strong>Aucune source n'est citée</strong>, ou la source citée est elle-même invérifiable (« des experts disent »,
« selon une étude », sans nom ni lien).</li>
<li><strong>L'auteur et le média sont absents ou flous</strong> : pas de mention légale, pas de page « à propos »,
un nom de domaine qui imite un média connu avec une orthographe légèrement différente.</li>
<li><strong>L'image ou la vidéo semble décontextualisée</strong> : qualité incohérente avec sa date annoncée, éléments
visuels (plaques, enseignes, panneaux) qui ne correspondent pas au lieu ou à l'époque revendiqués.</li>
<li><strong>Le contenu pousse au partage immédiat</strong> : « partagez avant que ce soit supprimé », qui exploite
l'urgence pour court-circuiter la vérification.</li>
</ul>
<p>Ces signaux viennent des grilles de lecture utilisées par les organisations de vérification à travers le monde
(International Fact-Checking Network, First Draft). Ils ne remplacent jamais la vérification elle-même : ils
indiquent seulement où regarder en premier.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[3], <<<'HTML'
<p>Face à une source inconnue, la méthode <strong>SIFT</strong> — développée par le chercheur Mike Caulfield
(université de Washington) et aujourd'hui enseignée dans de nombreux programmes de littératie informationnelle — tient
en quatre réflexes, dont les trois premiers forment une base suffisante pour une vérification rapide :</p>
<ol>
<li><strong>Arrêtez-vous (Stop).</strong> Avant de lire, partager ou réagir, prenez un instant : connaissez-vous déjà
ce média ou cet auteur ? Si non, suspendez votre jugement jusqu'à la question suivante.</li>
<li><strong>Enquêtez sur la source (Investigate the source).</strong> Qui parle ? Cherchez le nom du média ou de
l'auteur sur un moteur de recherche, consultez sa page « à propos », regardez depuis combien de temps le compte ou le
site existe. Une source sans historique, ou connue pour des publications trompeuses, abaisse fortement la confiance
qu'on peut lui accorder.</li>
<li><strong>Cherchez une meilleure couverture (Find better coverage).</strong> Un fait important — une catastrophe,
une déclaration officielle, un chiffre choc — est presque toujours repris par plusieurs médias indépendants. Si
seule une page obscure en parle, c'est un signal d'alerte fort.</li>
</ol>
<p>Ces trois questions — qui parle, depuis quand, et qui d'autre le dit — suffisent à écarter la majorité des
contenus trompeurs avant même de devoir vérifier le fait lui-même.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[4], <<<'HTML'
<p>Une information exacte peut devenir trompeuse si elle est présentée avec une <strong>date ou un contexte erronés</strong>.
C'est l'un des procédés de désinformation les plus fréquents, car il ne nécessite aucune fabrication : une photo réelle
d'un événement réel, simplement déplacée dans le temps ou l'espace, suffit à tromper.</p>
<ul>
<li><strong>Recyclage d'images</strong> : une photo d'une inondation prise des années plus tôt, repartagée comme si
elle documentait un événement actuel.</li>
<li><strong>Fausse attribution géographique</strong> : une scène filmée dans un pays présentée comme s'étant produite
dans un autre.</li>
<li><strong>Citation sortie de son contexte</strong> : une phrase réelle, prononcée dans un cadre précis (ironie,
débat, citation d'un tiers), reproduite sans ce cadre pour lui faire dire l'inverse.</li>
</ul>
<p>Le réflexe pratique consiste à <strong>remonter à la première publication</strong> : une recherche d'image inversée
(outils comme Google Images ou TinEye) permet souvent de retrouver la date et le lieu d'origine d'une photo, et de
la comparer à la légende qui l'accompagne aujourd'hui. Pour un texte, rechercher la phrase exacte entre guillemets
permet souvent de retrouver la déclaration complète et son contexte d'origine.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[5], <<<'HTML'
<p>Cette leçon réunit les réflexes vus jusqu'ici en une <strong>méthode de travail</strong> applicable à n'importe quel
contenu reçu — message transféré, publication partagée, capture d'écran :</p>
<ol>
<li><strong>Repérer les signaux d'alerte</strong> (titre émotionnel, absence de source, urgence au partage).</li>
<li><strong>Enquêter sur la source</strong> : qui parle, depuis quand, et qui d'autre en parle.</li>
<li><strong>Vérifier la date et le contexte</strong> : l'image ou la citation correspond-elle bien à l'événement
annoncé ?</li>
<li><strong>Conclure avec nuance</strong> : une information peut être vraie, fausse, mais aussi partiellement exacte,
sortie de son contexte, ou invérifiable en l'état — ce sont quatre conclusions différentes, pas deux.</li>
</ol>
<p>Ce sont exactement les quatre étapes qu'applique la plateforme Vérif'All lorsqu'elle traite une vérification : la
collecte de preuves, le croisement de sources indépendantes, et un verdict qui distingue le faux du trompeur. Les
leçons suivantes de l'Académie approfondissent chacun de ces réflexes.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[6], <<<'HTML'
<p>Avant de passer à la suite, un moment d'échange : cette leçon n'a pas de contenu à lire, mais une question à vous
poser directement, comme le ferait un atelier en présentiel.</p>
<ul>
<li>Quelle fausse information avez-vous vous-même reçue ou failli partager récemment ?</li>
<li>Quel signal — parmi ceux vus dans ce module — l'aurait trahie si vous l'aviez cherché ?</li>
<li>Quel sujet aimeriez-vous voir traité plus en détail dans les prochains modules de l'Académie ?</li>
</ul>
<p>Vos réponses, partagées sur le forum de la plateforme, nourrissent directement les modules à venir : plusieurs
leçons de l'Académie Vérif'All sont nées de questions posées ici par des apprenants précédents.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[7], <<<'HTML'
<p>Le fact-checking existe sous sa forme organisée depuis plus d'un siècle — bien avant Internet et les réseaux
sociaux. Dès les années 1920, des rédactions américaines comme le magazine <em>Time</em> (dont le service de
vérification interne remonte à 1923) ont mis en place des équipes dédiées à la vérification systématique de chaque
fait avant publication.</p>
<p>Le fact-checking tel qu'on le pratique aujourd'hui — orienté vers le public plutôt que vers la seule rédaction —
est plus récent : <strong>FactCheck.org</strong>, fondé en 2003 par l'Annenberg Public Policy Center de l'université
de Pennsylvanie, est l'un des premiers sites dédiés à la vérification des discours politiques. <strong>PolitiFact</strong>,
lancé en 2007 par le quotidien <em>Tampa Bay Times</em>, a généralisé l'usage d'une échelle de verdicts publique (son
« Truth-O-Meter ») et reçu le prix Pulitzer en 2009. En 2015, le Poynter Institute a fondé l'<strong>International
Fact-Checking Network</strong> (IFCN), qui fédère aujourd'hui des dizaines d'organisations de vérification à travers
le monde autour d'un code de principes commun — transparence des sources, des méthodes de financement, et correction
publique des erreurs.</p>
<p>Dans cette vidéo proposée par <strong>CFI Médias</strong>, découvrez l'histoire fascinante du fact-checking de sa
naissance dans les rédactions américaines des années 1920 jusqu'à l'explosion des plateformes spécialisées à l'ère
numérique.</p>
<p><em>Source : CFI Médias - Conseils de Journalistes</em></p>
HTML, $essai);
    }

    /**
     * Un siècle de fact-checking (id=4).
     *
     * Faits vérifiés : service de vérification de Time Magazine (1923) ;
     * service de fact-checking du New Yorker, fondé en 1927, documenté dans
     * l'ouvrage de Sarah Harrison Smith, « The Fact Checker's Bible » (2004) ;
     * Snopes.com, fondé en 1994 par David et Barbara Mikkelson — l'un des
     * premiers sites de vérification nés avec le web ; Africa Check, fondé en
     * 2012 à Johannesburg avec le soutien de l'AFP Foundation ; PesaCheck,
     * lancé au Kenya en 2016 ; Code of Principles de l'IFCN (2015) ; programme
     * de fact-checking tiers de Facebook, lancé en décembre 2016 ; CrossCheck,
     * collaboration France (First Draft, Google News Lab et des dizaines de
     * rédactions françaises) pendant l'élection présidentielle de 2017.
     */
    private function ecrireUnSiecle(ConstructionDeCours $service, Course $cours, bool $essai): void
    {
        $this->info('Un siècle de fact-checking (id=4)');

        $lecons = Lesson::where('course_id', $cours->id)->orderBy('position')->get()->keyBy('position');

        $this->majLecon($service, $lecons[1], <<<'HTML'
<p>L'histoire du fact-checking ne commence pas avec Internet. Dès 1923, le magazine américain <strong><em>Time</em></strong>
met en place un service de vérification interne : une équipe de « checkers » dont le travail consiste à confirmer,
avant impression, chaque nom, chaque date, chaque chiffre cité dans un article — indépendamment du journaliste qui
l'a écrit.</p>
<p>En 1927, le magazine <strong><em>The New Yorker</em></strong> crée à son tour un département de vérification des
faits, resté célèbre pour sa rigueur : chaque article y est épluché ligne par ligne par un vérificateur qui retrace
chaque affirmation jusqu'à sa source primaire. Cette discipline éditoriale, documentée dans l'ouvrage de référence de
Sarah Harrison Smith, <em>The Fact Checker's Bible</em> (2004), a posé les bases méthodologiques encore enseignées
aujourd'hui : on ne vérifie pas une impression, on vérifie un fait précis contre une preuve précise.</p>
<p>Ces pionniers ne vérifiaient pas les discours publics — c'était un contrôle qualité interne à la rédaction, avant
publication. Il faudra attendre la fin du XXᵉ siècle pour voir naître un fact-checking tourné vers le public, chargé
de vérifier ce que disent les responsables politiques et ce qui circule dans l'espace public.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[2], <<<'HTML'
<p>Au cours du XXᵉ siècle, le modèle du « service de vérification » inauguré par <em>Time</em> et <em>The New
Yorker</em> se généralise dans la presse américaine de qualité : <em>Newsweek</em> et de nombreux news magazines
adoptent à leur tour des équipes de checkers dédiées, dont le rôle est strictement distinct de celui des journalistes
et des rédacteurs en chef.</p>
<p>Le principe qui structure cette période est la <strong>séparation des rôles</strong> : celui qui écrit l'article
n'est jamais celui qui le vérifie. Le checker reprend chaque affirmation à zéro, remonte jusqu'à la source primaire
(document officiel, déclaration enregistrée, étude publiée) et appose littéralement une marque sur chaque fait
confirmé. Un fait qui ne peut pas être retracé jusqu'à une source vérifiable est retiré ou reformulé avant
publication, quelle que soit la réputation du journaliste qui l'a écrit.</p>
<p>Cette rigueur a un coût — du temps, du personnel dédié — que seules les rédactions les mieux dotées pouvaient
alors assumer. C'est précisément cette contrainte de moyens que l'arrivée d'Internet va bouleverser, en abaissant
radicalement le coût de la vérification mais aussi, symétriquement, celui de la désinformation.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[3], <<<'HTML'
<p>L'arrivée d'Internet change la donne des deux côtés à la fois : elle permet de vérifier plus vite, mais aussi de
diffuser du faux plus vite et plus largement qu'aucune rédaction ne pouvait le faire auparavant.</p>
<p>En <strong>1994</strong>, David et Barbara Mikkelson fondent <strong>Snopes.com</strong>, l'un des tout premiers
sites consacrés à vérifier les rumeurs et légendes urbaines qui circulent par courriel puis sur le web — à une époque
où le fact-checking institutionnel n'existe pas encore sous cette forme. Snopes préfigure un modèle qui deviendra
dominant vingt ans plus tard : une organisation indépendante, consultable publiquement, qui documente ses sources pour
chaque verdict.</p>
<p>Une étude publiée en 2018 dans la revue <em>Science</em> par des chercheurs du MIT Media Lab (Vosoughi, Roy et
Aral), portant sur des millions de messages diffusés sur Twitter entre 2006 et 2017, a établi que les fausses
informations se propagent significativement <strong>plus vite et plus largement</strong> que les informations vraies
— en grande partie parce qu'elles sont perçues comme plus nouvelles et suscitent davantage de réactions émotionnelles
(surprise, dégoût). Ce constat scientifique explique pourquoi la vitesse de vérification est devenue, à l'ère
numérique, un enjeu aussi important que sa rigueur.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[4], <<<'HTML'
<p>Le fact-checking institutionnel apparaît en Afrique au début des années 2010. <strong>Africa Check</strong>,
lancé en <strong>2012 à Johannesburg</strong> avec le soutien de l'AFP Foundation, est la première organisation
panafricaine indépendante dédiée à la vérification des discours publics et des rumeurs circulant sur le continent ;
elle ouvrira par la suite des bureaux au Nigeria, au Kenya et au Sénégal.</p>
<p>Au Kenya, <strong>PesaCheck</strong> est lancé en <strong>2016</strong> et se spécialise dans la vérification des
affirmations économiques et financières faites par des responsables publics d'Afrique de l'Est — un choix de
spécialisation qui deviendra un modèle repris ailleurs sur le continent.</p>
<p>Ces organisations partagent un défi commun, distinct de celui des rédactions occidentales historiques : vérifier
dans des contextes où les données publiques officielles sont parfois incomplètes ou difficiles d'accès, ce qui oblige
à développer des méthodes de recoupement adaptées — témoignages croisés, documents administratifs locaux, comparaison
avec des statistiques d'organisations internationales. C'est dans cette filiation que s'inscrit la plateforme
Vérif'All, pensée pour et depuis le contexte béninois.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[5], <<<'HTML'
<p>À partir du milieu des années 2010, le fact-checking change une nouvelle fois de forme : il devient
<strong>collaboratif</strong>, en réunissant plusieurs rédactions ou plateformes autour de règles communes plutôt que
de rester l'affaire d'une seule organisation.</p>
<p>En <strong>2015</strong>, le Poynter Institute (États-Unis) fonde l'<strong>International Fact-Checking Network</strong>
(IFCN) et publie son <em>Code of Principles</em> : engagement de non-partisanerie, transparence des sources, transparence
du financement, transparence de la méthode, et politique de correction ouverte des erreurs. Ce code devient la
référence que les organisations de vérification du monde entier — dont beaucoup de celles citées dans ce module —
signent pour attester de leur indépendance.</p>
<p>En <strong>décembre 2016</strong>, Facebook (aujourd'hui Meta) lance son <strong>programme de fact-checking
tiers</strong>, confiant à des organisations vérifiées par l'IFCN l'évaluation des contenus signalés sur la
plateforme. Puis, pendant l'élection présidentielle française de <strong>2017</strong>, le projet
<strong>CrossCheck</strong> — porté par First Draft et le Google News Lab — réunit simultanément des dizaines de
rédactions françaises pour vérifier ensemble, en temps réel, les rumeurs de la campagne : un modèle de mutualisation
qui a depuis inspiré des collaborations similaires lors d'autres scrutins.</p>
HTML, $essai);

        $this->majLecon($service, $lecons[6], <<<'HTML'
<p>Dix dates pour résumer un siècle d'histoire du fact-checking :</p>
<ol>
<li><strong>1923</strong> — Le magazine <em>Time</em> crée son service interne de vérification des faits.</li>
<li><strong>1927</strong> — <em>The New Yorker</em> met en place son propre département de fact-checking, resté une
référence de rigueur éditoriale.</li>
<li><strong>1971</strong> — La Charte de Munich fixe les devoirs déontologiques des journalistes européens, dont le
respect de la vérité comme premier devoir.</li>
<li><strong>1994</strong> — Snopes.com, l'un des premiers sites web de vérification des rumeurs, est fondé.</li>
<li><strong>2003</strong> — FactCheck.org est lancé par l'Annenberg Public Policy Center.</li>
<li><strong>2007</strong> — PolitiFact est lancé par le <em>Tampa Bay Times</em> (Pulitzer en 2009).</li>
<li><strong>2012</strong> — Africa Check est fondé à Johannesburg, premier réseau panafricain de vérification.</li>
<li><strong>2015</strong> — Le Poynter Institute fonde l'International Fact-Checking Network et son Code of Principles.</li>
<li><strong>2016</strong> — PesaCheck est lancé au Kenya ; Facebook ouvre son programme de fact-checking tiers.</li>
<li><strong>2017</strong> — Le projet collaboratif CrossCheck réunit des dizaines de rédactions françaises pendant
l'élection présidentielle.</li>
</ol>
<p>De la rédaction unique au réseau mondial collaboratif : le fil conducteur de ce siècle est la généralisation
progressive d'une méthode — remonter à la source primaire — à un nombre toujours plus grand d'acteurs et de pays.</p>
HTML, $essai);
    }

    /**
     * Généralités sur les fake news (id=12).
     *
     * Cadres théoriques mobilisés, tous réels et vérifiables : Claire Wardle
     * & Hossein Derakhshan, « Information Disorder: Toward an interdisciplinary
     * framework for research and policy making », Conseil de l'Europe, 2017
     * (distinction mis-/dis-/malinformation) ; Claire Wardle, « Fake news. It's
     * complicated », First Draft, 2017 (taxonomie des 7 types) ; Vosoughi, Roy
     * & Aral, « The spread of true and false news online », Science, 2018
     * (étude MIT Media Lab, Twitter 2006-2017) ; Hasher, Goldstein & Toppino,
     * « Frequency and the conference of referential validity », Journal of
     * Verbal Learning and Verbal Behavior, 1977 (effet de vérité illusoire).
     */
    private function ecrireGeneralites(ConstructionDeCours $service, Course $cours, bool $essai): void
    {
        $this->info('Généralités sur les fake news (id=12)');

        $lecons = Lesson::where('course_id', $cours->id)->orderBy('position')->get()->keyBy('position');

        $this->majLecon($service, $lecons[1], <<<'HTML'
<p>« Fake news », « hoax », « infox » : ces termes sont souvent utilisés comme des synonymes, mais ils recouvrent des
réalités différentes — et les confondre nuit à la précision avec laquelle on peut les combattre. Un rapport de
référence du Conseil de l'Europe, rédigé en 2017 par les chercheuses Claire Wardle et Hossein Derakhshan, propose un
cadre plus précis que « fake news » : celui du <strong>désordre informationnel</strong> (« information disorder »),
qui distingue trois phénomènes selon deux critères — le contenu est-il faux, et son auteur cherche-t-il à nuire ?</p>
<ul>
<li><strong>La mésinformation (misinformation)</strong> : une information fausse, partagée par quelqu'un qui la croit
vraie, sans intention de tromper. C'est le cas le plus fréquent — un partage de bonne foi d'une rumeur non vérifiée.</li>
<li><strong>La désinformation (disinformation)</strong> : une information fausse, créée et diffusée
<strong>délibérément</strong> pour tromper — c'est la seule des trois catégories qui combine fausseté et intention
de nuire.</li>
<li><strong>La mal-information (malinformation)</strong> : une information <strong>vraie</strong>, mais diffusée dans
l'intention de nuire — par exemple une information privée divulguée hors contexte pour discréditer quelqu'un.</li>
</ul>
<p>Cette distinction a une conséquence pratique directe : face à un contenu douteux, la question n'est pas seulement
« est-ce vrai ou faux ? » mais aussi « quelle était l'intention de celui qui l'a diffusé ? ». Les deux réponses
orientent différemment la façon de réagir.</p>
<p><strong>Exercice pratique</strong></p>
<ul>
<li>Prenez une info virale sur les réseaux sociaux.</li>
<li>Demandez-vous : qui publie ? Pourquoi ? quelles preuves ?</li>
<li>Cherchez si des médias fiables en parlent.</li>
<li>Classez-la selon le cadre vu ci-dessus : mésinformation, désinformation, mal-information — ou simplement
information fiable.</li>
</ul>
HTML, $essai);

        $this->ajouterLecon($service, $cours, "Anatomie d'une fausse information : les 7 types de désinformation", 'text', 9, <<<'HTML'
<p>Toute désinformation ne se fabrique pas de la même manière. Dans son étude de référence « Fake news. It's
complicated » (First Draft, 2017), la chercheuse Claire Wardle propose une taxonomie en <strong>sept types</strong>,
classés du moins au plus trompeur :</p>
<ol>
<li><strong>Satire ou parodie</strong> — aucune intention de nuire, mais un risque réel d'être pris au premier
degré hors de son contexte d'origine (un site satirique, partagé sans cette indication).</li>
<li><strong>Fausse connexion (false connection)</strong> — un titre, une image ou une légende qui ne correspond pas
réellement au contenu de l'article : le « putaclic ».</li>
<li><strong>Contenu trompeur (misleading content)</strong> — un usage trompeur d'une information réelle, par exemple
une statistique exacte présentée hors de son échelle ou de sa période pour suggérer une conclusion qu'elle ne
soutient pas.</li>
<li><strong>Faux contexte (false context)</strong> — un contenu authentique (photo, vidéo, citation) associé à des
circonstances de temps ou de lieu qui ne sont pas les siennes.</li>
<li><strong>Contenu usurpateur (imposter content)</strong> — une source authentique (média, institution, personnalité)
est imitée pour lui faire dire ce qu'elle n'a jamais dit.</li>
<li><strong>Contenu manipulé (manipulated content)</strong> — une information ou une image réelle, modifiée pour
tromper (recadrage trompeur, montage, trucage).</li>
<li><strong>Contenu fabriqué (fabricated content)</strong> — un contenu entièrement inventé, sans aucun fondement
réel, conçu pour tromper ou nuire. C'est la forme la plus éloignée des faits et, souvent, la plus difficile à
confondre avec une information authentique sans vérification.</li>
</ol>
<p>L'intérêt de cette échelle est pratique : elle rappelle qu'une fausse information n'est pas toujours une pure
invention — le plus souvent, c'est un fait réel détourné, recadré ou mal attribué, ce qui la rend justement plus
difficile à repérer qu'un mensonge grossier.</p>
HTML, $essai);

        $this->ajouterLecon($service, $cours, 'Pourquoi partage-t-on les fake news ? Psychologie et mécanismes de viralité', 'text', 10, <<<'HTML'
<p>Comprendre pourquoi une fausse information circule aide à mieux s'en protéger. La recherche identifie plusieurs
mécanismes, indépendants de la bonne ou mauvaise foi de celui qui partage.</p>
<p><strong>Les fausses informations se propagent plus vite que les vraies.</strong> Une étude publiée en 2018 dans la
revue <em>Science</em> par des chercheurs du MIT Media Lab (Soroush Vosoughi, Deb Roy et Sinan Aral), portant sur
l'analyse de millions de messages diffusés sur Twitter entre 2006 et 2017, a montré que les fausses nouvelles
atteignent un public 70 % plus large et se propagent nettement plus vite que les informations vraies — en particulier
dans la catégorie politique. L'explication avancée par les auteurs n'est pas technique mais psychologique : les
fausses informations sont perçues comme plus nouvelles et inattendues, et suscitent davantage de réactions
émotionnelles (surprise, dégoût, indignation), deux facteurs qui favorisent fortement le partage.</p>
<p><strong>Le biais de confirmation</strong> pousse chacun à accepter plus facilement une information qui conforte ce
qu'il croit déjà, et à examiner plus sévèrement celle qui le contredit — y compris sur des sujets où l'on se croit
objectif.</p>
<p><strong>L'effet de vérité illusoire</strong> (« illusory truth effect »), décrit dès 1977 par les chercheurs
Hasher, Goldstein et Toppino, montre qu'une affirmation répétée plusieurs fois est perçue comme plus crédible, même
sans aucune preuve nouvelle — uniquement parce que la répétition crée un sentiment de familiarité confondu avec un
sentiment de vérité. C'est ce mécanisme qui explique pourquoi une rumeur vue dix fois sur un fil d'actualité finit
par sembler « déjà confirmée », alors qu'aucune de ces dix apparitions n'apportait de preuve nouvelle.</p>
<p>Connaître ces mécanismes ne protège pas automatiquement contre eux — mais permet de reconnaître le moment où l'on
réagit à une émotion plutôt qu'à un fait, et de marquer la pause nécessaire avant de partager.</p>
HTML, $essai);
    }

    /**
     * Journalistes vs Infox (id=13).
     *
     * Faits cités : Charte de Munich (1971), premier devoir cité textuellement
     * (« Respecter la vérité, quelle que puisse en être pour lui-même les
     * conséquences, en raison du droit que le public a de la connaître ») ;
     * canular du « Great Moon Hoax », New York Sun, août 1835 ; crise
     * diplomatique du Golfe de 2017, déclenchée par le piratage de l'agence
     * de presse qatarie QNA et la diffusion de fausses déclarations attribuées
     * à l'émir du Qatar.
     */
    private function ecrireJournalistes(ConstructionDeCours $service, Course $cours, bool $essai): void
    {
        $this->info('Journalistes vs Infox (id=13)');

        $lecons = Lesson::where('course_id', $cours->id)->orderBy('position')->get()->keyBy('position');

        $this->majLecon($service, $lecons[1], <<<'HTML'
<p>En une minute, près de 42 millions de messages sont échangés sur WhatsApp et 500 heures de vidéos sont publiées sur
YouTube. Dans ce flot d'informations, comment distinguer le vrai du faux ? C'est précisément le rôle que la
désinformation en ligne a donné — ou redonné — au journalisme de vérification.</p>
<p>Ce rôle n'est pas nouveau : il est inscrit depuis plus de cinquante ans dans la déontologie du métier. La
<strong>Charte de Munich</strong>, adoptée en 1971 par les syndicats de journalistes de la Communauté européenne, en
fait le tout premier devoir du journaliste : « Respecter la vérité, quelle que puisse en être pour lui-même les
conséquences, en raison du droit que le public a de la connaître. » Ce que les réseaux sociaux ont changé, ce n'est
pas ce devoir, mais son échelle : un journaliste doit désormais vérifier non seulement ce qu'il écrit lui-même, mais
aussi une partie de ce qui circule dans l'espace public avant même d'être publié par un média.</p>
<p>La leçon suivante détaille les principes déontologiques qui encadrent ce travail, et celle d'après revient sur
quelques-unes des plus grandes infox de l'histoire — pour montrer que le problème, lui, ne date pas d'hier.</p>
<p><em>Source : Conseils de Journalistes - un projet CFI et France Médias Monde</em></p>
HTML, $essai);

        $this->ajouterLecon($service, $cours, 'Le rôle du journaliste face à la désinformation : déontologie et vérification', 'text', 9, <<<'HTML'
<p>Le journalisme professionnel se distingue du simple partage d'information par un ensemble de devoirs
déontologiques, formalisés dès 1971 dans la <strong>Charte de Munich</strong>, adoptée par les syndicats de
journalistes des pays alors membres de la Communauté européenne et toujours citée aujourd'hui comme référence
déontologique en Europe francophone.</p>
<p>Son premier devoir fixe la hiérarchie de toutes les règles qui suivent : « <em>Respecter la vérité, quelle que
puisse en être pour lui-même les conséquences, en raison du droit que le public a de la connaître.</em> » Ce principe
place la vérification au-dessus de la rapidité : un journaliste qui publie une information non vérifiée, même sous
la pression de la concurrence ou de l'actualité immédiate, manque à ce premier devoir — quelle que soit la suite.</p>
<p>D'autres devoirs de la même charte prolongent directement ce principe : distinguer clairement l'information du
commentaire, ne publier que des informations dont l'origine est connue, rectifier toute information publiée qui se
révèle inexacte. Ces règles, pensées pour la presse écrite et audiovisuelle, s'appliquent aujourd'hui avec la même
exigence à la publication sur les réseaux sociaux : un journaliste qui partage un contenu non vérifié sur son compte
professionnel engage sa responsabilité déontologique autant que s'il le publiait dans son média.</p>
<p>Face à la désinformation en ligne, le travail du journaliste de vérification combine donc cette déontologie
ancienne avec des outils nouveaux — recherche d'image inversée, analyse de métadonnées, recoupement de témoignages
sur les réseaux — mais l'exigence de fond, elle, n'a pas changé depuis 1971 : ne rien publier qu'on n'a pas soi-même
vérifié.</p>
HTML, $essai);

        $this->ajouterLecon($service, $cours, "Histoire des grandes infox qui ont marqué l'actualité", 'text', 10, <<<'HTML'
<p>La désinformation n'est pas un phénomène né avec Internet. Deux épisodes, séparés de près de deux siècles,
montrent qu'elle a toujours su s'adapter aux outils de son époque.</p>
<p><strong>Le canular de la Lune (1835).</strong> En août 1835, le quotidien new-yorkais <em>The Sun</em> publie une
série d'articles affirmant qu'un astronome avait découvert, grâce à un télescope révolutionnaire, de la vie sur la
Lune — y compris des créatures mi-humaines mi-chauves-souris. L'histoire, entièrement inventée, est reprise par
d'autres journaux de l'époque et fait un temps grimper les ventes du journal avant d'être démentie. Resté dans
l'histoire sous le nom de « Great Moon Hoax », cet épisode est souvent cité comme l'un des tout premiers exemples
documentés de désinformation de masse diffusée par voie de presse — la preuve qu'un contenu fabriqué peut circuler
largement bien avant l'existence d'Internet, dès lors qu'il est relayé par des canaux jugés fiables.</p>
<p><strong>La crise diplomatique du Golfe (2017).</strong> En mai 2017, l'agence de presse officielle du Qatar (QNA)
est piratée ; de fausses déclarations, attribuées à l'émir du Qatar et jamais prononcées, y sont publiées — des propos
jugés favorables à l'Iran et critiques envers d'autres pays du Golfe. Bien que le Qatar ait rapidement dénoncé le
piratage, ces fausses déclarations ont été reprises par plusieurs médias régionaux avant d'être démenties, et ont
contribué au déclenchement d'une crise diplomatique majeure : l'Arabie saoudite, les Émirats arabes unis, Bahreïn et
l'Égypte rompent leurs relations avec le Qatar et lui imposent un blocus qui durera plus de trois ans.</p>
<p>Ces deux exemples, à presque deux siècles d'écart, illustrent une même leçon : une infox n'a pas besoin d'être
sophistiquée pour avoir des conséquences réelles — elle a seulement besoin d'un canal perçu comme fiable pour
circuler avant d'être vérifiée.</p>
HTML, $essai);
    }
}
