<?php

declare(strict_types=1);

namespace App\Core\Marketing\Seo;

use App\Core\Marketing\Cartes\CarteDeDementi;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\HubArticle;
use App\Models\Verification;
use Illuminate\Support\Carbon;

/**
 * Fabrique les métadonnées de référencement de chaque page publique.
 *
 * ## Pourquoi un service, et pas une variable dans chaque vue
 *
 * Parce qu'une page a besoin de dix-huit balises cohérentes entre elles : le
 * `og:title` doit égaler le `twitter:title`, le `canonical` doit égaler le
 * `og:url`, et l'image doit être ABSOLUE · un chemin relatif dans un `og:image`
 * ne produit aucun aperçu, et personne ne s'en aperçoit avant le premier
 * partage WhatsApp. Dix-huit balises recopiées à la main sur vingt-deux pages,
 * c'est quatre cents occasions de se tromper une fois.
 *
 * ## D'où viennent les textes
 *
 * Des pages du prototype, une par une. Ce ne sont pas des descriptions
 * réécrites « dans le même esprit » : ce sont LES SIENNES, au caractère près.
 * Le prototype est le gabarit maître, y compris pour ce que Google affichera.
 */
final class Referencement
{
    /**
     * L'image de partage par défaut.
     *
     * Le prototype pose la même sur ses vingt-deux pages : l'annonce du Prix
     * WSIS. Ce n'est pas un oubli, c'est sa plus forte carte de visite. On la
     * reprend · en JPEG, parce que le PNG d'origine pesait douze mégaoctets.
     */
    private const IMAGE_PAR_DEFAUT = 'assets/images/blog/verifall-prix-wsis-2026-annonce.jpg';

    public function __construct(private readonly CarteDeDementi $cartes) {}

    /**
     * Titre et description de chaque page éditoriale, transposés du prototype.
     *
     * @var array<string, array{titre: string, description: string}>
     */
    private const PAGES = [
        'public.home' => [
            'titre' => "Vérif'All · Protégez-vous contre les fausses informations",
            'description' => "Vérif'All, l'application béninoise de fact-checking : vérifiez textes, images, vidéos et audios en quelques secondes et décryptez l'info.",
        ],
        'public.why' => [
            'titre' => "Pourquoi Vérif'All ?",
            'description' => "Pourquoi la vérité est devenue si fragile : la mission de Vérif'All en 3 axes, outiller, éduquer, fédérer.",
        ],
        'public.features' => [
            'titre' => 'Fonctionnalités',
            'description' => 'Toute la puissance du fact-checking à portée de main : vérification de texte, image, vidéo, audio, apprentissage, communauté et signalement.',
        ],
        'public.how' => [
            'titre' => 'Comment ça marche ?',
            'description' => 'La vérification en trois étapes : soumettez votre doute, analyse à 360° par notre Orchestrateur, obtenez un verdict clair et expliqué.',
        ],
        'public.team' => [
            'titre' => 'Notre Équipe',
            'description' => "Journalistes, fact-checkeurs, ingénieurs et pédagogues : les visages derrière la boussole Vérif'All, et le mot du fondateur René Fadonougbo.",
        ],
        'public.contact' => [
            'titre' => 'Contact',
            'description' => "Contactez l'équipe Vérif'All : partenariats, médias, questions et suggestions.",
        ],
        'public.faq' => [
            'titre' => 'Questions Fréquentes',
            'description' => "Tout ce que vous devez savoir sur Vérif'All et le Fact-checking : compte, gratuité, vérifications, certificats, mentors.",
        ],
        'public.media' => [
            'titre' => 'Médias',
            'description' => "La couverture médiatique du lancement de Vérif'All le 16 juillet 2026 au CDIJ, Cour d'Appel de Cotonou.",
        ],
        'public.legal' => [
            'titre' => 'Mentions légales',
            'description' => "Mentions légales du site verifall.com et de l'application Vérif'All, éditée par Vérif'All Lab au Bénin.",
        ],
        'public.privacy' => [
            'titre' => 'Politique de confidentialité',
            'description' => "Comment Vérif'All collecte, utilise, partage et protège vos données personnelles, conformément à la législation béninoise.",
        ],
        'public.cookies' => [
            'titre' => 'Politique des cookies',
            'description' => "Comment le site et l'application Vérif'All utilisent les cookies et comment gérer vos préférences.",
        ],
        'public.facts' => [
            'titre' => 'Actualités & analyses vérifiées',
            'description' => "Les dernières analyses de fact-checking publiées par Vérif'All : rumeurs, arnaques et images hors contexte démontées.",
        ],
        'public.blog' => [
            'titre' => 'Notre Blog',
            'description' => "Analyses, guides, formation et actualités de Vérif'All : tout sur la lutte contre la désinformation au Bénin.",
        ],
        'public.tutorials' => [
            'titre' => 'Tutoriels',
            'description' => "Les cours de fact-checking Vérif'All : introduction, fake news, investigation. Accès libre, du niveau débutant à avancé.",
        ],
        'public.verify' => [
            'titre' => 'Vérifiez avant de partager',
            'description' => "Collez un lien ou un texte pour une analyse instantanée : faux décès, faux coups d'État, faux recrutements, arnaques santé.",
        ],
        'public.verify-certificate' => [
            'titre' => 'Vérifier un certificat',
            'description' => "Vérifiez publiquement l'authenticité d'un certificat Vérif'All grâce à son identifiant unique et sa signature numérique.",
        ],

        /*
         | TRANSPARENCE. Ces trois pages ne viennent pas du prototype mais des
         | exigences du code de conduite de l'IFCN. Leurs textes sont écrits
         | pour un journaliste qui vérifie une source avant de la citer · c'est
         | son besoin, et non celui d'un moteur, qui décide de ce qu'il faut
         | annoncer : ce qu'on fait, ce qu'on ne fait pas, d'où vient l'argent.
         |
         | Aucune ne revendique une accréditation : ces pages la PRÉPARENT.
         */
        'public.corrections' => [
            'titre' => 'Journal des corrections',
            'description' => "Chaque verdict corrigé par Vérif'All reste publié avec son ancien verdict, sa date et la raison du changement. Aucune correction n'est enregistrée sans motif.",
        ],
        'public.methodology' => [
            'titre' => 'Notre méthodologie',
            'description' => "Comment Vérif'All établit un verdict : les six étapes de vérification, le poids réel de chaque moteur, les seuils qui mènent à « non vérifiable », et les trois limites que la plateforme annonce.",
        ],
        'public.funding' => [
            'titre' => 'Financement',
            'description' => "D'où viennent les ressources de Vérif'All : dotations, soutiens du projet et du lancement, recettes propres. Les montants publiés sont ceux que nous pouvons établir.",
        ],
    ];

    /** Le nom lisible de chaque page, pour le fil d'Ariane. */
    private const ETIQUETTES_FIL = [
        'public.facts' => 'Actualités',
        'public.blog' => 'Blog',
        'public.tutorials' => 'Tutoriels',
    ];

    public function pourPage(string $route): DonneesSeo
    {
        /*
        |----------------------------------------------------------------------
        | LE TITRE SUIT LA LANGUE
        |----------------------------------------------------------------------
        |
        | Il est VISIBLE : onglet du navigateur, apercu de partage sur WhatsApp,
        | resultat de recherche. Ce sont souvent les premiers mots qu'un lecteur
        | voit de la plateforme, parfois les seuls, et les laisser en francais
        | sur une page anglaise revient a accueillir quelqu'un dans une langue
        | qu'il n'a pas choisie.
        |
        | `self::PAGES` demeure : elle sert de REPLI quand une cle manque au
        | fichier de langue. Une page sans titre est pire qu'une page au titre
        | francais.
        */
        $cle = str_replace(['public.', '.', '-'], ['', '_', '_'], $route);
        $traduit = __('seo.'.$cle);

        $page = is_array($traduit) && isset($traduit['titre'], $traduit['description'])
            ? $traduit
            : (self::PAGES[$route] ?? [
                'titre' => "Vérif'All",
                'description' => "Vérif'All, la boussole contre la désinformation numérique.",
            ]);

        return new DonneesSeo(
            titre: $page['titre'],
            description: $page['description'],
            urlCanonique: route($route),
            image: asset(self::IMAGE_PAR_DEFAUT),
            filAriane: $route === 'public.home' ? [] : $this->fil([
                ['nom' => $page['titre'], 'url' => route($route)],
            ]),
        );
    }

    /**
     * Un article de blog.
     *
     * `NewsArticle` plutôt que `Article` : c'est le type que le prototype
     * emploie, et celui qui rend un contenu éligible aux modules d'actualité de
     * Google. Le `datePublished` en est la condition · un article sans date n'y
     * entre pas.
     */
    public function pourArticleDeBlog(BlogPost $article): DonneesSeo
    {
        $image = $article->cover_path !== null && $article->cover_path !== ''
            ? asset($article->cover_path)
            : asset(self::IMAGE_PAR_DEFAUT);

        $url = route('public.blog.show', $article);

        return new DonneesSeo(
            titre: $article->meta_title ?: $article->title,
            description: $article->meta_description ?: $this->extrait($article),
            urlCanonique: $url,
            image: $image,
            type: 'article',
            publieLe: $article->published_at,
            modifieLe: $article->updated_at,
            filAriane: $this->fil([
                ['nom' => 'Blog', 'url' => route('public.blog')],
                ['nom' => $article->title, 'url' => $url],
            ]),
            donneesStructurees: [[
                '@context' => 'https://schema.org',
                '@type' => 'NewsArticle',
                'headline' => $article->title,
                'image' => [$image],
                'datePublished' => $article->published_at?->toDateString(),
                'dateModified' => $article->updated_at?->toDateString(),
                'inLanguage' => 'fr',
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
                'author' => ['@type' => 'Organization', 'name' => "Vérif'All Lab"],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => "Vérif'All",
                    'logo' => ['@type' => 'ImageObject', 'url' => asset('assets/images/logo.png')],
                ],
            ]],
        );
    }

    /**
     * Une vérification publiée.
     *
     * `ClaimReview` est LA raison d'être du référencement d'un site de
     * fact-checking : c'est ce balisage qui fait apparaître le verdict
     * directement dans les résultats de recherche, et qui alimente les bases de
     * fact-checks partagées entre plateformes.
     *
     * La note se déduit du verdict, elle ne s'invente pas : `ratingValue` sur
     * une échelle de 1 à 5 avec un `alternateName` en toutes lettres, exactement
     * comme le prototype le fait pour son exemple.
     */
    /**
     * Un rapport de vérification servi en libre accès — `/rapport/{uuid}`.
     *
     * ## Le titre porte le VERDICT avant l'affirmation
     *
     * C'est le « sandwich de vérité » appliqué à l'aperçu de partage, et ce
     * n'est pas une préférence de style. Un titre qui commence par
     * « Le Président a été destitué… » propage la rumeur auprès de tous ceux
     * qui voient passer la carte dans une conversation sans l'ouvrir — c'est-à-dire
     * la plupart des gens. Répéter un mensonge pour le démentir l'ancre ; on
     * énonce donc ce qui est vrai d'abord.
     *
     * ## Et pourquoi `indexable: false`
     *
     * Ces pages ne sont pas du contenu éditorial : ce sont les demandes des
     * citoyens. Les laisser indexer ferait de chaque soumission une page
     * publique référencée, reprenant en titre l'affirmation qu'elle dément, et
     * exposerait le volume et la teneur de ce que les gens signalent. Les
     * démentis destinés au référencement sont les `HubArticle`, écrits par la
     * rédaction — voir `pourFaitVerifie()` juste en dessous.
     */
    public function pourRapportPublic(Verification $verification): DonneesSeo
    {
        $rapport = $verification->report;
        $verdict = $rapport?->verdict;

        return new DonneesSeo(
            titre: ($verdict?->libelleRapport() ?? '').' : '.$verification->apercu(90),
            description: trim(
                ($verdict?->phraseRapport() ?? '').' '.mb_substr((string) $rapport?->summary, 0, 150)
            ),
            urlCanonique: route('public.report', ['verification' => $verification->uuid]),
            image: asset(self::IMAGE_PAR_DEFAUT),
            type: 'article',
            publieLe: $rapport?->published_at,
            modifieLe: $rapport?->updated_at,
            indexable: false,
        );
    }

    public function pourFaitVerifie(HubArticle $article): DonneesSeo
    {
        $url = route('public.fact', $article);
        $note = NoteDuVerdict::depuis($article->verdict);

        /*
        |----------------------------------------------------------------------
        | L'AFFIRMATION EXAMINÉE, et le défaut grave qu'elle corrige
        |----------------------------------------------------------------------
        |
        | `claimReviewed` recevait le TITRE de l'article. Or les titres sont
        | éditoriaux : ils énoncent déjà la conclusion.
        |
        | « Aucune taxe WhatsApp n'est instaurée au Bénin », noté FAUX, se lit
        | par une machine « il est FAUX qu'aucune taxe n'existe » · soit
        | l'inverse exact du démenti. Le défaut était systématique : pour chaque
        | article de démenti, donc pour la majorité, le balisage affirmait le
        | contraire du verdict.
        |
        | Ingéré par un moteur, il aurait fait publier à Vérif'All de la
        | désinformation lisible par machine.
        |
        | On n'essaie PAS de retourner le titre par programme : « aucune taxe
        | n'est instaurée » ne se nie pas mécaniquement sans se tromper une fois
        | sur trois, et une fois sur trois est bien trop. L'affirmation telle
        | qu'elle a circulé est une donnée éditoriale, saisie à part.
        */
        $affirmation = is_string($article->claim) ? trim($article->claim) : '';

        $structure = $affirmation === '' ? null : [
            '@context' => 'https://schema.org',
            '@type' => 'ClaimReview',
            'url' => $url,
            'datePublished' => $article->published_at?->toDateString(),
            'inLanguage' => 'fr',
            'claimReviewed' => $affirmation,
            'author' => [
                '@type' => 'Organization',
                'name' => "Vérif'All",
                // L'éditeur doit être identifiable : un moteur relie les
                // vérifications d'un même auteur par cette adresse.
                'url' => route('public.home'),
            ],
            'reviewRating' => array_filter([
                '@type' => 'Rating',
                // Absente quand le verdict est « non vérifiable » : voir
                // NoteDuVerdict. Une note médiane dirait « à moitié vrai »,
                // ce qui serait une affirmation que nous n'avons pas faite.
                'ratingValue' => $note->valeur,
                'bestRating' => $note->valeur !== null ? 5 : null,
                'worstRating' => $note->valeur !== null ? 1 : null,
                'alternateName' => $note->libelle,
            ], static fn (mixed $v): bool => $v !== null),
        ];

        /*
         | `itemReviewed` · la publication EXAMINÉE, distincte de notre analyse.
         | C'est le champ qui permet à un moteur de relier notre démenti au
         | contenu qu'il dément, et donc de l'afficher à côté. Il n'est posé que
         | si la source est réellement connue : un `appearance` vide vaut moins
         | qu'aucun `appearance`.
         */
        if ($structure !== null && is_string($article->source_url) && $article->source_url !== '') {
            $structure['itemReviewed'] = [
                '@type' => 'Claim',
                'appearance' => array_filter([
                    '@type' => 'CreativeWork',
                    'url' => $article->source_url,
                    'publisher' => $article->source_name !== null && $article->source_name !== ''
                        ? ['@type' => 'Organization', 'name' => $article->source_name]
                        : null,
                ], static fn (mixed $v): bool => $v !== null),
            ];
        }

        return new DonneesSeo(
            /*
             | LA SURCHARGE SEO, honorée depuis le 2 septembre 2026.
             |
             | Cette méthode composait le titre et la description à partir des
             | seuls champs éditoriaux. `pourArticleDeBlog()` honorait déjà la
             | surcharge, pas celle-ci : un rédacteur qui remplissait le bloc
             | « Référencement » d'une actualité du Hub voyait ses valeurs
             | enregistrées et jamais employées.
             |
             | Le repli reste le comportement normal : sans surcharge, le titre
             | et le chapeau font le travail, et c'est le cas de la plupart des
             | articles.
             */
            titre: $article->meta_title ?: $article->title,
            description: $article->meta_description ?: (string) ($article->excerpt ?? ''),
            urlCanonique: $url,
            /*
             | LA CARTE DE DÉMENTI d'abord, quand elle existe.
             |
             | Au Bénin une rumeur circule en image sur WhatsApp ; un démenti
             | qui ne se partage que sous forme de lien ne circule pas à
             | parité. La carte porte le verdict en toutes lettres, donc lisible
             | sans ouvrir la page · et son URL change à chaque correction, ce
             | qui force les aperçus déjà en cache à être relus.
             |
             | À défaut : la couverture de l'article, puis l'image par défaut.
             */
            image: $this->cartes->urlPublique($article)
                ?? ($article->cover_path !== null && $article->cover_path !== ''
                    ? asset($article->cover_path)
                    : asset(self::IMAGE_PAR_DEFAUT)),
            type: 'article',
            publieLe: $article->published_at,
            modifieLe: $article->updated_at,
            filAriane: $this->fil([
                ['nom' => 'Actualités', 'url' => route('public.facts')],
                ['nom' => $article->title, 'url' => $url],
            ]),
            /*
             | AUCUN BALISAGE plutôt qu'un balisage inversé.
             |
             | Sans affirmation consignée, `$structure` vaut null et rien n'est
             | émis. La page reste parfaitement valide et perd seulement sa
             | chance de figurer dans un panneau de fact-checking · ce qui est
             | sans commune mesure avec le fait d'affirmer le contraire de son
             | propre verdict.
             */
            donneesStructurees: $structure === null ? [] : [$structure],
        );
    }

    /**
     * La fiche publique d'un cours.
     *
     * `Course` exige un `provider`. Le reste · note, nombre d'inscrits, durée —
     * n'est déclaré que si la donnée existe : un balisage qui annonce une note
     * inexistante est une fausse déclaration, et Google la sanctionne.
     */
    public function pourTutoriel(Course $cours): DonneesSeo
    {
        $url = route('public.tutorial', $cours);

        return new DonneesSeo(
            titre: $cours->title,
            description: (string) ($cours->summary ?? ''),
            urlCanonique: $url,
            image: $cours->cover_path !== null && $cours->cover_path !== ''
                ? asset($cours->cover_path)
                : asset(self::IMAGE_PAR_DEFAUT),
            filAriane: $this->fil([
                ['nom' => 'Tutoriels', 'url' => route('public.tutorials')],
                ['nom' => $cours->title, 'url' => $url],
            ]),
            donneesStructurees: [[
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $cours->title,
                'description' => (string) ($cours->summary ?? ''),
                'inLanguage' => 'fr',
                'url' => $url,
                'provider' => [
                    '@type' => 'Organization',
                    'name' => "Vérif'All",
                    'sameAs' => url('/'),
                ],
            ]],
        );
    }

    /**
     * L'identité du site, posée une fois sur l'accueil.
     *
     * `WebSite` + `SearchAction` demandent à Google d'afficher un champ de
     * recherche interne sous le résultat. `Organization` alimente le panneau
     * de connaissance latéral.
     *
     * @return list<array<string, mixed>>
     */
    public function identiteDuSite(): array
    {
        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => "Vérif'All",
                'url' => url('/'),
                'logo' => asset('assets/images/logo.png'),
                'description' => 'La boussole contre la désinformation numérique.',
                'foundingLocation' => ['@type' => 'Place', 'name' => 'Cotonou, Bénin'],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => "Vérif'All",
                'url' => url('/'),
                'inLanguage' => 'fr',
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => route('public.facts').'?recherche={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    /**
     * Préfixe un fil d'Ariane par l'accueil.
     *
     * @param  list<array{nom: string, url: string}>  $etapes
     * @return list<array{nom: string, url: string}>
     */
    private function fil(array $etapes): array
    {
        return array_merge(
            [['nom' => 'Accueil', 'url' => route('public.home')]],
            $etapes,
        );
    }

    /**
     * La description d'un article dépourvu de méta-description.
     *
     * On préfère l'`excerpt` rédigé ; à défaut, le début du corps, débarrassé
     * de son HTML. Jamais de texte inventé : ce qui n'est pas écrit ne se
     * remplace pas par une phrase plausible.
     */
    private function extrait(BlogPost $article): string
    {
        $source = $article->excerpt !== null && trim($article->excerpt) !== ''
            ? $article->excerpt
            : (string) $article->body;

        return trim(preg_replace('/\s+/', ' ', strip_tags($source)) ?? '');
    }

    public function maintenant(): Carbon
    {
        return Carbon::now();
    }
}
