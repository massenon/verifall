<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\VerdictFactCheck;
use App\Models\User;
use App\Models\VerifiedFact;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Les analyses publiées de la page « Actualités » (facts.html).
 *
 * Ces sept analyses viennent du prototype fourni : ce sont les contenus
 * éditoriaux de Vérif'All, pas des verdicts fabriqués pour meubler un écran.
 * La distinction compte — la règle du chantier interdit d'inventer un verdict,
 * elle n'interdit pas de reprendre ceux que la rédaction a écrits.
 *
 * Ce seeder appartient au jeu de DÉMONSTRATION. En production, la base des
 * faits se remplit par l'écran d'entrée du back-office.
 *
 * ## En BROUILLON, depuis le 14 septembre 2026
 *
 * Le prototype ne fournit aucune source pour ces analyses, et l'écran refuse de
 * publier un verdict sans source : « Publier un verdict sans source, c'est
 * demander qu'on vous croie sur parole. » Le seeder les publiait quand même —
 * il posait en base un état que le produit interdit, et qu'on ne pouvait plus
 * rétablir une fois dépublié. Inventer des sources pour les publier aurait été
 * pire : de fausses citations sur une plateforme de vérification.
 *
 * Elles arrivent donc en brouillon, prêtes à être sourcées puis publiées depuis
 * l'écran. Le nom de la classe et le fichier JSON ne changent pas.
 */
final class FaitsVerifiesSeeder extends Seeder
{
    public function run(): void
    {
        $chemin = __DIR__.'/data/faits-verifies.json';

        if (! is_file($chemin)) {
            throw new RuntimeException("Analyses publiées introuvables : {$chemin}");
        }

        /** @var list<array<string, string>> $faits */
        $faits = json_decode((string) file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);

        $redaction = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'mentor']))->first();

        foreach ($faits as $rang => $donnees) {
            $titre = (string) $donnees['titre'];

            $fait = VerifiedFact::firstOrNew(['title' => $titre]);

            if ($fait->exists) {
                continue;
            }

            $fait->forceFill([
                'uuid' => (string) Str::uuid(),
                'title' => $titre,
                'summary' => (string) $donnees['resume'],
                'verdict' => VerdictFactCheck::from((string) $donnees['verdict'])->value,
                /*
                 | Les mots-clés servent au rapprochement d'une nouvelle
                 | vérification avec une analyse déjà rendue : c'est ce qui
                 | évite de refacturer une question déjà tranchée.
                 */
                'keywords' => Str::limit(mb_strtolower($titre.' '.$donnees['resume']), 240, ''),
                'sources' => [],
                // Sans source, pas de publication : voir l'en-tête.
                'is_published' => false,
                'created_by' => $redaction?->id,
                // Étalées dans le temps : sept analyses publiées à la seconde
                // près se verraient au premier coup d'œil.
                'created_at' => now()->subDays(($rang + 1) * 9),
                'updated_at' => now()->subDays(($rang + 1) * 9),
            ])->save();
        }

        $this->command?->info('  '.VerifiedFact::count().' analyses en base — celles du prototype en brouillon, faute de source.');
    }
}
