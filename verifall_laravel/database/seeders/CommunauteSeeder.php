<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AvisCommunaute;
use App\Models\ForumCategory;
use App\Models\Thread;
use App\Models\ThreadVote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Forum citoyen : rubriques et sujets du prototype.
 *
 * Les cinq rubriques et les cinq sujets viennent de `forum.html`. Ils ne sont
 * pas décoratifs : ils montrent les trois cas que la page doit savoir rendre —
 * un sujet sans vote, un sujet où la communauté hésite, et un sujet que
 * l'équipe a tranché. Sans eux, l'écran se teste toujours dans son cas le plus
 * simple.
 */
final class CommunauteSeeder extends Seeder
{
    /** Rubriques du prototype, dans son ordre. */
    private const RUBRIQUES = ['Santé', 'Technologie', 'Fake News', 'Société', 'Politique'];

    /**
     * Sujets repris de `forum.html`.
     *
     * `avis` reproduit la répartition affichée par le prototype ; les votes
     * sont créés un par un, par des comptes distincts, pour que les compteurs
     * dénormalisés soient calculés par le même chemin qu'en production.
     *
     * @var list<array{titre: string, corps: string, rubrique: string, avis: array<string, int>|null, verdict: string|null, source: string|null}>
     */
    private const SUJETS = [
        [
            'titre' => 'Cette vidéo montre-t-elle réellement un nouveau vaccin au Bénin ?',
            'corps' => 'Elle circule massivement sur WhatsApp. Est-ce réellement une campagne officielle ?',
            'rubrique' => 'Santé',
            'avis' => ['true' => 6, 'doubt' => 2, 'false' => 2],
            'verdict' => null,
            'source' => null,
        ],
        [
            'titre' => 'Reconnaître une image générée par IA : vos techniques ?',
            'corps' => 'Partagez vos indices favoris : textures, mains, reflets, incohérences de lumière…',
            'rubrique' => 'Technologie',
            'avis' => null,
            'verdict' => null,
            'source' => null,
        ],
        [
            'titre' => 'Fausse offre d\'emploi « Port de Cotonou » qui demande des frais',
            'corps' => 'Le site demande 5 000 FCFA de « frais de dossier ». Signalé au fact-checking avec le lien source.',
            'rubrique' => 'Fake News',
            'avis' => ['true' => 1, 'doubt' => 1, 'false' => 8],
            'verdict' => null,
            'source' => 'https://recrutement-pac-benin.example/offre',
        ],
        [
            'titre' => 'Comment convaincre mes proches de vérifier avant de partager ?',
            'corps' => 'Ma famille partage tout ce qui passe dans le groupe. Vos astuces pédagogiques ?',
            'rubrique' => 'Société',
            'avis' => null,
            'verdict' => null,
            'source' => null,
        ],
        [
            'titre' => 'Le prétendu décret de nomination circule encore',
            'corps' => 'Vérif\'All l\'a démenti, mais il revient sous une autre forme. Restez vigilants.',
            'rubrique' => 'Politique',
            'avis' => ['true' => 1, 'doubt' => 1, 'false' => 8],
            // Le seul sujet tranché : c'est lui qui prouve que le verdict
            // officiel s'affiche AU-DESSUS de l'avis de la communauté.
            'verdict' => 'false',
            'source' => null,
        ],
    ];

    public function run(): void
    {
        $rubriques = $this->planterLesRubriques();
        $comptes = User::query()->limit(10)->get();

        if ($comptes->isEmpty()) {
            $this->command?->warn('  Aucun compte : le forum reste vide.');

            return;
        }

        foreach (self::SUJETS as $rang => $donnees) {
            $auteur = $comptes[$rang % $comptes->count()];

            $sujet = Thread::firstOrNew(['slug' => Str::slug($donnees['titre'])]);

            if ($sujet->exists) {
                continue;
            }

            $sujet->forceFill([
                'uuid' => (string) Str::uuid(),
                'user_id' => $auteur->id,
                'forum_category_id' => $rubriques[$donnees['rubrique']]->id,
                'title' => $donnees['titre'],
                'body' => '<p>'.e($donnees['corps']).'</p>',
                'source_url' => $donnees['source'],
                'official_verdict' => $donnees['verdict'],
                'verified_at' => $donnees['verdict'] !== null ? now()->subDay() : null,
                'last_activity_at' => now()->subHours($rang + 1),
            ])->save();

            if ($donnees['avis'] !== null) {
                $this->planterLesAvis($sujet, $donnees['avis'], $comptes);
            }
        }

        $this->command?->info(sprintf(
            '  %d rubriques · %d sujets · %d avis.',
            ForumCategory::count(),
            Thread::count(),
            ThreadVote::count(),
        ));
    }

    /**
     * @return array<string, ForumCategory>
     */
    private function planterLesRubriques(): array
    {
        $rubriques = [];

        foreach (self::RUBRIQUES as $rang => $nom) {
            $rubrique = ForumCategory::firstOrNew(['slug' => Str::slug($nom)]);

            $rubrique->forceFill([
                'uuid' => $rubrique->uuid ?? (string) Str::uuid(),
                'name' => $nom,
                'position' => $rang + 1,
                'is_active' => true,
            ])->save();

            $rubriques[$nom] = $rubrique;
        }

        return $rubriques;
    }

    /**
     * @param  array<string, int>  $repartition
     * @param  Collection<int, User>  $comptes
     */
    private function planterLesAvis(Thread $sujet, array $repartition, $comptes): void
    {
        $index = 0;

        foreach ($repartition as $opinion => $combien) {
            for ($i = 0; $i < $combien; $i++) {
                $votant = $comptes[$index % $comptes->count()];
                $index++;

                // Un vote par personne et par sujet : au-delà du nombre de
                // comptes disponibles, on s'arrête plutôt que de violer la
                // contrainte.
                if (ThreadVote::where('thread_id', $sujet->id)->where('user_id', $votant->id)->exists()) {
                    continue;
                }

                ThreadVote::create([
                    'thread_id' => $sujet->id,
                    'user_id' => $votant->id,
                    'opinion' => AvisCommunaute::from($opinion)->value,
                ]);
            }
        }

        $comptes = ThreadVote::where('thread_id', $sujet->id)
            ->selectRaw('opinion, COUNT(*) as total')
            ->groupBy('opinion')
            ->pluck('total', 'opinion');

        $sujet->forceFill([
            'votes_true' => (int) $comptes->get(AvisCommunaute::Vrai->value, 0),
            'votes_doubt' => (int) $comptes->get(AvisCommunaute::Doute->value, 0),
            'votes_false' => (int) $comptes->get(AvisCommunaute::Faux->value, 0),
        ])->save();
    }
}
