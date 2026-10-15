<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Les huit badges de l'Achievement Center, repris de `recompenses.html`.
 *
 * Les badges VERROUILLÉS sont créés eux aussi, avec leur condition affichée :
 * le prototype les montre grisés, et il a raison — voir ce qui reste à
 * atteindre est ce qui donne envie de continuer. Un badge invisible tant qu'il
 * n'est pas gagné ne motive personne.
 */
final class BadgesSeeder extends Seeder
{
    /**
     * @var list<array{slug: string, icone: string, nom: string, description: string, condition: string, xp: int, unites: int}>
     */
    private const BADGES = [
        [
            'slug' => 'premiere-verification', 'icone' => '🎯',
            'nom' => 'Première vérification',
            'description' => 'Vous avez soumis votre premier contenu au laboratoire.',
            'condition' => '1 vérification requise', 'xp' => 50, 'unites' => 0,
        ],
        [
            'slug' => 'verificateur-debutant', 'icone' => '🔍',
            'nom' => 'Vérificateur débutant',
            'description' => 'Vous avez complété votre premier parcours de vérification.',
            'condition' => '3 leçons terminées requises', 'xp' => 100, 'unites' => 0,
        ],
        [
            'slug' => 'premier-quiz', 'icone' => '🧠',
            'nom' => 'Premier quiz réussi',
            'description' => 'Vous avez validé un quiz avec un score supérieur au seuil.',
            'condition' => '1 quiz réussi requis', 'xp' => 100, 'unites' => 0,
        ],
        [
            'slug' => 'diplome', 'icone' => '🎓',
            'nom' => 'Diplômé Vérif\'All',
            'description' => 'Vous avez obtenu votre premier certificat officiel.',
            'condition' => '1 certificat requis', 'xp' => 500, 'unites' => 0,
        ],
        [
            'slug' => 'serie-sept-jours', 'icone' => '🔥',
            'nom' => 'Série de 7 jours',
            'description' => 'Sept jours consécutifs de vigilance active.',
            'condition' => '7 jours consécutifs requis', 'xp' => 0, 'unites' => 5,
        ],
        [
            'slug' => 'chasseur-intox', 'icone' => '🛰️',
            'nom' => 'Chasseur d\'intox',
            'description' => 'Cinq contenus faux ou trompeurs détectés.',
            'condition' => '5 intox détectées requises', 'xp' => 200, 'unites' => 0,
        ],
        [
            'slug' => 'verificateur-confirme', 'icone' => '🏛️',
            'nom' => 'Vérificateur confirmé',
            'description' => 'Atteignez 1 000 XP cumulés.',
            'condition' => '1 000 XP requis', 'xp' => 0, 'unites' => 10,
        ],
        [
            'slug' => 'ambassadeur', 'icone' => '🤝',
            'nom' => 'Ambassadeur',
            'description' => 'Trois filleuls actifs grâce à votre parrainage.',
            'condition' => '3 parrainages éligibles requis', 'xp' => 300, 'unites' => 0,
        ],
    ];

    public function run(): void
    {
        foreach (self::BADGES as $rang => $donnees) {
            $badge = Badge::firstOrNew(['slug' => $donnees['slug']]);

            $badge->forceFill([
                'uuid' => $badge->uuid ?? (string) Str::uuid(),
                'icon' => $donnees['icone'],
                'name' => $donnees['nom'],
                'description' => $donnees['description'],
                'requirement_label' => $donnees['condition'],
                'xp_reward' => $donnees['xp'],
                'units_reward' => $donnees['unites'],
                'position' => $rang + 1,
                'is_active' => true,
            ])->save();
        }

        $this->command?->info(sprintf('  %d badges.', Badge::count()));
    }
}
