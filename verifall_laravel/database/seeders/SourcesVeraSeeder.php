<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SourceCategory;
use App\Models\TrustedSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Élargissement du référentiel — 395 sources reprises du répertoire public de Vera.
 *
 * ## D'où viennent ces lignes
 *
 * De la page publique « Les sources sur lesquelles s'appuie Vera », relevée le
 * 9 septembre 2026. Elle range chaque entrée en trois familles — fact-checking,
 * publication de référence, source experte — et lui associe un pays ou une
 * région.
 *
 * ## Ce que ce seeder n'écrase JAMAIS
 *
 * Il est en **insertion seule**, et c'est le point important. Le référentiel de
 * départ ({@see SourcesFiablesSeeder}) porte 22 fiches dont la crédibilité est
 * censée être amendée depuis l'écran d'administration. Un `updateOrCreate` les
 * remettrait à la valeur d'usine à chaque passage, en silence — `africacheck.org`
 * et `apnews.com` figurent dans les deux listes.
 *
 * La clé de dédoublonnage est le **domaine normalisé**
 * ({@see TrustedSource::normaliserLeDomaine()}), la même que celle de l'écran et
 * du seeder d'origine. Semer deux fois ne produit donc jamais deux fiches, et la
 * colonne `domain` porte de toute façon un index unique : la base refuserait.
 *
 * ## Ce qui a été ÉCARTÉ, et pourquoi
 *
 * La source affiche ses URL dans une colonne à largeur fixe, qui les COUPE.
 * 55 entrées arrivent avec un hôte incomplet — `science.feedback.or`,
 * `nationalgeographic.con`, `pubmed.ncbi.nlm.nih.go`. Le domaine manquant est
 * souvent devinable, et il n'a pas été deviné : une fiche de référentiel dont le
 * domaine est faux est pire qu'une fiche absente, parce que le moteur s'en sert
 * pour créditer une source. Elles sont listées dans
 * `docs/SOURCES_A_COMPLETER.md`, à reprendre à la main.
 *
 * ## Les crédibilités sont celles de la FAMILLE, pas de la source
 *
 * Aucune note individuelle n'a été inventée. Chaque fiche reçoit la valeur par
 * défaut de sa famille, volontairement prudente, et la rédaction l'amende depuis
 * l'écran. Le champ `notes` porte la région d'origine et la provenance : c'est ce
 * qui permet de retrouver, plus tard, ce qui vient d'ici et ce qui a été décidé.
 *
 * ## Une réserve, qui appartient au porteur du projet
 *
 * Reprendre ce répertoire, c'est importer la sélection éditoriale d'un tiers.
 * Le seeder d'origine s'en tenait à des institutions « dont le statut ne se
 * discute pas ». Ces 395 entrées sont d'une autre nature : ce sont des
 * rédactions. Elles sont livrées ACTIVES parce qu'un référentiel inactif ne sert
 * à rien, mais la liste mérite une relecture éditoriale avant la mise en ligne.
 */
final class SourcesVeraSeeder extends Seeder
{
    public function run(): void
    {
        $famillesNouvelles = [
            'publications-de-reference' => [
                'Publications de référence', 78,
                'Titres de presse et publications établies, hors agences. Le suivi éditorial reste nécessaire.',
                60,
            ],
            'sources-expertes' => [
                'Sources expertes', 82,
                'Revues scientifiques, institutions de recherche et spécialistes d\'un domaine.',
                70,
            ],
        ];

        foreach ($famillesNouvelles as $slug => [$nom, $credibilite, $description, $ordre]) {
            SourceCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $nom,
                    'description' => $description,
                    'default_credibility' => $credibilite,
                    'sort_order' => $ordre,
                ],
            );
        }

        /*
         | La famille du fact-checking appartient au référentiel de départ
         | ({@see SourcesFiablesSeeder}) — mais celui-ci n'est appelé par AUCUN
         | autre seeder : il se lance à la main. Sur une base neuve, la famille
         | n'existe donc pas, et se contenter de la chercher rangeait
         | 90 sources sans famille du tout, sans que rien ne le signale.
         |
         | `firstOrCreate` et non `updateOrCreate` : si elle est là, on n'y
         | touche pas. Sa description et sa crédibilité ont pu être amendées
         | depuis l'écran, et ce seeder n'a pas à les ramener à l'usine.
         */
        SourceCategory::query()->firstOrCreate(
            ['slug' => 'reseaux-de-fact-checking'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Réseaux de fact-checking',
                'description' => 'Rédactions membres de réseaux de vérification reconnus.',
                'default_credibility' => 88,
                'sort_order' => 50,
            ],
        );

        $familles = SourceCategory::query()->pluck('id', 'slug');

        $sources = [

            // — Fact-checking → reseaux-de-fact-checking
            ['20minutes.fr', '20 Minutes - Fake Off', 'France', 'reseaux-de-fact-checking'],
            ['237check.org', '237Check', 'Cameroun', 'reseaux-de-fact-checking'],
            ['aap.com.au', 'aap factcheck', 'Australie', 'reseaux-de-fact-checking'],
            ['aap.org', 'American Academy of Pediatrics (AAP)', 'US', 'reseaux-de-fact-checking'],
            ['africacheck.org', 'Africa Check', 'Africa', 'reseaux-de-fact-checking'],
            ['allsides.com', 'AllSides', 'US', 'reseaux-de-fact-checking'],
            ['altnews.in', 'Alt News', 'Africa', 'reseaux-de-fact-checking'],
            ['aosfatos.org', 'Aos Fatos', 'Brazil', 'reseaux-de-fact-checking'],
            ['apnews.com', 'Associated Press Fact Check', 'US', 'reseaux-de-fact-checking'],
            ['apublica.org', 'Agência Pública', 'Brazil', 'reseaux-de-fact-checking'],
            ['badona.info', 'Badona', 'Bénin', 'reseaux-de-fact-checking'],
            ['balobakicheck.com', 'Balobaki Check', 'République Démocratiqu..', 'reseaux-de-fact-checking'],
            ['bbc.com', 'BBC Verify', 'UK', 'reseaux-de-fact-checking'],
            ['benbere.org', 'Benbereverif', 'Mali', 'reseaux-de-fact-checking'],
            ['boomlive.in', 'BOOM', 'India', 'reseaux-de-fact-checking'],
            ['br.de', 'FaktenFuchs', 'Germany', 'reseaux-de-fact-checking'],
            ['cestvraica.com', 'C\'est vrai ça', 'France', 'reseaux-de-fact-checking'],
            ['checkfirst.network', 'CheckFirst', 'EU', 'reseaux-de-fact-checking'],
            ['chequeado.com', 'Chequeado', 'Argentina', 'reseaux-de-fact-checking'],
            ['civilnet.am', 'CivilNet', 'Armenia', 'reseaux-de-fact-checking'],
            ['climatefeedback.org', 'Climate Feedback', 'International', 'reseaux-de-fact-checking'],
            ['congocheck.net', 'CongoCheck', 'Afrique centrale', 'reseaux-de-fact-checking'],
            ['data-check.org', 'DataCheckOrg', 'Cameroun', 'reseaux-de-fact-checking'],
            ['datacameroon.com', 'Datacameroon', 'Cameroun', 'reseaux-de-fact-checking'],
            ['demagog.cz', 'Demagog.cz', 'Czech Republic', 'reseaux-de-fact-checking'],
            ['dfrlab.org', 'Lab DFR', 'Global', 'reseaux-de-fact-checking'],
            ['disinfo.africa', 'DisinfoAfrica', 'Africa', 'reseaux-de-fact-checking'],
            ['dpa-factchecking.co', 'Factchecking Deutsche Presse-Agentur', 'Germany', 'reseaux-de-fact-checking'],
            ['dubawa.org', 'Dubawa', 'India', 'reseaux-de-fact-checking'],
            ['eburnietoday.com', 'Eburnie Today', 'Côte d\'Ivoire', 'reseaux-de-fact-checking'],
            ['edmo.eu', 'EDMO (European Digital Media Observa', 'EU', 'reseaux-de-fact-checking'],
            ['elezafact.cd', 'Eleza Fact', 'République Démocratiqu..', 'reseaux-de-fact-checking'],
            ['en.tfc-taiwan.org.tw', 'Taiwan FactCheck Center', 'Taiwan', 'reseaux-de-fact-checking'],
            ['euvsdisinfo.eu', 'EUVSDESINFO', 'EU', 'reseaux-de-fact-checking'],
            ['fact-watch.org', 'Fact Watch', 'Bangladesh', 'reseaux-de-fact-checking'],
            ['factcheck.afp.com', 'AFP Fact Check', 'France / International', 'reseaux-de-fact-checking'],
            ['factcheck.ge', 'FactCheck Georgia', 'Georgia', 'reseaux-de-fact-checking'],
            ['factcheck.kz', 'FactCheck.kz', 'Kazakhstan', 'reseaux-de-fact-checking'],
            ['factcheck.org', 'http://factcheck.org/', 'US', 'reseaux-de-fact-checking'],
            ['factcheckafrica.net', 'FactCheck Africa', 'Nigeria', 'reseaux-de-fact-checking'],
            ['factcheckni.org', 'FactCheckNI', 'Northern Ireland', 'reseaux-de-fact-checking'],
            ['factcheckzw.org', 'Fact Check Zimbabwe', 'Zimbabwe', 'reseaux-de-fact-checking'],
            ['factly.in', 'Factly', 'India', 'reseaux-de-fact-checking'],
            ['factoscope.fr', 'Factoscope', 'Francophonie', 'reseaux-de-fact-checking'],
            ['factuel.afp.com', 'AFP Factuel', 'France / International', 'reseaux-de-fact-checking'],
            ['fatabyyano.net', 'Fatabyyano', 'Monde arabe', 'reseaux-de-fact-checking'],
            ['france24.com', 'France 24 - Truth or Fake', 'France', 'reseaux-de-fact-checking'],
            ['francetvinfo.fr', 'France Info - Vrai ou Fake', 'France', 'reseaux-de-fact-checking'],
            ['fullfact.org', 'Full Fact', 'UK', 'reseaux-de-fact-checking'],
            ['healthfeedback.org', 'Health Feedback', 'International', 'reseaux-de-fact-checking'],
            ['ici.radio-canada.ca', 'Décrypteurs Radio-Canada', 'Canada', 'reseaux-de-fact-checking'],
            ['infodemiology.com', 'Infodemiology', 'International', 'reseaux-de-fact-checking'],
            ['intox-detox.fr', 'Intox-detox AxaPrevention', 'France', 'reseaux-de-fact-checking'],
            ['ivoirecheck.co', 'Ivoirecheck', 'Côte d\'Ivoire', 'reseaux-de-fact-checking'],
            ['lanation.bj', 'La Nation - Fact-checking', 'Bénin', 'reseaux-de-fact-checking'],
            ['leadstories.com', 'Lead stories', 'US', 'reseaux-de-fact-checking'],
            ['lefigaro.fr', 'Le Figaro - La Vérification', 'France', 'reseaux-de-fact-checking'],
            ['lejalon.com', 'MaliCheck', 'Mali', 'reseaux-de-fact-checking'],
            ['lemonde.fr', 'Le Monde - Les Décodeurs', 'France', 'reseaux-de-fact-checking'],
            ['liberation.fr', 'Libération - CheckNews', 'France', 'reseaux-de-fact-checking'],
            ['localvoicesliberia.cc', 'Local Voices Media Network', 'Liberia', 'reseaux-de-fact-checking'],
            ['localvoicesliberia.co', 'Local Voices Media Network', 'Liberia', 'reseaux-de-fact-checking'],
            ['lokutamabe.com', 'LokutaMabe', 'République Démocratiqu..', 'reseaux-de-fact-checking'],
            ['maldita.es', 'Maldita.es', 'Spain', 'reseaux-de-fact-checking'],
            ['mimikama.org', 'Mimikama', 'Austria', 'reseaux-de-fact-checking'],
            ['misbar.com', 'Misbar', 'Monde arabe', 'reseaux-de-fact-checking'],
            ['mygopen.com', 'MyGoPen', 'Taiwan', 'reseaux-de-fact-checking'],
            ['mythdetector.com', 'Myth Detector', 'Georgia', 'reseaux-de-fact-checking'],
            ['naturenews.africa', 'NatureNews Africa - Fact-check', 'Africa', 'reseaux-de-fact-checking'],
            ['ouestaf.com', 'Vérifacts par Ouestaf', 'West Africa', 'reseaux-de-fact-checking'],
            ['ovd.info', 'OVD-Info', 'Russia', 'reseaux-de-fact-checking'],
            ['pesacheck.org', 'PesaCheck', 'West Africa', 'reseaux-de-fact-checking'],
            ['politifact.com', 'PolitiFact', 'US', 'reseaux-de-fact-checking'],
            ['poynter.org', 'Poynter 50', 'US', 'reseaux-de-fact-checking'],
            ['pravda.org.pl', 'Pravda', 'Pologne', 'reseaux-de-fact-checking'],
            ['premiumtimesng.co', 'Premium Times Nigeria', 'Nigeria', 'reseaux-de-fact-checking'],
            ['presse.inserm.fr', 'Inserm : Canal Detox', 'France', 'reseaux-de-fact-checking'],
            ['raskrinkavanje.ba', 'Raskrinkavanje', 'Bosnie', 'reseaux-de-fact-checking'],
            ['reuters.com', 'Reuters-FactCheck', 'International', 'reseaux-de-fact-checking'],
            ['sfpt-fr.org', 'SFPT (Pharmacologie et Thérapeutique)', 'France', 'reseaux-de-fact-checking'],
            ['snopes.com', 'Snopes', 'US', 'reseaux-de-fact-checking'],
            ['tamamedia.fr', 'Les Vérificateurs de Tama Média', 'West Africa', 'reseaux-de-fact-checking'],
            ['teyit.org', 'Teyit', 'Turkey', 'reseaux-de-fact-checking'],
            ['tf1info.fr', 'TF1 - Les Vérificateurs', 'France', 'reseaux-de-fact-checking'],
            ['tfc-taiwan.org.tw', 'Taiwan FactCheck Center', 'Taiwan', 'reseaux-de-fact-checking'],
            ['tsmliberia.com', 'The Stage Media', 'Turkey', 'reseaux-de-fact-checking'],
            ['un.org', 'Nations Unies - Faits concernant le clim', 'International', 'reseaux-de-fact-checking'],
            ['verafiles.org', 'Verafiles', 'Philippines', 'reseaux-de-fact-checking'],
            ['verificat.cat', 'Verificat', 'Spain', 'reseaux-de-fact-checking'],
            ['who.int', 'OMS / WHO', 'International', 'reseaux-de-fact-checking'],

            // — Publication de référence → publications-de-reference
            ['abc.es', 'ABC', 'Espagne', 'publications-de-reference'],
            ['abc.net.au', 'ABC News Australia', 'Australie', 'publications-de-reference'],
            ['afr.com', 'The Australian Financial Review', 'Australie', 'publications-de-reference'],
            ['africaguinee.com', 'Africaguinee', 'Guinée', 'publications-de-reference'],
            ['africanews.com', 'Africa News', 'Afrique', 'publications-de-reference'],
            ['afrique.latribune.fr', 'La Tribune Afrique', 'Afrique', 'publications-de-reference'],
            ['agenceecofin.com', 'Agence Ecofin', 'Afrique', 'publications-de-reference'],
            ['agpguinee.com', 'AGP', 'Guinée', 'publications-de-reference'],
            ['aljazeera.com', 'Al Jazeera English', 'International', 'publications-de-reference'],
            ['antiguaobserver.com', 'The Daily Observer', 'Anglais', 'publications-de-reference'],
            ['arabnews.com', 'Arab News', 'Arabie Saoudite', 'publications-de-reference'],
            ['aravot.am', 'Aravot', 'Arménien', 'publications-de-reference'],
            ['avaz.ba', 'Dnevni Avaz', 'Bosnien', 'publications-de-reference'],
            ['axios.com', 'Axios', 'US', 'publications-de-reference'],
            ['azadliq.org', 'Azadliq', 'Azerbaïdjanais', 'publications-de-reference'],
            ['azatutyun.am', 'Azatutyun', 'Arménien', 'publications-de-reference'],
            ['bangkokpost.com', 'Bangkok Post', 'Thaïlande', 'publications-de-reference'],
            ['banouto.bj', 'Banouto.bj', 'Benin', 'publications-de-reference'],
            ['beninintelligent.bj', 'Bénin Intelligent', 'Benin', 'publications-de-reference'],
            ['bipradio.com', 'BipRadio', 'Benin', 'publications-de-reference'],
            ['bloomberg.com', 'Bloomberg', 'US', 'publications-de-reference'],
            ['busqueda.com.uy', 'Búsqueda', 'Espagnol', 'publications-de-reference'],
            ['canberratimes.com.au', 'The Canberra Times', 'Australie', 'publications-de-reference'],
            ['capital.fr', 'Capital', 'France', 'publications-de-reference'],
            ['cbc.ca', 'CBC News', 'Canada', 'publications-de-reference'],
            ['challenges.fr', 'Challenges', 'France', 'publications-de-reference'],
            ['channelnewsasia.com', 'Channel NewsAsia', 'Singapour', 'publications-de-reference'],
            ['cieletespace.fr', 'Ciel & Espace', 'France', 'publications-de-reference'],
            ['clarin.com', 'Clarín', 'Espagnol', 'publications-de-reference'],
            ['cnn.com', 'CNN', 'US', 'publications-de-reference'],
            ['cnnbrasil.com.br', 'CNN Brasil', 'Brazil', 'publications-de-reference'],
            ['cooperativa.cl', 'Radio Cooperativa', 'Espagnol', 'publications-de-reference'],
            ['corriere.it', 'Corriere della Sera', 'Italie', 'publications-de-reference'],
            ['courrierinternational.com', 'Courrier International', 'France', 'publications-de-reference'],
            ['cyprus-mail.com', 'Cyprus Mail', 'Cyprus', 'publications-de-reference'],
            ['dailypioneer.com', 'The Pioneer', 'Inde', 'publications-de-reference'],
            ['dailystar.com.lb', 'The Daily Star Lebanon', 'Liban', 'publications-de-reference'],
            ['dailytrust.com', 'Daily Trust', 'Nigeria', 'publications-de-reference'],
            ['dawn.com', 'Dawn Pakistan', 'Pakistan', 'publications-de-reference'],
            ['derstandard.at', 'Der Standard', 'Allemand', 'publications-de-reference'],
            ['df.cl', 'Diario Financiero', 'Espagnol', 'publications-de-reference'],
            ['dna.fr', 'Dernières Nouvelles d\'Alsace (DNA)', 'France', 'publications-de-reference'],
            ['dnevnik.bg', 'Dnevnik', 'Bulgare', 'publications-de-reference'],
            ['economist.com', 'The Economist', 'International', 'publications-de-reference'],
            ['egyptindependent.com', 'Egypt Independent', 'Égypte', 'publications-de-reference'],
            ['elcomercio.com', 'El Comercio', 'Espagnol', 'publications-de-reference'],
            ['elespectador.com', 'El Espectador', 'Espagnol', 'publications-de-reference'],
            ['elmercurio.cl', 'El Mercurio', 'Espagnol', 'publications-de-reference'],
            ['elmundo.es', 'El Mundo', 'Espagne', 'publications-de-reference'],
            ['elpais.com', 'El País', 'Espagne', 'publications-de-reference'],
            ['elpais.com.uy', 'El País', 'Espagnol', 'publications-de-reference'],
            ['eltiempo.com', 'El Tiempo', 'Espagnol', 'publications-de-reference'],
            ['en.yna.co.kr', 'Yonhap News', 'Corée du Sud', 'publications-de-reference'],
            ['epsiloon.com', 'Epsiloon', 'France', 'publications-de-reference'],
            ['estadao.com.br', 'Estadão', 'Brazil', 'publications-de-reference'],
            ['estrepublicain.fr', 'L\'Est Républicain', 'France', 'publications-de-reference'],
            ['euronews.com', 'Euronews', 'Europe', 'publications-de-reference'],
            ['evnreport.com', 'EVN Report', 'Arménien', 'publications-de-reference'],
            ['excelsior.com.mx', 'Excélsior', 'Espagnol', 'publications-de-reference'],
            ['exit.al', 'theExit', 'France', 'publications-de-reference'],
            ['factor.am', 'Factor.am', 'Arménien', 'publications-de-reference'],
            ['faz.net', 'Frankfurter Allgemeine Zeitung', 'Allemagne', 'publications-de-reference'],
            ['fd.nl', 'Het Financieele Dagblad', 'Pays-Bas', 'publications-de-reference'],
            ['fijisun.com.fj', 'Fiji Sun', 'Fidji', 'publications-de-reference'],
            ['fijitimes.com', 'The Fiji Times', 'Fidji', 'publications-de-reference'],
            ['financialafrik.com', 'Financial Afrik', 'Afrique', 'publications-de-reference'],
            ['folha.uol.com.br', 'Folha de S.Paulo', 'Brazil', 'publications-de-reference'],
            ['france3-regions.francetvinfo.fr', 'France 3 régions (Franceinfo)', 'France', 'publications-de-reference'],
            ['ft.com', 'Financial Times', 'Royaume-Uni', 'publications-de-reference'],
            ['geo.fr', 'Geo', 'International', 'publications-de-reference'],
            ['geo.tv', 'Geo News', 'Pakistan', 'publications-de-reference'],
            ['globo.com', 'O Globo', 'Brazil', 'publications-de-reference'],
            ['guinee360.com', 'Guinee360', 'Guinée', 'publications-de-reference'],
            ['guinee7.com', 'Guinee7', 'Guinée', 'publications-de-reference'],
            ['guineematin.com', 'GuinéeMatin', 'Guinée', 'publications-de-reference'],
            ['guineenews.org', 'GuineeNews', 'Guinée', 'publications-de-reference'],
            ['gulfnews.com', 'Gulf News', 'Émirats Arabes Unis', 'publications-de-reference'],
            ['haaretz.com', 'Haaretz English', 'Israël', 'publications-de-reference'],
            ['haiti24.net', 'Haiti 24', 'Haïti', 'publications-de-reference'],
            ['haitilibre.com', 'Haiti Libre', 'Haïti', 'publications-de-reference'],
            ['herald.co.zw', 'The Herald Zimbabwe', 'Zimbabwe', 'publications-de-reference'],
            ['heraldsun.com.au', 'Herald Sun', 'Australie', 'publications-de-reference'],
            ['hetq.am', 'Hetq', 'Arménien', 'publications-de-reference'],
            ['hindustantimes.com', 'Hindustan Times', 'Inde', 'publications-de-reference'],
            ['hirondelle-tchad.org', 'Studio Hirondelle Tchad', 'Afrique', 'publications-de-reference'],
            ['horizon-news.net', 'Horizon News', 'Afrique', 'publications-de-reference'],
            ['huffpost.com', 'HuffPost', 'US', 'publications-de-reference'],
            ['idnes.cz', 'Mladá fronta Dnes', 'Tchèque', 'publications-de-reference'],
            ['ilmessaggero.it', 'Il Messaggero', 'Italie', 'publications-de-reference'],
            ['ilsole24ore.com', 'Il Sole 24 Ore', 'Italie', 'publications-de-reference'],
            ['independent.co.uk', 'Independent', 'Royaume-Uni', 'publications-de-reference'],
            ['indianexpress.com', 'The Indian Express', 'Inde', 'publications-de-reference'],
            ['indiatoday.in', 'India Today', 'Inde', 'publications-de-reference'],
            ['inquirer.net', 'Philippine Daily Inquirer', 'Philippines', 'publications-de-reference'],
            ['intercept.com.br', 'Intercept Brasil', 'Brazil', 'publications-de-reference'],
            ['irishexaminer.com', 'Irish Examiner', 'Irlande', 'publications-de-reference'],
            ['irishtimes.com', 'Irish Times', 'Irlande', 'publications-de-reference'],
            ['iwacu-burundi.org', 'Iwacu', 'Kirundi', 'publications-de-reference'],
            ['jamaica-gleaner.com', 'The Gleaner', 'Jamaïque', 'publications-de-reference'],
            ['jamaicaobserver.com', 'Jamaica Observer', 'Jamaïque', 'publications-de-reference'],
            ['japantimes.co.jp', 'The Japan Times', 'Japon', 'publications-de-reference'],
            ['jeuneafrique.com', 'Jeune Afrique', 'Afrique', 'publications-de-reference'],
            ['jordantimes.com', 'Jordan Times', 'Jordanie', 'publications-de-reference'],
            ['jornada.com.mx', 'La Jornada', 'Espagnol', 'publications-de-reference'],
            ['justiceinfo.net', 'Justice Info', 'International', 'publications-de-reference'],
            ['jutarnji.hr', 'Jutarnji List', 'Croate', 'publications-de-reference'],
            ['kaieteurnewsonline.com', 'Kaieteur News', 'Guyana', 'publications-de-reference'],
            ['kathmandupost.com', 'Kathmandu Post', 'Népal', 'publications-de-reference'],
            ['khaleejtimes.com', 'Khaleej Times', 'Émirats Arabes Unis', 'publications-de-reference'],
            ['koreaherald.com', 'The Korea Herald', 'Corée du Sud', 'publications-de-reference'],
            ['koreatimes.co.kr', 'The Korea Times', 'Corée du Sud', 'publications-de-reference'],
            ['kuenselonline.com', 'Kuensel', 'Dzongkha', 'publications-de-reference'],
            ['la-croix.com', 'La Croix', 'France', 'publications-de-reference'],
            ['la-razon.com', 'La Razón', 'Espagnol', 'publications-de-reference'],
            ['ladepeche.pf', 'La Dépêche de Tahiti', 'Polynésie française', 'publications-de-reference'],
            ['laliberte.ch', 'La Liberté', 'Suisse', 'publications-de-reference'],
            ['lalibre.be', 'La Libre Belgique', 'Belgique', 'publications-de-reference'],
            ['lalsace.fr', 'L\'Alsace', 'France', 'publications-de-reference'],
            ['laprovence.com', 'La Provence', 'France', 'publications-de-reference'],
            ['larecherche.fr', 'La recherche', 'France', 'publications-de-reference'],
            ['larepublica.co', 'La República', 'Espagnol', 'publications-de-reference'],
            ['larepublica.pe', 'La República', 'Espagnol', 'publications-de-reference'],
            ['lastampa.it', 'La Stampa', 'Italie', 'publications-de-reference'],
            ['latimes.com', 'Los Angeles Times', 'US', 'publications-de-reference'],
            ['latribune.fr', 'La Tribune', 'France', 'publications-de-reference'],
            ['lavanguardia.com', 'La Vanguardia', 'Espagne', 'publications-de-reference'],
            ['lavenir.net', 'L\'Avenir', 'Belgique', 'publications-de-reference'],
            ['lavoixdunord.fr', 'La Voix du Nord', 'France', 'publications-de-reference'],
            ['lci.fr', 'LCI', 'France', 'publications-de-reference'],
            ['leconomistebenin.bj', 'L\'Économiste du Bénin', 'Benin', 'publications-de-reference'],
            ['ledauphine.com', 'Le Dauphiné Libéré', 'France', 'publications-de-reference'],
            ['ledevoir.com', 'Le Devoir', 'Canada', 'publications-de-reference'],
            ['legrandcontinent.eu', 'Le Grand Continent', 'France', 'publications-de-reference'],
            ['lejournaldemayotte.com', 'Le Journal de Mayotte', 'Mayotte', 'publications-de-reference'],
            ['lematin.ch', 'Le Matin', 'Suisse', 'publications-de-reference'],
            ['lemauricien.com', 'Le Mauricien', 'Maurice', 'publications-de-reference'],
            ['lemediacitoyen.com', 'Le Média Citoyen', 'Senegal', 'publications-de-reference'],
            ['lemessager.fr', 'Le Messager', 'France', 'publications-de-reference'],
            ['lenouvelliste.com', 'Le Nouvelliste', 'Haïti', 'publications-de-reference'],
            ['leparisien.fr', 'Le Parisien', 'France', 'publications-de-reference'],
            ['lepoint.fr', 'Le Point', 'France', 'publications-de-reference'],
            ['leprogres.fr', 'Le Progrès', 'France', 'publications-de-reference'],
            ['lesechos.fr', 'Les Échos', 'France', 'publications-de-reference'],
            ['lesoir.be', 'Le Soir', 'Belgium', 'publications-de-reference'],
            ['letemps.ch', 'Le Temps', 'Suisse', 'publications-de-reference'],
            ['letudiant.fr', 'L’Étudiant', 'France', 'publications-de-reference'],
            ['lexpress.fr', 'L’Express', 'France', 'publications-de-reference'],
            ['lexpress.mu', 'L\'Express Maurice', 'Maurice', 'publications-de-reference'],
            ['localvoicesliberia.com', 'Local Voices Liberia', 'Liberia', 'publications-de-reference'],
            ['loopnews.com', 'Loop News Caribbean', 'Caraïbes', 'publications-de-reference'],
            ['lopinion.fr', 'L\'Opinion', 'France', 'publications-de-reference'],
            ['lorientlejour.com', 'L\'Orient-Le Jour', 'Liban', 'publications-de-reference'],
            ['macaubusiness.com', 'Macau Business', 'Macao', 'publications-de-reference'],
            ['marianne.net', 'Marianne', 'France', 'publications-de-reference'],
            ['martinique.franceantilles.fr', 'France-Antilles', 'Martinique', 'publications-de-reference'],
            ['mediacites.fr', 'Mediacités', 'France', 'publications-de-reference'],
            ['mediapart.fr', 'Médiapart', 'France', 'publications-de-reference'],
            ['mg.co.za', 'Mail & Guardian', 'Afrique du Sud', 'publications-de-reference'],
            ['milenio.com', 'Milenio', 'Espagnol', 'publications-de-reference'],
            ['monitor.co.ug', 'Daily Monitor', 'Ouganda', 'publications-de-reference'],
            ['moroccoworldnews.com', 'Morocco World News', 'Maroc', 'publications-de-reference'],
            ['nacion.com', 'La Nación', 'Espagnol', 'publications-de-reference'],
            ['nation.africa', 'Daily Nation', 'Kenya', 'publications-de-reference'],
            ['nationalpost.com', 'National Post', 'Canada', 'publications-de-reference'],
            ['nationnews.com', 'Nation News', 'Barbade', 'publications-de-reference'],
            ['nationthailand.com', 'The Nation Thailand', 'Thaïlande', 'publications-de-reference'],
            ['nautil.us', 'Nautilus', 'US', 'publications-de-reference'],
            ['nbcnews.com', 'NBC News', 'US', 'publications-de-reference'],
            ['newscientist.com', 'New Scientist', 'Royaume-Uni', 'publications-de-reference'],
            ['newsweek.com', 'Newsweek', 'US', 'publications-de-reference'],
            ['newtimes.co.rw', 'The New Times', 'Rwanda', 'publications-de-reference'],
            ['nexojornal.com.br', 'Nexo Jornal', 'Brazil', 'publications-de-reference'],
            ['next.ink', 'Next', 'France', 'publications-de-reference'],
            ['nhk.or.jp', 'NHK World', 'Japon', 'publications-de-reference'],
            ['nieuwsblad.be', 'Het Nieuwsblad', 'Belgique', 'publications-de-reference'],
            ['nouvelobs.com', 'Le Nouvel Obs', 'France', 'publications-de-reference'],
            ['nrc.nl', 'NRC Handelsblad', 'Pays-Bas', 'publications-de-reference'],
            ['nytimes.com', 'The New York Times', 'US', 'publications-de-reference'],
            ['ouest-france.fr', 'Ouest-France', 'France', 'publications-de-reference'],
            ['politico.com', 'Politico', 'US', 'publications-de-reference'],
            ['politiken.dk', 'Politiken', 'Danois', 'publications-de-reference'],
            ['popsci.com', 'Popular Science', 'US', 'publications-de-reference'],
            ['postimees.ee', 'Postimees', 'Estonien', 'publications-de-reference'],
            ['pourlascience.fr', 'Pour la science', 'France', 'publications-de-reference'],
            ['premiumtimesng.com', 'Premium Times', 'Nigeria', 'publications-de-reference'],
            ['punchng.com', 'The Punch', 'Nigeria', 'publications-de-reference'],
            ['rappler.com', 'Rappler', 'Philippines', 'publications-de-reference'],
            ['repubblica.it', 'La Repubblica', 'Italie', 'publications-de-reference'],
            ['rfi.fr', 'RFI', 'France', 'publications-de-reference'],
            ['rpp.pe', 'RPP', 'Espagnol', 'publications-de-reference'],
            ['rtbf.be', 'Faky-RTBF', 'Belgique', 'publications-de-reference'],
            ['saharareporters.com', 'Sahara Reporters', 'Nigeria', 'publications-de-reference'],
            ['samoaobserver.ws', 'Samoa Observer', 'Samoa', 'publications-de-reference'],
            ['sciencenews.org', 'Science News', 'US', 'publications-de-reference'],
            ['sciencesetavenir.fr', 'Sciences & avenir', 'France', 'publications-de-reference'],
            ['scientificamerican.com', 'Scientific American', 'US', 'publications-de-reference'],
            ['scmp.com', 'South China Morning Post', 'Hong Kong', 'publications-de-reference'],
            ['scotsman.com', 'The Scotsman', 'Écosse', 'publications-de-reference'],
            ['senenews.com', 'SeneNews', 'Senegal', 'publications-de-reference'],
            ['sidwaya.info', 'Sidwaya', 'Burkina Faso', 'publications-de-reference'],
            ['smh.com.au', 'The Sydney Morning Herald', 'Australie', 'publications-de-reference'],
            ['solomontimes.com', 'The Solomon Times', 'Îles Salomon', 'publications-de-reference'],
            ['space.com', 'Space', 'US', 'publications-de-reference'],
            ['spiegel.de', 'Der Spiegel', 'Allemagne', 'publications-de-reference'],
            ['srtb.bj', 'SRTB', 'Benin', 'publications-de-reference'],
            ['stabroeknews.com', 'Stabroek News', 'Guyana', 'publications-de-reference'],
            ['standardmedia.co.ke', 'The Standard Kenya', 'Kenya', 'publications-de-reference'],
            ['straitstimes.com', 'Straits Times', 'Singapour', 'publications-de-reference'],
            ['studiosifaka.org', 'Studio Sifaka', 'Afrique', 'publications-de-reference'],
            ['studiotamani.org', 'Studio Tamani', 'Mali', 'publications-de-reference'],
            ['studioyafa.bf', 'Studio Yafa', 'Burkina Faso', 'publications-de-reference'],
            ['sudantribune.com', 'Sudan Tribune', 'Soudan', 'publications-de-reference'],
            ['sudouest.fr', 'Sud-Ouest', 'France', 'publications-de-reference'],
            ['sudquotidien.sn', 'Sud Quotidien', 'Senegal', 'publications-de-reference'],
            ['sueddeutsche.de', 'Süddeutsche Zeitung', 'Allemagne', 'publications-de-reference'],
            ['swissinfo.ch', 'Swissinfo', 'Suisse', 'publications-de-reference'],
            ['tahiti-infos.com', 'Tahiti Infos', 'Polynésie française', 'publications-de-reference'],
            ['talcualdigital.com', 'Tal Cual', 'Espagnol', 'publications-de-reference'],
            ['tdg.ch', 'Tribune de Genève', 'Suisse', 'publications-de-reference'],
            ['telegraaf.nl', 'De Telegraaf', 'Pays-Bas', 'publications-de-reference'],
            ['telegraph.co.uk', 'The Telegraph', 'Royaume-Uni', 'publications-de-reference'],
            ['telerama.fr', 'Télérama', 'France', 'publications-de-reference'],
            ['telescopemag.fr', 'telescopemag', 'France', 'publications-de-reference'],
            ['the-star.co.ke', 'The Star Kenya', 'Kenya', 'publications-de-reference'],
            ['thearabweekly.com', 'Arab Weekly', 'Moyen-Orient', 'publications-de-reference'],
            ['theaustralian.com.au', 'The Australian', 'Australie', 'publications-de-reference'],
            ['thecitizen.co.tz', 'The Citizen Tanzania', 'Tanzanie', 'publications-de-reference'],
            ['thedailystar.net', 'The Daily Star', 'Bangladesh', 'publications-de-reference'],
            ['theeastafrican.co.ke', 'The EastAfrican', 'Afrique de l\'Est', 'publications-de-reference'],
            ['theglobeandmail.com', 'Globe and Mail', 'Canada', 'publications-de-reference'],
            ['theguardian.com', 'The Guardian', 'Royaume-Uni', 'publications-de-reference'],
            ['thehimalayantimes.com', 'The Himalayan Times', 'Népal', 'publications-de-reference'],
            ['thejournal.ie', 'The Journal', 'Irlande', 'publications-de-reference'],
            ['thenassauguardian.com', 'The Nassau Guardian', 'Bahamas', 'publications-de-reference'],
            ['thenational.scot', 'The National', 'Écosse', 'publications-de-reference'],
            ['thenews.com.pk', 'The News International', 'Pakistan', 'publications-de-reference'],
            ['thepeninsulaqatar.com', 'The Peninsula Qatar', 'Qatar', 'publications-de-reference'],
            ['thestandard.com.hk', 'The Standard', 'Hong Kong', 'publications-de-reference'],
            ['thestar.com', 'Toronto Star', 'Canada', 'publications-de-reference'],
            ['thestar.com.my', 'The Star Malaysia', 'Malaisie', 'publications-de-reference'],
            ['thetimes.co.uk', 'The Times', 'Royaume-Uni', 'publications-de-reference'],
            ['theverge.com', 'The Verge', 'US', 'publications-de-reference'],
            ['timeskuwait.com', 'The Times Kuwait', 'Koweït', 'publications-de-reference'],
            ['timesofindia.com', 'The Times of India', 'Inde', 'publications-de-reference'],
            ['timesofmalta.com', 'The Times of Malta', 'Malte', 'publications-de-reference'],
            ['tribune.com.pk', 'The Express Tribune', 'Pakistan', 'publications-de-reference'],
            ['tribune242.com', 'The Tribune', 'Bahamas', 'publications-de-reference'],
            ['tribuneindia.com', 'Tribune India', 'Inde', 'publications-de-reference'],
            ['trinidadexpress.com', 'Trinidad Express', 'Trinité-et-Tobago', 'publications-de-reference'],
            ['trustmyscience.com', 'Trust My Science', 'France', 'publications-de-reference'],
            ['tv5monde.com', 'TV5Monde', 'France', 'publications-de-reference'],
            ['usatoday.com', 'USA Today', 'US', 'publications-de-reference'],
            ['valor.globo.com', 'Valor Econômico', 'Brazil', 'publications-de-reference'],
            ['vanguardngr.com', 'Vanguard Nigeria', 'Nigeria', 'publications-de-reference'],
            ['veja.com.br', 'Veja', 'Brazil', 'publications-de-reference'],
            ['vietnamnews.vn', 'Viet Nam News', 'Vietnam', 'publications-de-reference'],
            ['visionguinee.info', 'Vision Guinée', 'Guinée', 'publications-de-reference'],
            ['volkskrant.nl', 'Volkskrant', 'Pays-Bas', 'publications-de-reference'],
            ['vox.com', 'Vox', 'US', 'publications-de-reference'],
            ['voxeurop.eu', 'VoxEurop (en français)', 'Europe', 'publications-de-reference'],
            ['washingtonpost.com', 'The Washington Post', 'US', 'publications-de-reference'],
            ['wired.com', 'Wired', 'US', 'publications-de-reference'],
            ['wsj.com', 'The Wall Street Journal', 'US', 'publications-de-reference'],
            ['zeit.de', 'Die Zeit', 'Allemagne', 'publications-de-reference'],
            ['zinfos974.com', 'Zinfos974', 'La Réunion', 'publications-de-reference'],

            // — Source experte → sources-expertes
            ['anses.fr', 'ANSES- Agence nationale de sécurité sa', 'France', 'sources-expertes'],
            ['ansm.sante.fr', 'ANSM-Agence nationale de sécurité du', 'France', 'sources-expertes'],
            ['auschwitz.org', 'Stop Denial (Mémorial d\'Auschwitz)', 'Pologne', 'sources-expertes'],
            ['citizen4science.org', 'Citizen4Science', 'France', 'sources-expertes'],
            ['cjr.org', 'Columbia Journalism Review', 'US', 'sources-expertes'],
            ['clepsy.fr', 'CléPsy', 'France', 'sources-expertes'],
            ['climate.us', 'Climate.us', 'US', 'sources-expertes'],
            ['corpwatchers.eu', 'European Network of Corporate Observ', 'International', 'sources-expertes'],
            ['ec.europa.eu', 'Eurostat', 'EU', 'sources-expertes'],
            ['has-sante.fr', 'Haute Autorité de Santé', 'France', 'sources-expertes'],
            ['hdr.undp.org', 'PNUD - Rapport sur le développement l', 'International', 'sources-expertes'],
            ['heart.org', 'American Heart Association', 'US', 'sources-expertes'],
            ['inegalites.fr', 'Observatoire des inégalités', 'France', 'sources-expertes'],
            ['info-res.org', 'Centre for Information Resilience (CIR)', 'UK-international', 'sources-expertes'],
            ['inrae.fr', 'INRAE', 'France', 'sources-expertes'],
            ['insee.fr', 'INSEE', 'France', 'sources-expertes'],
            ['inserm.fr', 'Magazine de l\'Inserm', 'France', 'sources-expertes'],
            ['interstices.info', 'Interstices.info', 'France', 'sources-expertes'],
            ['ipbes.net', 'IPBES-Plateforme intergouvernemental', 'International', 'sources-expertes'],
            ['kyoto-u.ac.jp', 'Université de Kyoto (research news)', 'International', 'sources-expertes'],
            ['larevuedesmedias.ina.fr', 'La Revue des médias (INA)', 'France', 'sources-expertes'],
            ['leblob.fr', 'Le Blob (Universcience)', 'France', 'sources-expertes'],
            ['lejournal.cnrs.fr', 'CNRS Le Journal', 'France', 'sources-expertes'],
            ['medi-sphere.be', 'MediSphere', 'EU', 'sources-expertes'],
            ['mnhn.fr', 'Muséum national d\'Histoire naturelle', 'France', 'sources-expertes'],
            ['msdmanuals.com', 'Manuels MSD', 'US-international', 'sources-expertes'],
            ['nature.com', 'Nature', 'UK-international', 'sources-expertes'],
            ['news.cnrs.fr', 'CNRS News (édition anglaise)', 'France', 'sources-expertes'],
            ['ourworldindata.org', 'Our World in data OWID', 'International', 'sources-expertes'],
            ['pharmacomedicale.org', 'Collège National de Pharmacologie Mé', 'France', 'sources-expertes'],
            ['publications.aap.org', 'AAP Publications (AAP News)', 'US', 'sources-expertes'],
            ['publichealth.jhu.edu', 'Johns Hopkins School of Public Health', 'US', 'sources-expertes'],
            ['sante.fr', 'santé.fr', 'France', 'sources-expertes'],
            ['scholarpedia.org', 'Scholarpedia', 'US-international', 'sources-expertes'],
            ['science.org', 'Science', 'US-international', 'sources-expertes'],
            ['statpearls.com', 'StatPearls', 'US-international', 'sources-expertes'],
            ['tdah-france.fr', 'TDAH France', 'France', 'sources-expertes'],
            ['theconversation.com', 'The Conversation', 'International', 'sources-expertes'],
            ['undp.org', 'PNUD-UNDP', 'International', 'sources-expertes'],
            ['usafacts.org', 'USA Facts', 'US', 'sources-expertes'],        ];

        $familleParType = [
            'reseaux-de-fact-checking',
            'publications-de-reference',
            'sources-expertes',
        ];

        $poses = 0;
        $deja = 0;

        foreach ($sources as [$domaine, $nom, $region, $slugFamille]) {
            $normalise = TrustedSource::normaliserLeDomaine($domaine);

            if ($normalise === '' || ! in_array($slugFamille, $familleParType, true)) {
                continue;
            }

            // INSERTION SEULE. `firstOrCreate` regarde d'abord, écrit ensuite :
            // une fiche déjà présente garde sa crédibilité, sa famille et son
            // texte, quels qu'ils soient devenus depuis.
            $avant = TrustedSource::query()->where('domain', $normalise)->exists();

            if ($avant) {
                $deja++;

                continue;
            }

            TrustedSource::query()->create([
                'uuid' => (string) Str::uuid(),
                'source_category_id' => $familles[$slugFamille] ?? null,
                'name' => $nom,
                'domain' => $normalise,
                'url' => 'https://'.$normalise,
                'credibility' => SourceCategory::query()
                    ->where('slug', $slugFamille)
                    ->value('default_credibility') ?? 70,
                'is_active' => true,
                'notes' => 'Répertoire public de Vera, relevé le 9 septembre 2026. Région d\'origine : '.$region.'.',
            ]);

            $poses++;
        }

        $this->command?->info(sprintf(
            'Sources Vera : %d posées, %d déjà présentes et laissées intactes.',
            $poses,
            $deja,
        ));
    }
}
