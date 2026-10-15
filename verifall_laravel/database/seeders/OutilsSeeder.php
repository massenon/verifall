<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Le répertoire d'outils, extrait de compte-user/outils.html.
 *
 * Le JSON a été EXTRAIT du prototype, pas retapé : quinze URL recopiées à la
 * main, c'est quinze occasions d'envoyer quelqu'un ailleurs que là où on le
 * croit.
 */
final class OutilsSeeder extends Seeder
{
    public function run(): void
    {
        $chemin = __DIR__.'/data/outils.json';

        if (! is_file($chemin)) {
            throw new RuntimeException("Répertoire d'outils introuvable : {$chemin}");
        }

        /** @var list<array<string, mixed>> $outils */
        $outils = json_decode((string) file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);

        foreach ($outils as $rang => $outil) {
            $titre = (string) $outil['titre'];

            Tool::updateOrCreate(
                ['slug' => Str::slug($titre)],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => $titre,
                    'description' => (string) $outil['description'],
                    'category' => (string) $outil['categorie'],
                    'tag' => (string) ($outil['etiquette'] ?? '') ?: null,
                    'icon' => (string) ($outil['icone'] ?? 'build'),
                    'url' => (string) $outil['url'],
                    'link_label' => (string) ($outil['libelle_lien'] ?? '') ?: null,
                    'use_cases' => $outil['usages'] ?? [],
                    'keywords' => (string) ($outil['mots_cles'] ?? '') ?: null,
                    'is_recommended' => (bool) ($outil['recommande'] ?? false),
                    'is_published' => true,
                    'sort_order' => $rang,
                ],
            );
        }

        $this->command?->info('  '.Tool::count().' outils de vérification.');
    }
}
