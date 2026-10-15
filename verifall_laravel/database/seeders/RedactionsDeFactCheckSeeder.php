<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tool;
use App\Models\TrustedSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seize rédactions de fact-checking, offertes au citoyen comme des OUTILS.
 *
 * ## Pourquoi seize et pas trois cent quatre-vingt-six
 *
 * {@see SourcesVeraSeeder} a posé 386 sources dans `trusted_sources`. Elles y
 * servent le MOTEUR : il s'en sert pour créditer un domaine. Elles ne sont pas
 * faites pour être parcourues.
 *
 * `tools` est autre chose : le répertoire de ce qu'une personne ouvre pour
 * vérifier elle-même — une machine à remonter le temps, un lecteur d'EXIF, une
 * recherche d'image inversée. Y verser 386 lignes aurait noyé les quinze outils
 * réels sous une liste de sites, sans description ni cas d'usage.
 *
 * D'où une CURATION, et ses trois règles :
 *
 *   1. **La proximité d'abord.** Le Bénin, puis l'Afrique de l'Ouest, puis le
 *      continent. Une rumeur qui circule à Cotonou a plus de chances d'avoir
 *      déjà été vérifiée à Cotonou, Abidjan ou Yaoundé qu'à Washington.
 *   2. **Le lien ouvre sur du fact-checking, pas sur une page d'accueil.** Les
 *      rédactions dont la vérification vit dans une RUBRIQUE d'un journal
 *      généraliste ont été écartées — `lemonde.fr` ou `lanation.bj` mèneraient
 *      à la une du jour, pas aux Décodeurs. Un outil qui n'ouvre pas là où il
 *      promet est pire qu'un outil absent.
 *   3. **Chaque entrée existe déjà dans `trusted_sources`.** Ce que nous
 *      recommandons de consulter et ce que le moteur crédite sont la même
 *      liste ; un test le garde.
 *
 * ## Insertion seule
 *
 * `firstOrCreate` sur le `slug`. Une fiche que la rédaction a amendée depuis
 * l'écran ne doit pas revenir à la valeur d'usine au semis suivant — c'est la
 * même règle que pour les sources.
 */
final class RedactionsDeFactCheckSeeder extends Seeder
{
    /** La catégorie existe déjà dans le répertoire repris du prototype. */
    private const CATEGORIE = 'Fact-checking';

    /**
     * Les quinze premiers rangs appartiennent au répertoire du prototype.
     * Celles-ci viennent après, dans l'ordre de proximité.
     */
    private const PREMIER_RANG = 100;

    public function run(): void
    {
        foreach ($this->redactions() as $rang => [$titre, $domaine, $etiquette, $description, $usages, $recommande]) {
            /*
             | Le domaine sert de CLÉ vers le référentiel, pas d'URL recopiée.
             | Si la source disparaît du référentiel, l'outil ne doit pas
             | survivre en pointant dans le vide.
             */
            $source = TrustedSource::query()
                ->where('domain', TrustedSource::normaliserLeDomaine($domaine))
                ->first();

            if ($source === null) {
                throw new RuntimeException(sprintf(
                    'La rédaction « %s » (%s) n’est pas au référentiel des sources. '.
                    'Lancez d’abord SourcesVeraSeeder, ou retirez-la de cette liste.',
                    $titre,
                    $domaine,
                ));
            }

            Tool::query()->firstOrCreate(
                ['slug' => Str::slug($titre)],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => $titre,
                    'description' => $description,
                    'category' => self::CATEGORIE,
                    'tag' => $etiquette,
                    // L'écran prend l'icône de la CATÉGORIE ; celle-ci n'est
                    // qu'une valeur de repli cohérente avec elle.
                    'icon' => 'fact_check',
                    'url' => 'https://'.$source->domain,
                    'link_label' => null,
                    'use_cases' => $usages,
                    'keywords' => $this->motsCles($titre, $description, $usages, $etiquette),
                    'is_recommended' => $recommande,
                    'is_published' => true,
                    'sort_order' => self::PREMIER_RANG + $rang,
                ],
            );
        }

        $this->command?->info(sprintf(
            '  %d rédactions de fact-checking dans le répertoire (%d outils au total).',
            count($this->redactions()),
            Tool::query()->count(),
        ));
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: list<string>, 5: bool}>
     */
    private function redactions(): array
    {
        return [
            // ─────────────────────────────────────────────────── Bénin et voisins
            ['Badona', 'badona.info', 'BÉNIN',
                'La vérification des faits au Bénin. Cherchez si une rumeur qui circule ici a déjà été examinée.',
                ['Rumeurs béninoises', 'Actualité nationale'], true],

            ['Ivoirecheck', 'ivoirecheck.co', 'CÔTE D’IVOIRE',
                'Les vérifications ivoiriennes. Beaucoup d’intox traversent la sous-région avant d’arriver au Bénin.',
                ['Sous-région', 'Intox partagées'], true],

            ['237Check', '237check.org', 'CAMEROUN',
                'Le fact-checking camerounais, en français. Utile pour les rumeurs qui circulent en Afrique centrale.',
                ['Afrique centrale', 'Français'], false],

            ['CongoCheck', 'congocheck.net', 'RD CONGO',
                'Vérifications congolaises, souvent sur des images et vidéos qui repartent ailleurs sur le continent.',
                ['Images recyclées', 'Afrique centrale'], false],

            ['Balobaki Check', 'balobakicheck.com', 'RD CONGO',
                'Une seconde rédaction congolaise. Deux avis valent mieux qu’un quand une image ressort d’un autre pays.',
                ['Recoupement', 'Vidéos'], false],

            // ─────────────────────────────────────────────── Afrique, continental
            ['Africa Check', 'africacheck.org', 'AFRIQUE',
                'La plus ancienne rédaction de vérification du continent. Santé, économie, déclarations publiques.',
                ['Chiffres officiels', 'Déclarations'], true],

            ['PesaCheck', 'pesacheck.org', 'AFRIQUE',
                'Spécialisée dans les chiffres : budgets, statistiques, promesses publiques passées au crible.',
                ['Budgets', 'Statistiques'], false],

            ['Dubawa', 'dubawa.org', 'AFRIQUE DE L’OUEST',
                'Vérifications en Afrique de l’Ouest, en anglais. Complète utilement les rédactions francophones.',
                ['Afrique de l’Ouest', 'Anglais'], false],

            ['FactCheck Africa', 'factcheckafrica.net', 'AFRIQUE',
                'Vérifications panafricaines, avec une attention particulière aux réseaux sociaux.',
                ['Réseaux sociaux', 'Panafricain'], false],

            // ──────────────────────────────────────────────────────── Francophonie
            ['Factoscope', 'factoscope.fr', 'FRANCOPHONIE',
                'Un annuaire francophone du fact-checking : de quoi retrouver qui a vérifié quoi, et quand.',
                ['Annuaire', 'Francophone'], false],

            // ───────────────────────────────────────────── International, thématique
            ['Poynter — IFCN', 'poynter.org', 'RÉSEAU',
                'Le réseau international des rédactions de vérification, et le code de principes qu’elles signent.',
                ['Qui vérifie', 'Déontologie'], false],

            ['EUvsDisinfo', 'euvsdisinfo.eu', 'EUROPE',
                'Une base de campagnes de désinformation documentées, utile quand une intox vient de loin.',
                ['Campagnes', 'Base de données'], false],

            ['Health Feedback', 'healthfeedback.org', 'SANTÉ',
                'Des scientifiques évaluent les affirmations de santé. Le domaine où une fausse information coûte le plus cher.',
                ['Santé', 'Avis scientifique'], true],

            ['Climate Feedback', 'climatefeedback.org', 'CLIMAT',
                'Le même principe, appliqué au climat : des chercheurs notent ce que dit un article.',
                ['Climat', 'Avis scientifique'], false],

            ['Snopes', 'snopes.com', 'MONDE',
                'La doyenne des rédactions de vérification. Une mémoire longue des rumeurs qui reviennent.',
                ['Rumeurs anciennes', 'Anglais'], false],

            ['Full Fact', 'fullfact.org', 'ROYAUME-UNI',
                'Vérification britannique, réputée pour sa méthode et ses corrections publiées.',
                ['Méthode', 'Corrections'], false],
        ];
    }

    /**
     * Les mots-clés de la recherche de l'écran, sur le modèle du répertoire
     * repris du prototype : tout le texte visible, en minuscules.
     *
     * @param  list<string>  $usages
     */
    private function motsCles(string $titre, string $description, array $usages, string $etiquette): string
    {
        return mb_strtolower(mb_substr(
            implode(' ', [$titre, $description, implode(' ', $usages), $etiquette, self::CATEGORIE]),
            0,
            500,
        ));
    }
}
