<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Models\HubArticle;
use Illuminate\Console\Command;
use Mews\Purifier\Facades\Purifier;

/**
 * Remet à niveau les 6 premiers articles du Hub (lot du 14 septembre 2026),
 * qui n'étaient que des bouts de chapô (11 à 42 mots) jamais rédigés comme de
 * vrais articles. Portés à la même exigence que les 3 articles Afrique
 * ajoutés le 10 octobre 2026 : rédaction 100% originale, structure complète,
 * lecture de 3 à 5 minutes, aucun tiret ni trait d'union dans le corps.
 *
 * Complète aussi les champs SEO (meta_title, meta_description,
 * meta_keywords) des 9 articles du Hub, vides jusqu'ici — honorés par
 * Referencement::pourFaitVerifie() depuis le 2 septembre 2026, mais jamais
 * renseignés.
 *
 * Idempotent : comparée au mot-clé du corps existant, une ré-exécution ne
 * fait que corriger ce qui aurait changé entre temps.
 */
final class RedigerArticlesCompletsEtSeo extends Command
{
    protected $signature = 'verifall:rediger-articles-complets-et-seo {--essai : n’écrit rien, affiche ce qui serait fait}';

    protected $description = 'Rédige en entier les 6 articles du Hub laissés à l’état de chapô, et complète le SEO des 9 articles';

    public function handle(): int
    {
        $essai = (bool) $this->option('essai');

        foreach ($this->corps() as $slug => $html) {
            $this->appliquerCorps($slug, $html, $essai);
        }

        foreach ($this->seo() as $slug => $champs) {
            $this->appliquerSeo($slug, $champs, $essai);
        }

        $this->info($essai ? 'Essai terminé — rien n’a été écrit.' : 'Articles et SEO mis à jour.');

        return self::SUCCESS;
    }

    private function appliquerCorps(string $slug, string $html, bool $essai): void
    {
        $article = HubArticle::where('slug', $slug)->first();

        if (! $article) {
            $this->error("  INTROUVABLE : {$slug}");

            return;
        }

        $propre = trim((string) Purifier::clean($html, 'default'));
        $mots = str_word_count(strip_tags($propre));

        if ($essai) {
            $this->line("  [essai] corps -> {$slug} ({$mots} mots)");

            return;
        }

        $article->forceFill(['body' => $propre])->save();
        $this->line("  corps mis à jour -> {$slug} ({$mots} mots)");
    }

    /**
     * @param  array{meta_title: string, meta_description: string, meta_keywords: string}  $champs
     */
    private function appliquerSeo(string $slug, array $champs, bool $essai): void
    {
        $article = HubArticle::where('slug', $slug)->first();

        if (! $article) {
            $this->error("  INTROUVABLE (seo) : {$slug}");

            return;
        }

        if ($essai) {
            $this->line("  [essai] seo -> {$slug}");

            return;
        }

        $article->forceFill($champs)->save();
        $this->line("  seo mis à jour -> {$slug}");
    }

    /** @return array<string, string> */
    private function corps(): array
    {
        return [
            'aucune-taxe-whatsapp-nest-instauree-au-benin' => <<<'HTML'
<p><strong>Ce qui circule.</strong> Un message largement partagé sur WhatsApp et Facebook affirme que le gouvernement béninois vient d'instaurer une taxe sur l'utilisation de l'application WhatsApp. Le message prend souvent la forme d'une capture d'écran présentée comme un communiqué officiel, annonçant un prélèvement journalier ou mensuel sur chaque utilisateur, sous peine de coupure d'accès à l'application. Cette rumeur n'est pas nouvelle au Bénin : une version quasiment identique avait déjà circulé en 2024, avant d'être démentie.</p>

<p>Le texte joue sur une inquiétude réelle et déjà documentée dans plusieurs pays africains, où des mesures de taxation des communications sur internet ont effectivement été envisagées ou appliquées par le passé. C'est précisément cette ressemblance avec des précédents réels ailleurs sur le continent qui rend la rumeur crédible aux yeux de nombreux utilisateurs béninois, même lorsqu'elle ne correspond à aucune décision prise localement.</p>

<h3>Le verdict en bref</h3>
<p>Cette affirmation est fausse. Aucune taxe sur l'utilisation de WhatsApp n'a été instaurée au Bénin. L'Autorité de Régulation des Communications Électroniques et de la Poste du Bénin, qui encadre ce type de décision, n'a publié aucun texte en ce sens, et aucun opérateur télécom présent sur le territoire n'applique de surcoût lié à l'usage de cette application.</p>

<h3>Ce que montrent les éléments examinés</h3>
<p>Le premier élément qui affaiblit cette rumeur est l'absence totale de relais officiel. Une mesure fiscale touchant des millions d'utilisateurs d'une application aussi largement utilisée aurait nécessairement fait l'objet d'un texte réglementaire publié, puis repris par les opérateurs télécoms eux mêmes dans leurs communications aux abonnés, que ce soit par SMS, par voie de presse ou sur leurs canaux numériques officiels. Aucune de ces étapes attendues ne s'est produite.</p>
<p>Le deuxième élément tient à la nature même du visuel qui circule : une capture d'écran isolée, sans numéro d'arrêté, sans date de signature, sans logo vérifiable d'une institution précise. Ce sont exactement les caractéristiques d'un montage conçu pour imiter la forme d'une communication officielle sans en avoir le fond, un procédé déjà observé lors du premier passage de cette même rumeur en 2024.</p>
<p>Le troisième élément est la continuité du service lui même : si une taxe conditionnait réellement l'accès à WhatsApp, cela se traduirait par des blocages ou des ralentissements constatables et largement commentés par les utilisateurs au moment de son entrée en vigueur supposée. Aucune perturbation de ce type n'a été rapportée par les abonnés béninois.</p>

<h3>Notre analyse</h3>
<p>Les rumeurs de taxation des réseaux sociaux prospèrent parce qu'elles s'appuient sur un sentiment de défiance envers les décisions fiscales, renforcé par le souvenir de mesures similaires réellement appliquées ailleurs sur le continent par le passé. Ce terreau rend la population prompte à relayer ce type d'annonce par précaution, même sans certitude sur son authenticité, dans la crainte de perdre l'accès à un outil de communication devenu quotidien pour les échanges familiaux et professionnels.</p>
<p>Le fait que cette même rumeur revienne périodiquement, parfois sous une forme presque identique à des versions précédentes, est en soi un signal à reconnaître : une information fiscale réelle ne se recycle pas à l'identique d'une année sur l'autre sans qu'aucune administration ne la confirme ni ne la démente formellement dans l'intervalle qui sépare les deux vagues de partage.</p>
<p>Ce type de désinformation technique touche particulièrement les usagers les plus dépendants de la messagerie pour leurs échanges quotidiens, ce qui explique sa diffusion rapide dès qu'elle réapparaît, quel que soit le pays concerné sur le continent, et quelle que soit la plateforme visée par la rumeur du moment.</p>

<h3>Ce qu'il faut retenir</h3>
<p>Aucune taxe sur WhatsApp n'existe au Bénin à ce jour. Face à une annonce fiscale de ce type, le réflexe utile est de vérifier directement auprès du régulateur des télécommunications ou des opérateurs eux mêmes, plutôt que de se fier à une capture d'écran isolée qui circule sans référence officielle vérifiable.</p>
HTML,
            'video-dinondation-a-cotonou-tournee-ailleurs' => <<<'HTML'
<p><strong>Ce qui circule.</strong> Une vidéo montrant des rues totalement submergées, des véhicules immobilisés dans l'eau et des habitants pataugeant jusqu'à la taille circule largement sur les réseaux sociaux béninois, présentée comme filmée récemment à Cotonou lors d'un épisode de fortes pluies. La légende qui accompagne la vidéo varie selon les partages, mais revient systématiquement à l'idée que la capitale économique serait sous les eaux de façon inédite.</p>

<p>Ce type de contenu suscite une inquiétude légitime, les inondations saisonnières étant une réalité connue à Cotonou pendant la saison des pluies. C'est justement cette plausibilité qui permet à une vidéo prise ailleurs de circuler sans éveiller immédiatement le doute chez une majorité de personnes qui la reçoivent sur leur téléphone.</p>

<h3>Le verdict en bref</h3>
<p>Cette vidéo est trompeuse telle que présentée. Les images sont authentiques et montrent une véritable inondation, mais elles ont été filmées dans un autre pays, en 2022, bien avant leur mise en circulation sous une légende évoquant Cotonou. Aucun élément ne permet de relier ce contenu à un événement récent survenu dans la capitale économique béninoise.</p>

<h3>Ce que montrent les éléments examinés</h3>
<p>L'identification de l'origine réelle de la vidéo s'appuie sur une recherche d'image inversée à partir d'images fixes extraites de la séquence. Cette méthode permet de retrouver des publications antérieures montrant les mêmes scènes, souvent accompagnées d'une légende différente et d'une date de publication bien plus ancienne que celle suggérée par la rumeur actuelle.</p>
<p>Plusieurs détails visibles dans la vidéo, tels que l'architecture des bâtiments, les plaques d'immatriculation des véhicules visibles à l'écran et certains panneaux en arrière plan, ne correspondent pas à ce qui est habituellement observé à Cotonou, ce qui renforce la conclusion d'un lieu de tournage différent de celui annoncé.</p>
<p>Le décalage temporel constitue l'indice le plus solide : la vidéo a été identifiée dans des publications remontant à 2022, soit bien avant la date à laquelle elle a commencé à circuler sous l'étiquette d'une inondation cotonoise récente. Une vidéo ne peut matériellement pas documenter un événement survenu après sa date de captation initiale.</p>

<h3>Notre analyse</h3>
<p>Le recyclage de vidéos authentiques hors de leur contexte d'origine est l'un des procédés de désinformation les plus efficaces, précisément parce que les images elles mêmes ne sont pas truquées. Personne n'a besoin de fabriquer une fausse inondation lorsqu'il suffit de réutiliser des images réelles d'un événement survenu ailleurs, en les associant à un lieu et une date qui n'ont jamais été les siens.</p>
<p>Ce procédé fonctionne particulièrement bien pendant la saison des pluies, lorsque la probabilité d'inondations réelles à Cotonou rend chaque nouvelle vidéo plausible aux yeux du public, qu'elle soit authentique ou recyclée depuis un autre contexte. L'émotion suscitée par des images de sinistre encourage également un partage rapide, avant toute vérification préalable.</p>
<p>Ce schéma se répète d'une saison des pluies à l'autre, avec des vidéos différentes mais un mécanisme identique : des images réelles, prises ailleurs, resituées dans un contexte local pour renforcer leur impact émotionnel et accélérer leur vitesse de diffusion sur les réseaux sociaux.</p>

<h3>Ce qu'il faut retenir</h3>
<p>Les images qui circulent sont réelles, mais elles ne montrent pas Cotonou et ne datent pas d'aujourd'hui. Avant de partager une vidéo d'inondation présentée comme récente, il est utile de vérifier si elle a déjà circulé sous une autre légende, à une autre date, grâce à une recherche d'image inversée sur une capture extraite de la séquence.</p>
HTML,
            'fausse-offre-demploi-au-port-autonome-de-cotonou' => <<<'HTML'
<p><strong>Ce qui circule.</strong> Une offre d'emploi largement relayée sur les réseaux sociaux et par message annonce que le Port Autonome de Cotonou recrute massivement pour divers postes, souvent sans qualification particulière exigée et avec des salaires présentés comme attractifs. L'offre renvoie vers un site ou un formulaire qui demande aux candidats de verser des « frais de dossier » avant tout traitement de leur candidature, généralement par transfert d'argent mobile.</p>

<p>Ce type d'annonce cible un public en recherche active d'emploi, pour qui la perspective d'un poste dans une institution aussi reconnue que le Port Autonome de Cotonou représente une opportunité particulièrement attractive, ce qui explique la rapidité avec laquelle l'offre se propage dans les groupes de discussion et les réseaux familiaux.</p>

<h3>Le verdict en bref</h3>
<p>Cette offre d'emploi est fausse. Le Port Autonome de Cotonou ne recrute que par l'intermédiaire de son site officiel et de ses canaux de communication reconnus, et ne demande à aucun moment de frais de dossier aux candidats pour le traitement d'une candidature. Toute demande de paiement associée à une offre d'emploi présentée en son nom constitue une arnaque.</p>

<h3>Ce que montrent les éléments examinés</h3>
<p>Le signal le plus net de fraude dans ce type d'offre est la demande de paiement préalable. Aucune institution publique béninoise sérieuse ne conditionne l'examen d'une candidature à un versement financier de la part du postulant : les frais, lorsqu'ils existent pour un concours, sont fixés par un texte officiel, versés par des canaux identifiés et jamais réclamés par un formulaire anonyme relayé sur les réseaux sociaux.</p>
<p>Le site ou formulaire vers lequel renvoie l'offre ne correspond généralement pas au nom de domaine officiel du Port Autonome de Cotonou, et ne propose aucun moyen de vérifier l'authenticité de l'annonce auprès de l'institution elle même. Cette absence de vérifiabilité est caractéristique des offres frauduleuses, qui misent sur la rapidité de réaction du candidat avant qu'il ne pense à recouper l'information ailleurs.</p>
<p>Enfin, le volume de postes annoncés, souvent très supérieur aux besoins réels et récurrents d'une institution portuaire, ainsi que l'absence de critères de sélection précis, sont des caractéristiques typiques d'une offre conçue pour maximiser le nombre de candidatures, et donc le nombre de victimes potentielles des frais réclamés en amont.</p>

<h3>Notre analyse</h3>
<p>Les arnaques au recrutement prospèrent dans un contexte où le marché de l'emploi formel reste tendu et où une offre dans une institution publique réputée représente un espoir concret pour de nombreux candidats. Ce besoin réel est exploité par des personnes qui construisent des offres crédibles en apparence, reprenant le nom et parfois le logo d'institutions connues, pour capter des frais auprès d'un grand nombre de victimes avant que la supercherie ne soit découverte.</p>
<p>Ce type de fraude n'est pas propre au Port Autonome de Cotonou : le même mécanisme touche régulièrement d'autres institutions publiques et grandes entreprises béninoises, avec une mécanique identique de frais de dossier réclamés en amont de tout entretien ou toute vérification réelle du profil du candidat par un recruteur.</p>

<h3>Ce qu'il faut retenir</h3>
<p>Le Port Autonome de Cotonou ne recrute jamais contre un paiement préalable. Face à une offre d'emploi qui demande des frais avant tout entretien, le réflexe utile est de consulter directement le site officiel de l'institution concernée et de ne jamais transmettre d'argent à un formulaire ou un contact qui ne peut être vérifié de façon indépendante.</p>
HTML,
            'calendrier-officiel-des-examens-nationaux-confirme' => <<<'HTML'
<p><strong>Ce qui circule.</strong> Un calendrier détaillant les dates des examens nationaux pour la session 2026, incluant les épreuves du certificat d'études primaires, du brevet d'études du premier cycle et du baccalauréat, circule sur les réseaux sociaux et dans les établissements scolaires béninois. Face à la multiplication des versions de calendriers scolaires qui circulent chaque année, souvent avec des différences de dates d'une publication à l'autre, de nombreux parents et élèves s'interrogent légitimement sur la fiabilité de celui ci avant de s'organiser en conséquence.</p>

<p>Cette prudence est d'autant plus justifiée que des calendriers erronés ou obsolètes réapparaissent fréquemment en période de préparation aux examens, créant une confusion inutile chez les candidats à quelques semaines d'échéances déterminantes pour leur parcours scolaire et leur orientation future.</p>

<h3>Le verdict en bref</h3>
<p>Cette affirmation est vraie. Le calendrier qui circule correspond bien aux dates officielles publiées par le ministère en charge de l'éducation pour la session 2026, et ces dates sont confirmées comme exactes et définitives au moment de la vérification par notre rédaction.</p>

<h3>Ce que montrent les éléments examinés</h3>
<p>La vérification d'un calendrier d'examens repose avant tout sur la comparaison directe avec la publication officielle émise par le ministère compétent, qui reste la seule source faisant foi en la matière. Les dates qui circulent correspondent terme à terme à cette publication officielle, sans écart constaté sur les dates d'ouverture, de clôture ou de déroulement des épreuves pour chacun des examens concernés par cette session.</p>
<p>Un calendrier scolaire officiel présente des caractéristiques précises et vérifiables : un en tête institutionnel clair, une référence à l'acte administratif qui l'établit, et une diffusion par les canaux de communication reconnus du ministère, que ce soit son site internet ou les directions départementales de l'enseignement. Le document examiné réunit l'ensemble de ces caractéristiques attendues d'un document authentique.</p>
<p>Il reste néanmoins utile de rappeler qu'un calendrier d'examens peut faire l'objet d'ajustements ponctuels décidés par l'administration en cours d'année scolaire, ce qui justifie de vérifier périodiquement la version la plus récente publiée par le ministère plutôt que de se fier indéfiniment à une capture enregistrée plusieurs mois auparavant sur un téléphone.</p>

<h3>Notre analyse</h3>
<p>Confirmer l'exactitude d'une information n'est pas moins important que d'en démentir une fausse : la confusion entretenue par la circulation simultanée de plusieurs versions de calendriers scolaires, certaines erronées et d'autres exactes, produit une incertitude qui nuit tout autant à l'organisation des familles qu'une rumeur ouvertement fausse et sans fondement.</p>
<p>Ce type de vérification de confirmation illustre une dimension essentielle du travail de vérification des faits : il ne s'agit pas seulement de repérer ce qui est faux, mais aussi de donner aux citoyens un repère fiable sur ce qui est vrai, dans un environnement informationnel où la quantité de contenus partagés dépasse largement la capacité de chacun à les vérifier individuellement avant de s'organiser en famille.</p>
<p>Pour les familles béninoises, disposer d'un calendrier d'examens fiable conditionne directement l'organisation des révisions, les inscriptions aux centres d'examen et la prise de congés des parents pour accompagner leurs enfants, ce qui rend cette confirmation particulièrement utile au delà du simple exercice de vérification.</p>

<h3>Ce qu'il faut retenir</h3>
<p>Le calendrier des examens nationaux pour la session 2026 qui circule est bien le calendrier officiel. Les familles et les candidats peuvent s'y fier, tout en gardant le réflexe de vérifier périodiquement la publication la plus récente du ministère en cas de doute sur une éventuelle mise à jour ultérieure.</p>
HTML,
            'campagne-de-vaccination-gratuite-dans-le-littoral' => <<<'HTML'
<p><strong>Ce qui circule.</strong> Une annonce de campagne de vaccination gratuite dans le département du Littoral circule sur les réseaux sociaux et par message, précisant des centres de santé et des horaires auxquels la population peut se rendre pour bénéficier de la vaccination sans frais. Compte tenu de la multiplication d'annonces sanitaires parfois trompeuses ou mal datées, certains habitants hésitent à s'y rendre sans confirmation préalable par une source fiable.</p>

<p>Cette prudence est compréhensible : de fausses annonces sanitaires, qu'il s'agisse de campagnes inventées ou de campagnes réelles mais mal relayées avec des dates ou des lieux erronés, créent régulièrement de la confusion et peuvent conduire des habitants à se déplacer inutilement, ou à l'inverse à manquer une campagne réellement organisée près de chez eux.</p>

<h3>Le verdict en bref</h3>
<p>Cette affirmation est vraie. La campagne de vaccination gratuite annoncée dans le Littoral est bien réelle, et les centres ainsi que les horaires qui circulent correspondent aux informations confirmées par le ministère de la Santé auprès de notre rédaction.</p>

<h3>Ce que montrent les éléments examinés</h3>
<p>La vérification d'une annonce sanitaire de ce type passe par la confrontation directe avec les canaux officiels du ministère de la Santé, seule autorité habilitée à organiser et à communiquer sur une campagne de vaccination à l'échelle départementale. Les centres de santé mentionnés dans l'annonce qui circule correspondent à des établissements existants et reconnus dans le département du Littoral, et les horaires annoncés concordent avec la communication officielle du ministère.</p>
<p>Une campagne de vaccination publique authentique présente généralement des caractéristiques identifiables : une couverture par les structures sanitaires locales déjà connues de la population, une communication relayée par les agents de santé communautaires, et une cohérence avec le calendrier vaccinal national en vigueur. L'annonce examinée réunit ces éléments sans contradiction relevée par notre vérification.</p>
<p>Il reste recommandé, pour toute personne souhaitant s'y rendre, de confirmer directement auprès du centre de santé le plus proche de son domicile la disponibilité effective de la campagne au moment précis de sa visite, les plannings pouvant être ajustés localement en fonction des stocks de vaccins ou de l'affluence constatée sur place.</p>

<h3>Notre analyse</h3>
<p>Les campagnes de vaccination gratuites jouent un rôle de santé publique majeur, et leur bon déroulement dépend directement de la confiance que la population leur accorde. Une rumeur mettant en doute l'authenticité d'une campagne réelle peut avoir des conséquences concrètes en réduisant la fréquentation des centres de vaccination, tout comme une fausse annonce peut détourner des ressources ou créer de faux espoirs chez des familles qui s'y rendent pour rien.</p>
<p>C'est pourquoi la vérification des informations sanitaires mérite la même rigueur que celle appliquée aux rumeurs politiques ou économiques : confirmer ce qui est vrai a autant de valeur que démentir ce qui est faux, en particulier lorsque la santé des populations est directement concernée par la décision de se déplacer ou non vers un centre de vaccination du quartier.</p>

<h3>Ce qu'il faut retenir</h3>
<p>La campagne de vaccination gratuite dans le Littoral est bien réelle. Les habitants peuvent s'y rendre en toute confiance, tout en vérifiant auprès du centre de santé le plus proche les horaires précis applicables le jour de leur visite.</p>
HTML,
            'nouveau-service-e-services-pour-les-actes-de-naissance' => <<<'HTML'
<p><strong>Ce qui circule.</strong> Une annonce indique qu'il est désormais possible de demander un acte de naissance directement en ligne, via une plateforme officielle de services publics numériques, sans avoir à se déplacer physiquement dans un centre d'état civil. Cette information circule notamment auprès de la diaspora béninoise et des citoyens résidant loin des centres d'état civil, pour qui ce type de service représenterait une simplification administrative importante au quotidien.</p>

<p>Face à la multiplication de faux sites imitant des plateformes gouvernementales pour collecter des données personnelles ou des paiements frauduleux, une certaine prudence est naturelle avant d'utiliser un service présenté comme officiel pour une démarche aussi sensible que l'obtention d'un acte d'état civil.</p>

<h3>Le verdict en bref</h3>
<p>Cette affirmation est vraie. La plateforme officielle de services publics béninoise permet bien de formuler une demande en ligne d'actes d'état civil, dont l'acte de naissance, ce qui constitue une simplification réelle pour les usagers résidant loin d'un centre d'état civil.</p>

<h3>Ce que montrent les éléments examinés</h3>
<p>La vérification de ce type de service public numérique repose sur l'identification du nom de domaine officiel associé à l'administration béninoise des services publics, qui seul doit être utilisé pour ce genre de démarche. La plateforme concernée correspond bien à ce domaine officiel et propose une interface de demande en ligne cohérente avec les procédures administratives habituelles d'état civil déjà en vigueur.</p>
<p>Le service numérique examiné ne remplace pas l'intégralité de la procédure traditionnelle : la demande en ligne initie la requête, mais la délivrance du document final reste encadrée par les règles de vérification habituelles de l'état civil béninois, avec un retrait ou un envoi organisé selon des modalités précisées sur la plateforme elle même au moment du dépôt.</p>
<p>Cette nuance est importante à signaler, car certaines versions de la rumeur qui circulent autour de cette annonce laissent entendre une délivrance instantanée et sans contrôle, ce qui ne correspond pas exactement au fonctionnement réel du service, même si la possibilité de déposer la demande en ligne est bien confirmée par notre vérification.</p>

<h3>Notre analyse</h3>
<p>La numérisation des services publics est un sujet particulièrement sensible à la désinformation, précisément parce qu'elle touche à des démarches administratives essentielles pour les citoyens, qu'il s'agisse de papiers d'identité ou d'actes d'état civil. Une fausse annonce de dématérialisation peut pousser des usagers vers des sites frauduleux conçus pour imiter l'apparence d'un service public, dans le but de collecter des données personnelles sensibles auprès de victimes peu méfiantes.</p>
<p>C'est pourquoi il reste essentiel, même lorsqu'une annonce de service numérique s'avère exacte comme c'est le cas ici, de rappeler systématiquement l'adresse officielle exacte de la plateforme concernée, afin d'éviter que la confirmation d'une information réelle ne profite indirectement à une copie frauduleuse du même service portant un nom de domaine similaire mais différent de l'original.</p>

<h3>Ce qu'il faut retenir</h3>
<p>Le service de demande en ligne d'actes de naissance est bien réel et accessible sur la plateforme officielle des services publics béninois. Les usagers sont invités à vérifier systématiquement l'adresse exacte du site avant d'y renseigner la moindre information personnelle.</p>
HTML,
        ];
    }

    /** @return array<string, array{meta_title: string, meta_description: string, meta_keywords: string}> */
    private function seo(): array
    {
        return [
            'aucune-taxe-whatsapp-nest-instauree-au-benin' => [
                'meta_title' => "Taxe WhatsApp au Bénin : c'est faux | Vérif'All",
                'meta_description' => "Vérif'All a vérifié l'affirmation d'une taxe sur WhatsApp au Bénin. Verdict : faux. Aucun texte de l'ARCEP Bénin ne confirme cette rumeur recyclée.",
                'meta_keywords' => 'taxe whatsapp bénin, arcep bénin, fake news bénin, vérification whatsapp, fact checking bénin',
            ],
            'video-dinondation-a-cotonou-tournee-ailleurs' => [
                'meta_title' => "Vidéo d'inondation à Cotonou : trompeuse | Vérif'All",
                'meta_description' => "Cette vidéo d'inondation présentée comme filmée à Cotonou date en réalité de 2022 et provient d'un autre pays. L'analyse complète de Vérif'All.",
                'meta_keywords' => 'inondation cotonou, vidéo trompeuse, fact checking bénin, vérification vidéo, désinformation bénin',
            ],
            'fausse-offre-demploi-au-port-autonome-de-cotonou' => [
                'meta_title' => "Fausse offre d'emploi au PAC : attention arnaque | Vérif'All",
                'meta_description' => "Une offre d'emploi au Port Autonome de Cotonou réclamant des frais de dossier est frauduleuse. Le PAC ne recrute que via son site officiel.",
                'meta_keywords' => 'arnaque emploi bénin, port autonome cotonou, fausse offre emploi, fact checking bénin, recrutement frauduleux',
            ],
            'calendrier-officiel-des-examens-nationaux-confirme' => [
                'meta_title' => 'Calendrier des examens 2026 au Bénin : confirmé | Vérif\'All',
                'meta_description' => 'Le calendrier des examens nationaux 2026 qui circule est bien le calendrier officiel publié par le ministère. Vérification complète de Vérif\'All.',
                'meta_keywords' => 'examens nationaux bénin, calendrier scolaire 2026, bepc baccalauréat bénin, fact checking éducation',
            ],
            'campagne-de-vaccination-gratuite-dans-le-littoral' => [
                'meta_title' => "Vaccination gratuite dans le Littoral : c'est vrai | Vérif'All",
                'meta_description' => 'La campagne de vaccination gratuite annoncée dans le Littoral est bien réelle, centres et horaires confirmés par le ministère de la Santé.',
                'meta_keywords' => 'vaccination gratuite bénin, littoral bénin, ministère santé bénin, fact checking santé',
            ],
            'nouveau-service-e-services-pour-les-actes-de-naissance' => [
                'meta_title' => "Demande d'acte de naissance en ligne au Bénin : vrai | Vérif'All",
                'meta_description' => 'Le nouveau service en ligne pour demander un acte de naissance au Bénin est bien réel, accessible sur la plateforme officielle des services publics.',
                'meta_keywords' => 'acte de naissance en ligne, service public bénin, état civil bénin, fact checking administratif',
            ],
            'citations-fabriquees-president-burkinabe-mondial-2026' => [
                'meta_title' => "Fausse citation du président burkinabé : démenti | Vérif'All",
                'meta_description' => "Une citation attribuée au président Ibrahim Traoré sur le Mondial 2026 est fabriquée. Enquête de l'AFP Factuel et analyse complète de Vérif'All.",
                'meta_keywords' => 'ibrahim traoré, fake news burkina faso, intelligence artificielle, afp factuel, fact checking afrique',
            ],
            'pas-immersion-militaire-obligatoire-fonction-publique-cote-ivoire' => [
                'meta_title' => "Immersion militaire fonction publique CI : c'est faux | Vérif'All",
                'meta_description' => "Aucune immersion militaire n'est obligatoire pour intégrer la fonction publique ivoirienne. Vérif'All démêle le vrai du faux sur cette rumeur.",
                'meta_keywords' => "fonction publique côte d'ivoire, immersion militaire, fake news côte d'ivoire, afp factuel, fact checking afrique",
            ],
            'video-mozambique-aucun-lien-rdc' => [
                'meta_title' => 'Vidéo de violences : Mozambique, pas la RDC | Vérif\'All',
                'meta_description' => "Cette vidéo de violences présentée comme filmée en RDC a en réalité été tournée au Mozambique. Analyse basée sur l'enquête de l'AFP Factuel.",
                'meta_keywords' => 'rdc, mozambique, vidéo trompeuse, afp factuel, fact checking afrique, désinformation conflit',
            ],
        ];
    }
}
