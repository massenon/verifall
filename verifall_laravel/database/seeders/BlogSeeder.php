<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Blog public : rubriques et annonces RÉELLES.
 *
 * Le prototype montre neuf cartes d'articles. Sept portent l'attribut
 * `title="Exemple de carte — contenu à venir"` : ce sont des placeholders que
 * son auteur signale lui-même comme tels. Les semer produirait un blog rempli
 * d'articles qui n'existent pas, sur un site dont tout l'objet est de
 * distinguer ce qui existe de ce qui n'existe pas.
 *
 * Deux contenus, en revanche, sont de vraies nouvelles de la plateforme — deux
 * distinctions reçues, présentées comme telles dans le prototype et sur la page
 * d'accueil. Elles sont semées.
 */
final class BlogSeeder extends Seeder
{
    /** Rubriques du prototype, dans son ordre d'affichage. */
    private const RUBRIQUES = ['Analyses', 'Guides', 'Formation', 'Partenariats', 'Outils'];

    /**
     * @var list<array{slug: string, rubrique: string, titre: string, chapo: string, corps: string, image: string, mise_en_avant: bool}>
     */
    private const ANNONCES = [
        [
            'slug' => 'verifall-remporte-le-prix-wsis-2026',
            'rubrique' => 'Partenariats',
            'titre' => 'Vérif\'All remporte le Prix WSIS 2026',
            'chapo' => 'La plateforme béninoise de vérification des faits a remporté le Prix WSIS 2026 '
                .'dans la catégorie « Media », décerné par l\'Union internationale des '
                .'télécommunications à Genève, parmi 1 595 projets issus de 122 pays.',
            'corps' => '<p>Décernée par l\'Union internationale des télécommunications (UIT), agence '
                .'spécialisée des Nations unies, cette distinction consacre Vérif\'All parmi '
                .'1 595 projets issus de 122 pays.</p>'
                .'<p>La preuve qu\'une boussole de l\'information conçue au Bénin peut guider '
                .'le monde.</p>',
            'image' => 'assets/images/blog/verifall-prix-wsis-2026-annonce.jpg',
            'mise_en_avant' => true,
        ],
        [
            'slug' => 'deuxieme-prix-concours-objectif-developpement-durable',
            'rubrique' => 'Partenariats',
            'titre' => '2ᵉ prix du Concours « Objectif : Développement Durable ! »',
            'chapo' => 'Face à des projets engagés venus de toute l\'Académie, Vérif\'All a décroché '
                .'le 2ᵉ prix du concours d\'Aix-Marseille Université.',
            'corps' => '<p>Une reconnaissance qui salue un projet où l\'esprit critique devient un '
                .'levier de développement durable.</p>',
            'image' => 'assets/images/blog/2eme-prix-concours-objectif-developpement-durable.jpg',
            'mise_en_avant' => false,
        ],
    ];

    public function run(): void
    {
        $rubriques = $this->planterLesRubriques();
        $auteur = $this->auteur();

        foreach (self::ANNONCES as $rang => $donnees) {
            $article = BlogPost::firstOrNew(['slug' => $donnees['slug']]);

            if ($article->exists) {
                continue;
            }

            $article->forceFill([
                'uuid' => (string) Str::uuid(),
                'blog_category_id' => $rubriques[$donnees['rubrique']]->id,
                'author_id' => $auteur?->id,
                'title' => $donnees['titre'],
                'excerpt' => $donnees['chapo'],
                'body' => $donnees['corps'],
                'cover_path' => $donnees['image'],
                'is_featured' => $donnees['mise_en_avant'],
                'is_published' => true,
                'published_at' => now()->subDays($rang * 30),
            ])->save();
        }

        $this->command?->info(sprintf(
            '  %d rubriques · %d annonces publiées.',
            BlogCategory::count(),
            BlogPost::count(),
        ));
    }

    /**
     * @return array<string, BlogCategory>
     */
    private function planterLesRubriques(): array
    {
        $rubriques = [];

        foreach (self::RUBRIQUES as $rang => $nom) {
            $rubrique = BlogCategory::firstOrNew(['slug' => Str::slug($nom)]);

            $rubrique->forceFill([
                'uuid' => $rubrique->uuid ?? (string) Str::uuid(),
                'name' => $nom,
                'position' => $rang + 1,
            ])->save();

            $rubriques[$nom] = $rubrique;
        }

        return $rubriques;
    }

    /**
     * Un compte de l'équipe, s'il en existe un.
     *
     * L'article reste publiable sans auteur rattaché : une annonce sans
     * signature vaut mieux qu'un seeder qui échoue sur une base sans staff.
     */
    private function auteur(): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', UserRole::Admin->value))->first();
    }
}
