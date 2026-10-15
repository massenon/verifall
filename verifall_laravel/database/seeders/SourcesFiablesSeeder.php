<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SourceCategory;
use App\Models\TrustedSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Un référentiel de départ — cinq familles, une vingtaine de sources.
 *
 * ## Pourquoi semer, plutôt que livrer une table vide
 *
 * L'écran fonctionne à vide, mais le MOTEUR non : tant que rien n'est
 * référencé, il ne juge les domaines que par leur forme. Le correctif de
 * suffixe institutionnel écarte déjà le faux positif sur `.gov` et `.gouv.xx`,
 * mais rien ne dit encore à l'analyse que `reuters.com` ou `lemonde.fr` sont
 * des sources établies.
 *
 * ## Ce que ce jeu n'est PAS
 *
 * Ce n'est pas une position éditoriale de Vérif'All. Ce sont des institutions
 * et des agences dont le statut ne se discute pas — organisations
 * intergouvernementales, agences de presse mondiales, presse béninoise de
 * référence, réseau international de fact-checking. **Aucun média partisan,
 * aucun classement de qualité.**
 *
 * Les crédibilités sont volontairement PRUDENTES et n'excèdent pas 92 : un
 * référentiel qui décerne des 100 se fait lire comme un jugement absolu, alors
 * qu'il ne dit qu'une chose — cette source est identifiée et suivie.
 *
 * La rédaction est censée l'amender depuis l'écran. C'est un point de départ,
 * pas une vérité livrée.
 */
final class SourcesFiablesSeeder extends Seeder
{
    public function run(): void
    {
        $familles = [
            'institutions-internationales' => [
                'Institutions internationales', 88,
                'Organisations intergouvernementales et agences des Nations unies.',
                10,
            ],
            'institutions-beninoises' => [
                'Institutions béninoises', 85,
                'Portails officiels de l\'État béninois et de ses démembrements.',
                20,
            ],
            'agences-de-presse' => [
                'Agences de presse', 90,
                'Agences mondiales, dont les dépêches alimentent la plupart des rédactions.',
                30,
            ],
            'presse-beninoise' => [
                'Presse béninoise', 75,
                'Titres nationaux de référence. Le suivi éditorial reste nécessaire.',
                40,
            ],
            'reseaux-de-fact-checking' => [
                'Réseaux de fact-checking', 88,
                'Rédactions membres de réseaux de vérification reconnus.',
                50,
            ],
        ];

        $idsParSlug = [];

        foreach ($familles as $slug => [$nom, $credibilite, $description, $ordre]) {
            $famille = SourceCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $nom,
                    'description' => $description,
                    'default_credibility' => $credibilite,
                    'sort_order' => $ordre,
                ],
            );

            $idsParSlug[$slug] = $famille->id;
        }

        $sources = [
            // — institutions internationales
            ['who.int', 'Organisation mondiale de la santé', 'institutions-internationales', 90],
            ['un.org', 'Organisation des Nations unies', 'institutions-internationales', 90],
            ['unicef.org', 'UNICEF', 'institutions-internationales', 88],
            ['worldbank.org', 'Banque mondiale', 'institutions-internationales', 88],
            ['imf.org', 'Fonds monétaire international', 'institutions-internationales', 88],
            ['au.int', 'Union africaine', 'institutions-internationales', 85],
            ['ecowas.int', 'CEDEAO', 'institutions-internationales', 85],

            // — institutions béninoises
            ['gouv.bj', 'Portail officiel du Gouvernement du Bénin', 'institutions-beninoises', 88],
            ['sante.gouv.bj', 'Ministère de la Santé', 'institutions-beninoises', 85],
            ['insae-bj.org', 'Institut national de la statistique', 'institutions-beninoises', 85],

            // — agences de presse
            ['reuters.com', 'Reuters', 'agences-de-presse', 92],
            ['apnews.com', 'Associated Press', 'agences-de-presse', 92],
            ['afp.com', 'Agence France-Presse', 'agences-de-presse', 92],
            ['bbc.com', 'BBC', 'agences-de-presse', 88],
            ['rfi.fr', 'Radio France Internationale', 'agences-de-presse', 85],

            // — presse béninoise
            ['lanationbenin.info', 'La Nation', 'presse-beninoise', 78],
            ['matinlibre.com', 'Matin Libre', 'presse-beninoise', 72],
            ['24haubenin.info', '24 heures au Bénin', 'presse-beninoise', 72],
            ['banouto.info', 'Banouto', 'presse-beninoise', 75],

            // — réseaux de fact-checking
            ['africacheck.org', 'Africa Check', 'reseaux-de-fact-checking', 90],
            ['factcheck.afp.com', 'AFP Factuel', 'reseaux-de-fact-checking', 90],
            ['poynter.org', 'Poynter — IFCN', 'reseaux-de-fact-checking', 88],
        ];

        foreach ($sources as [$domaine, $nom, $slugFamille, $credibilite]) {
            TrustedSource::updateOrCreate(
                // La clé est le DOMAINE NORMALISÉ, comme dans l'écran : semer
                // deux fois ne doit jamais produire deux fiches.
                ['domain' => TrustedSource::normaliserLeDomaine($domaine)],
                [
                    'uuid' => (string) Str::uuid(),
                    'source_category_id' => $idsParSlug[$slugFamille],
                    'name' => $nom,
                    'url' => 'https://'.$domaine,
                    'credibility' => $credibilite,
                    'is_active' => true,
                    'notes' => null,
                ],
            );
        }
    }
}
