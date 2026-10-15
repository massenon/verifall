<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Community\Services\SignalementDeContenu;
use App\Enums\PorteeOpportunite;
use App\Enums\TypeOpportunite;
use App\Http\Api\V1\Resources\ArticleHubResource;
use App\Http\Api\V1\Resources\OpportuniteResource;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Http\Api\V1\Support\Filtre;
use App\Http\Api\V1\Support\PageCurseur;
use App\Models\HubArticle;
use App\Models\HubJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le Hub : brèves vérifiées et opportunités.
 *
 * DEUX CHOSES NE SONT PAS EXPOSÉES, et c'est délibéré :
 *
 *   · les PORTAILS OFFICIELS, retirés du parcours citoyen par le prototype
 *     mobile (la rubrique reste en back-office) ;
 *   · tout contenu NON PUBLIÉ. Un brouillon d'article dont on connaîtrait le
 *     slug ne doit pas se lire : la préparation d'une vérification est
 *     précisément ce qu'il ne faut pas divulguer avant l'heure.
 */
final class HubController
{
    /** `GET /hub/articles` */
    public function articles(Request $requete): JsonResponse
    {
        $donnees = $requete->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer'],
            'cursor' => ['nullable', 'string'],
        ]);

        $requeteSql = HubArticle::where('is_published', true);

        $categorie = Filtre::valeur($requete, 'category');

        if ($categorie !== null) {
            $requeteSql->where('category', $categorie);
        }

        $page = $requeteSql->orderByDesc('id')
            ->cursorPaginate(PageCurseur::taille($requete))
            ->withQueryString();

        return Enveloppe::page($page, ArticleHubResource::collection($page->items()));
    }

    /**
     * `GET /hub/articles/{slug}`
     *
     * Le lien profond `verifall://hub/{slug}` aboutit ici. Un article dépublié
     * répond 404 : l'application doit alors montrer un écran de repli, et non
     * un contenu retiré de la circulation pour une bonne raison.
     */
    public function article(HubArticle $article): JsonResponse
    {
        if (! $article->is_published) {
            return Enveloppe::erreur(CodeErreur::Introuvable);
        }

        return Enveloppe::donnee(ArticleHubResource::make($article));
    }

    /**
     * `GET /hub/opportunities`
     *
     * Les offres EXPIRÉES restent visibles jusqu'à l'archivage planifié, mais
     * portent `expiree: true` : les faire disparaître à l'échéance ferait
     * croire à une erreur à qui vient de recevoir le lien par WhatsApp, alors
     * qu'il faut lui dire que c'est fermé.
     */
    public function opportunites(Request $requete): JsonResponse
    {
        $donnees = $requete->validate([
            'scope' => ['nullable', 'string', 'in:'.implode(',', array_column(PorteeOpportunite::cases(), 'value'))],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(TypeOpportunite::cases(), 'value'))],
            'limit' => ['nullable', 'integer'],
            'cursor' => ['nullable', 'string'],
        ]);

        $requeteSql = HubJob::where('is_published', true)->whereNull('closed_at');

        if (($donnees['scope'] ?? null) !== null) {
            $requeteSql->where('scope', $donnees['scope']);
        }

        if (($donnees['type'] ?? null) !== null) {
            $requeteSql->where('type', $donnees['type']);
        }

        $page = $requeteSql->orderByDesc('id')
            ->cursorPaginate(PageCurseur::taille($requete))
            ->withQueryString();

        return Enveloppe::page($page, OpportuniteResource::collection($page->items()));
    }

    /** `GET /hub/opportunities/{slug}` */
    public function opportunite(HubJob $opportunity): JsonResponse
    {
        if (! $opportunity->is_published) {
            return Enveloppe::erreur(CodeErreur::Introuvable);
        }

        return Enveloppe::donnee(OpportuniteResource::make($opportunity), meta: [
            /*
             | L'avertissement anti-frais est SYSTÉMATIQUE, pas conditionnel :
             | il est renvoyé par le serveur pour que le client n'ait pas à
             | décider quand l'afficher. Une offre d'emploi frauduleuse ne se
             | signale pas d'elle-même, et c'est justement la plus dangereuse.
             */
            'avertissement' => 'Une offre légitime ne demande jamais d\'argent. '
                .'Ne versez aucun frais de dossier, de formation ou de visa.',
        ]);
    }

    /** `POST /hub/opportunities/{slug}/report` */
    public function signaler(Request $requete, HubJob $opportunity, SignalementDeContenu $signalements): JsonResponse
    {
        $donnees = $requete->validate([
            'motif' => ['required', 'string', 'in:'.implode(',', array_keys(SignalementDeContenu::motifs()))],
            'detail' => ['nullable', 'string', 'max:1000'],
        ]);

        $signalement = $signalements->signaler(
            $this->utilisateur($requete),
            $opportunity,
            $donnees['motif'],
            $donnees['detail'] ?? null,
        );

        return Enveloppe::donnee([
            'uuid' => $signalement->uuid,
            'message' => 'Merci. Un modérateur va examiner cette annonce.',
        ], statut: 201);
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
