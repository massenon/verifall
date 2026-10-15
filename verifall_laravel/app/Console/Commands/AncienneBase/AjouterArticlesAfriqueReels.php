<?php

declare(strict_types=1);

namespace App\Console\Commands\AncienneBase;

use App\Enums\VerdictFactCheck;
use App\Models\HubArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Ajoute trois articles « Actualités vérifiées » portant sur des affirmations
 * réelles circulant en Afrique de l'Ouest et centrale, au delà du seul Bénin.
 *
 * Origine et méthode, pour mémoire (9 octobre 2026) : les sujets viennent de
 * la liste publique Afrique de factuel.afp.com/list/regions/Afrique,
 * consultée via navigateur réel (WebFetch seul échouait sur ce domaine).
 * Aucun texte n'est copié depuis l'AFP — chaque article est une rédaction
 * originale, dans la voix éditoriale de Vérif'All, qui attribue explicitement
 * l'enquête de fond à l'AFP Factuel et renvoie vers son URL réelle (vérifiée
 * par navigation, jamais inventée) en fin d'article via `source_name` /
 * `source_url`. C'est la directive éditoriale (NEWINSTRUCTION_ARTICLE §1, « ne
 * jamais inventer... une enquête ») qui impose cette transparence : Vérif'All
 * ne prétend pas avoir mené seul l'investigation de terrain.
 *
 * Idempotent par slug : ne duplique rien si déjà présent.
 */
final class AjouterArticlesAfriqueReels extends Command
{
    protected $signature = 'verifall:ajouter-articles-afrique-reels {--essai : n’écrit rien, affiche ce qui serait fait}';

    protected $description = "Ajoute 3 articles originaux sur des affirmations réelles circulant en Afrique (Burkina Faso, Côte d'Ivoire, RDC/Mozambique), sourcés vers l'AFP Factuel";

    public function handle(): int
    {
        $essai = (bool) $this->option('essai');

        foreach ($this->articles() as $donnees) {
            $this->ajouter($donnees, $essai);
        }

        $this->info($essai ? 'Essai terminé — rien n’a été écrit.' : '3 articles traités.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{slug: string, title: string, claim: string, excerpt: string, body_file: string, category: string, verdict: VerdictFactCheck, source_name: string, source_url: string, cover_path: string, published_at: string}>
     */
    private function articles(): array
    {
        $dossierCorps = 'C:/Users/tempuser/AppData/Local/Temp/claude/verifall-article-covers';

        return [
            [
                'slug' => 'citations-fabriquees-president-burkinabe-mondial-2026',
                'title' => 'Les citations attribuées au président burkinabé sur le Mondial 2026 sont fabriquées',
                'claim' => 'Le président Ibrahim Traoré aurait critiqué les contrôles migratoires américains liés au Mondial 2026.',
                'excerpt' => "Une image largement partagée attribue au président du Burkina Faso des critiques sur les contrôles migratoires américains liés au Mondial 2026. Cette citation est fabriquée, et l'image porte les marques d'un montage généré par intelligence artificielle.",
                'body_file' => $dossierCorps.'/article-1-burkina.html',
                'category' => 'Intelligence artificielle',
                'verdict' => VerdictFactCheck::Faux,
                'source_name' => 'AFP Factuel',
                'source_url' => 'https://factuel.afp.com/doc.afp.com.B7WL363',
                'cover_path' => 'assets/images/hub/hub-news-7.svg',
                'published_at' => '2026-06-24 10:00:00',
            ],
            [
                'slug' => 'pas-immersion-militaire-obligatoire-fonction-publique-cote-ivoire',
                'title' => "Non, la Côte d'Ivoire n'impose pas d'immersion militaire pour entrer dans la fonction publique",
                'claim' => 'Une immersion militaire serait désormais obligatoire pour intégrer la fonction publique ivoirienne.',
                'excerpt' => "Une publication affirme qu'une période d'immersion militaire est désormais obligatoire pour intégrer la fonction publique en Côte d'Ivoire. Aucun texte réglementaire ne confirme cette mesure, qui n'existe pas.",
                'body_file' => $dossierCorps.'/article-2-cotedivoire.html',
                'category' => 'Société',
                'verdict' => VerdictFactCheck::Faux,
                'source_name' => 'AFP Factuel',
                'source_url' => 'https://factuel.afp.com/doc.afp.com.C4437G3',
                'cover_path' => 'assets/images/hub/hub-news-8.svg',
                'published_at' => '2026-08-07 10:00:00',
            ],
            [
                'slug' => 'video-mozambique-aucun-lien-rdc',
                'title' => "Cette vidéo de violences filmée au Mozambique n'a aucun lien avec la RDC",
                'claim' => 'Une vidéo de violences contre des civils documenterait des exactions commises en RDC.',
                'excerpt' => "Une vidéo de violences contre des civils circule comme preuve d'exactions en République Démocratique du Congo. Les images sont réelles, mais elles ont été filmées au Mozambique, sans rapport avec le conflit congolais.",
                'body_file' => $dossierCorps.'/article-3-rdc.html',
                'category' => 'Conflit',
                'verdict' => VerdictFactCheck::Trompeur,
                'source_name' => 'AFP Factuel',
                'source_url' => 'https://factuel.afp.com/doc.afp.com.C4422ZR',
                'cover_path' => 'assets/images/hub/hub-news-9.svg',
                'published_at' => '2026-08-11 10:00:00',
            ],
        ];
    }

    /**
     * @param  array{slug: string, title: string, claim: string, excerpt: string, body_file: string, category: string, verdict: VerdictFactCheck, source_name: string, source_url: string, cover_path: string, published_at: string}  $donnees
     */
    private function ajouter(array $donnees, bool $essai): void
    {
        if (HubArticle::where('slug', $donnees['slug'])->exists()) {
            $this->line("  déjà présent, ignoré : \"{$donnees['title']}\"");

            return;
        }

        if (! is_file($donnees['body_file'])) {
            $this->error("  FICHIER INTROUVABLE : {$donnees['body_file']} — article ignoré.");

            return;
        }

        $corpsBrut = (string) file_get_contents($donnees['body_file']);

        if ($essai) {
            $this->line('  [essai] +article "'.$donnees['title'].'" — '.mb_strlen(strip_tags($corpsBrut)).' caractères prêts.');

            return;
        }

        $article = new HubArticle;
        $article->forceFill([
            'uuid' => (string) Str::uuid(),
            'slug' => $donnees['slug'],
            'title' => $donnees['title'],
            'claim' => $donnees['claim'],
            'excerpt' => $donnees['excerpt'],
            'body' => trim((string) Purifier::clean($corpsBrut, 'default')),
            'category' => $donnees['category'],
            'verdict' => $donnees['verdict']->value,
            'source_name' => $donnees['source_name'],
            'source_url' => $donnees['source_url'],
            'cover_path' => $donnees['cover_path'],
            'author_id' => null,
            'is_published' => true,
            'is_urgent' => false,
            'published_at' => $donnees['published_at'],
        ])->save();

        $this->line('  ajouté : "'.$donnees['title'].'"');
    }
}
