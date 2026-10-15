<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\VerdictFactCheck;
use App\Models\HubArticle;
use App\Models\HubJob;
use App\Models\HubPortal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Hub d'Infos Bénin — brèves et portails officiels.
 *
 * Les six brèves sont EXTRAITES de `hub-infos.html` vers
 * `data/hub-articles.json`, comme le catalogue de l'Académie : les recopier à
 * la main, c'est se tromper un jour de verdict sur une brève — et une brève
 * mal étiquetée dit l'inverse de ce qu'elle devrait dire.
 */
final class HubSeeder extends Seeder
{
    /**
     * Portails officiels du Bénin.
     *
     * `is_official` est renseigné à la main et jamais déduit du domaine :
     * « gouv-benin.info » n'est pas « gouv.bj », et c'est précisément le piège
     * qu'on apprend aux gens à repérer.
     *
     * @var list<array{nom: string, url: string, categorie: string}>
     */
    private const PORTAILS = [
        ['nom' => 'Portail officiel du Gouvernement', 'url' => 'https://www.gouv.bj', 'categorie' => 'Institutions'],
        ['nom' => 'Service Public Bénin', 'url' => 'https://service-public.bj', 'categorie' => 'Démarches'],
        ['nom' => 'ARCEP Bénin', 'url' => 'https://arcep.bj', 'categorie' => 'Télécoms'],
        ['nom' => 'Ministère de la Santé', 'url' => 'https://sante.gouv.bj', 'categorie' => 'Santé'],
        ['nom' => 'Agence Bénin Presse', 'url' => 'https://abp.bj', 'categorie' => 'Médias'],
    ];

    public function run(): void
    {
        $this->planterLesPortails();
        $this->planterLesBreves();
        $this->planterLesOffres();

        $this->command?->info(sprintf(
            '  %d brèves · %d portails officiels · %d opportunités.',
            HubArticle::count(),
            HubPortal::count(),
            HubJob::count(),
        ));
    }

    private function planterLesPortails(): void
    {
        foreach (self::PORTAILS as $rang => $donnees) {
            $portail = HubPortal::firstOrNew(['url' => $donnees['url']]);

            $portail->forceFill([
                'uuid' => $portail->uuid ?? (string) Str::uuid(),
                'name' => $donnees['nom'],
                'category' => $donnees['categorie'],
                'is_official' => true,
                'position' => $rang + 1,
                'is_published' => true,
            ])->save();
        }
    }

    /**
     * Les opportunités.
     *
     * Les dates limites sont RELATIVES au jour du semis : une démonstration
     * dont toutes les offres sont expirées ne montre plus rien — et la
     * fermeture automatique après le délai de grâce les effacerait de l'écran
     * dès le lendemain.
     *
     * La dernière est volontairement expirée depuis dix jours : c'est le seul
     * moyen de voir que `verifall:fermer-opportunites` fait bien son travail.
     */
    private function planterLesOffres(): void
    {
        $chemin = __DIR__.'/data/hub-offres.json';

        if (! is_file($chemin)) {
            throw new RuntimeException("Opportunités du Hub introuvables : {$chemin}");
        }

        /** @var list<array<string, mixed>> $offres */
        $offres = json_decode((string) file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);

        foreach ($offres as $rang => $donnees) {
            $titre = (string) $donnees['titre'];

            HubJob::updateOrCreate(
                ['slug' => Str::slug(Str::limit($titre, 80, ''))],
                [
                    'uuid' => (string) Str::uuid(),
                    'title' => $titre,
                    'organisation' => (string) $donnees['organisation'],
                    'scope' => (string) $donnees['portee'],
                    'type' => (string) $donnees['type'],
                    'location' => (string) $donnees['lieu'],
                    'description' => (string) $donnees['resume'],
                    'apply_url' => (string) $donnees['url'],
                    'deadline' => now()->addDays(20 + $rang * 5),
                    'is_verified' => (bool) $donnees['verifiee'],
                    'is_published' => true,
                    'published_at' => now()->subDays($rang + 2),
                    'closed_at' => null,
                ],
            );
        }
    }

    private function planterLesBreves(): void
    {
        $chemin = __DIR__.'/data/hub-articles.json';

        if (! is_file($chemin)) {
            throw new RuntimeException("Brèves du Hub introuvables : {$chemin}");
        }

        /** @var list<array<string, string|null>> $breves */
        $breves = json_decode((string) file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);

        foreach ($breves as $rang => $donnees) {
            $titre = (string) $donnees['titre'];
            $article = HubArticle::firstOrNew(['slug' => Str::slug(Str::limit($titre, 80, ''))]);

            if ($article->exists) {
                continue;
            }

            $article->forceFill([
                'uuid' => (string) Str::uuid(),
                'title' => $titre,
                /*
                 | L'AFFIRMATION EXAMINÉE, distincte du titre.
                 |
                 | Le titre est éditorial et énonce déjà la conclusion :
                 | « Aucune taxe WhatsApp n'est instaurée ». Employé comme
                 | `claimReviewed` avec un verdict FAUX, il fait dire au
                 | balisage « il est faux qu'aucune taxe n'existe » — l'inverse
                 | exact du démenti.
                 |
                 | Sans cette valeur, aucun `ClaimReview` n'est émis : pas de
                 | balisage vaut infiniment mieux qu'un balisage inversé.
                 */
                'claim' => $donnees['affirmation'] ?? null,
                'excerpt' => (string) $donnees['chapo'],
                'body' => '<p>'.e((string) $donnees['chapo']).'</p>',
                'category' => (string) $donnees['categorie'],
                'verdict' => $this->verdict((string) ($donnees['bandeau'] ?? ''))?->value,
                'source_name' => $donnees['source'] ?: null,
                'cover_path' => isset($donnees['couverture']) && $donnees['couverture'] !== null
                    ? 'assets/images/hub/'.$donnees['couverture']
                    : null,
                'is_published' => true,
                'published_at' => now()->subDays($rang),
            ])->save();
        }
    }

    /**
     * Bandeau du prototype → verdict du produit.
     *
     * Le Hub dit « DÉMENTI » là où le rapport dit « FAUX » : deux registres,
     * un seul verdict stocké. Un second vocabulaire en base finirait par
     * diverger du premier.
     */
    private function verdict(string $bandeau): ?VerdictFactCheck
    {
        return match ($bandeau) {
            'ok' => VerdictFactCheck::Vrai,
            'no' => VerdictFactCheck::Faux,
            'warn' => VerdictFactCheck::Trompeur,
            default => null,
        };
    }
}
