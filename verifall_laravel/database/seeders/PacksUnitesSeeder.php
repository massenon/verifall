<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\UnitPack;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * La grille de départ, reprise de `config/economy.php`.
 *
 * Ce sont les valeurs du PROTOTYPE, appliquées depuis l'arbitrage n° 15. Elles
 * n'ont plus valeur de décision figée : elles sont un point de départ que le
 * porteur du projet modifie depuis le back-office. La configuration reste comme
 * archive du raisonnement, elle n'est plus lue à l'exécution.
 */
final class PacksUnitesSeeder extends Seeder
{
    public function run(): void
    {
        $grille = [
            ['code' => 'p10', 'label' => 'Pack Découverte', 'units' => 10, 'price_fcfa' => 500, 'featured' => false],
            ['code' => 'p25', 'label' => 'Pack Citoyen', 'units' => 25, 'price_fcfa' => 1000, 'featured' => true],
            ['code' => 'p60', 'label' => 'Pack Vigilant', 'units' => 60, 'price_fcfa' => 2000, 'featured' => false],
        ];

        foreach ($grille as $rang => $pack) {
            UnitPack::updateOrCreate(
                ['code' => $pack['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'label' => $pack['label'],
                    'units' => $pack['units'],
                    'price_fcfa' => $pack['price_fcfa'],
                    'is_featured' => $pack['featured'],
                    'is_active' => true,
                    'sort_order' => $rang,
                ],
            );
        }

        $this->command?->info('  '.UnitPack::count().' packs d\'unités.');
    }
}
