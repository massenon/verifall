<?php

declare(strict_types=1);

namespace App\Core\Marketing\Seo;

use Carbon\CarbonInterface;

/**
 * Tout ce qu'une page doit dire d'elle-même aux moteurs et aux réseaux.
 *
 * Ce n'est pas une nouveauté : le prototype porte DÉJÀ, sur ses 22 pages, un
 * jeu complet — `canonical`, sept balises OpenGraph, quatre balises Twitter, et
 * un bloc JSON-LD par page (`BreadcrumbList`, `FAQPage`, `ClaimReview`,
 * `NewsArticle`, `Organization`, `WebSite`). La transposition Blade les avait
 * perdues en route : il ne restait qu'un `<title>` et une `<meta description>`.
 *
 * Un objet immuable plutôt qu'un tableau : une faute de frappe sur une clé de
 * tableau produit une balise vide qu'on ne voit jamais — elle n'apparaît que
 * le jour où quelqu'un partage la page et où l'aperçu sort blanc.
 */
final readonly class DonneesSeo
{
    /**
     * @param  list<array{nom: string, url: string}>  $filAriane
     * @param  list<array<string, mixed>>  $donneesStructurees  blocs JSON-LD additionnels
     */
    public function __construct(
        public string $titre,
        public string $description,
        public string $urlCanonique,
        public string $image,
        public string $type = 'website',
        public ?CarbonInterface $publieLe = null,
        public ?CarbonInterface $modifieLe = null,
        public array $filAriane = [],
        public array $donneesStructurees = [],
        public bool $indexable = true,
    ) {}

    /**
     * Le titre complet, suffixé de la marque.
     *
     * Le prototype suffixe systématiquement le nom du site, SAUF sur l'accueil
     * où le titre porte déjà la marque en tête. On reproduit cette règle plutôt
     * que d'ajouter aveuglément, ce qui donnerait « Vérif'All … — Vérif'All ».
     */
    public function titreComplet(): string
    {
        if (str_contains($this->titre, "Vérif'All")) {
            return $this->titre;
        }

        /*
         | Un point médian, et non un tiret cadratin.
         |
         | Ce titre est VISIBLE : onglet du navigateur, aperçu de partage sur
         | WhatsApp, résultat de recherche. La consigne éditoriale bannit les
         | tirets de ponctuation des pages publiques, et un titre en fait
         | partie. Le point médian sépare aussi bien sans être un tiret.
         */
        return $this->titre." · Vérif'All";
    }

    /**
     * La description, ramenée à ce qu'un moteur affiche réellement.
     *
     * Au-delà d'environ 160 caractères, Google coupe — et il coupe au milieu
     * d'un mot. Autant couper nous-mêmes, proprement, sur une frontière de mot.
     */
    public function descriptionCourte(): string
    {
        $texte = trim(preg_replace('/\s+/', ' ', strip_tags($this->description)) ?? '');

        if (mb_strlen($texte) <= 160) {
            return $texte;
        }

        $coupe = mb_substr($texte, 0, 157);
        $dernierEspace = mb_strrpos($coupe, ' ');

        return ($dernierEspace !== false ? mb_substr($coupe, 0, $dernierEspace) : $coupe).'…';
    }

    /**
     * Le fil d'Ariane au format schema.org.
     *
     * Google s'en sert pour remplacer l'URL brute par un chemin lisible dans
     * ses résultats — « Vérif'All › Actualités › Ce fact-check » plutôt que
     * « verifall.com/actualites/xxx ».
     *
     * @return array<string, mixed>|null
     */
    public function filArianeStructure(): ?array
    {
        if ($this->filAriane === []) {
            return null;
        }

        $elements = [];
        foreach ($this->filAriane as $position => $etape) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $etape['nom'],
                'item' => $etape['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }
}
