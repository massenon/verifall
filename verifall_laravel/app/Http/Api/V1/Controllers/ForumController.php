<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Community\Services\AnimationDuForum;
use App\Core\Community\Services\SignalementDeContenu;
use App\Enums\AvisCommunaute;
use App\Http\Api\V1\Resources\ReponseResource;
use App\Http\Api\V1\Resources\SujetResource;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Http\Api\V1\Support\Filtre;
use App\Http\Api\V1\Support\PageCurseur;
use App\Models\ForumCategory;
use App\Models\Thread;
use App\Models\ThreadVote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le forum citoyen.
 *
 * `AnimationDuForum` porte tout ce qui compte : la purification du HTML avant
 * persistance, le refus de répondre dans un fil verrouillé, l'unicité du vote,
 * le recalcul des compteurs dans la même transaction que le fait qui les
 * change. Rien de tout cela n'est réécrit ici — un forum où le mobile
 * publierait sans purifier serait une porte d'injection ouverte le jour de la
 * livraison.
 */
final class ForumController
{
    /**
     * `GET /forum/threads`
     *
     * Ordonné par dernière activité, comme l'écran web. Pagination par
     * curseur : c'est LA liste qui bouge le plus du produit, et un décalage y
     * ferait sauter des sujets à chaque nouvelle réponse publiée.
     */
    public function sujets(Request $requete): JsonResponse
    {
        $donnees = $requete->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer'],
            'cursor' => ['nullable', 'string'],
        ]);

        $requeteSql = Thread::with(['author', 'category'])->withCount('replies');

        $categorie = Filtre::valeur($requete, 'category');

        if ($categorie !== null) {
            $requeteSql->whereHas('category', fn ($c) => $c->where('slug', $categorie));
        }

        if (($donnees['q'] ?? null) !== null && trim((string) $donnees['q']) !== '') {
            $recherche = trim((string) $donnees['q']);
            $requeteSql->where('title', 'like', '%'.$recherche.'%');
        }

        /*
         | Tri sur `id` et non sur `last_activity_at` : le curseur exige une
         | clé strictement ordonnée et unique. Trier sur une date que deux
         | sujets peuvent partager ferait disparaître l'un des deux au passage
         | de page — silencieusement, et seulement en production.
         */
        $page = $requeteSql->orderByDesc('id')
            ->cursorPaginate(PageCurseur::taille($requete))
            ->withQueryString();

        return Enveloppe::page($page, SujetResource::collection($page->items()), [
            'categories' => ForumCategory::orderBy('name')->get(['slug', 'name'])
                ->map(fn (ForumCategory $c): array => ['slug' => $c->slug, 'nom' => $c->name]),
        ]);
    }

    /** `POST /forum/threads` */
    public function publier(Request $requete, AnimationDuForum $forum): JsonResponse
    {
        $donnees = $requete->validate([
            'titre' => ['required', 'string', 'min:8', 'max:160'],
            'corps' => ['required', 'string', 'min:20', 'max:5000'],
            'category' => ['nullable', 'string', 'max:80'],
            // Ancien nom, accepté pour une version. Voir ci-dessous.
            'categorie' => ['nullable', 'string', 'max:80'],
            'lien_signale' => ['nullable', 'url', 'max:2048'],
        ]);

        /*
         | UN SEUL NOM POUR UNE SEULE CHOSE. Les cinq listes filtrables de
         | l'API attendent `category` ; ce corps attendait `categorie`. Un
         | client qui a appris `category` sur cinq écrans se trompe sur le
         | sixième — et le champ est alors ignoré EN SILENCE : le sujet part
         | sans rubrique, l'appel répond 201, et personne ne voit rien. C'est
         | le pire des échecs, puisqu'il produit un résultat plausible.
         |
         | `category` est donc le nom canonique. `categorie` reste accepté le
         | temps d'une version pour ne casser aucun client déjà écrit contre
         | la première rédaction du contrat ; il disparaîtra en v2.
         */
        $categorie = Filtre::valeur($requete, 'category')
            ?? Filtre::valeur($requete, 'categorie');

        $sujet = $forum->publier(
            $this->utilisateur($requete),
            $donnees['titre'],
            $donnees['corps'],
            $categorie !== null ? ForumCategory::where('slug', $categorie)->first() : null,
            $donnees['lien_signale'] ?? null,
        );

        return Enveloppe::donnee(
            SujetResource::make($sujet->loadMissing(['author', 'category'])),
            statut: 201,
        );
    }

    /** `GET /forum/threads/{slug}` */
    public function sujet(Request $requete, Thread $thread): JsonResponse
    {
        $thread->loadMissing(['author', 'category', 'replies.author'])->loadCount('replies');

        // Mon vote accompagne le sujet : sans lui, l'écran ne saurait pas
        // quel bouton montrer comme déjà choisi, et proposerait de voter à
        // quelqu'un qui a déjà voté.
        $thread->setAttribute('mon_vote', ThreadVote::where('thread_id', $thread->id)
            ->where('user_id', $this->utilisateur($requete)->id)
            ->value('opinion'));

        return Enveloppe::donnee(SujetResource::make($thread));
    }

    /**
     * `POST /forum/threads/{slug}/vote`
     *
     * UN SEUL VOTE PAR CITOYEN — la contrainte est en base, pas dans le
     * client. Le vote optimiste du prototype est donc sans danger : la réponse
     * du serveur fait foi et porte la répartition recalculée.
     */
    public function voter(Request $requete, Thread $thread, AnimationDuForum $forum): JsonResponse
    {
        $donnees = $requete->validate([
            'avis' => ['required', 'string', 'in:'.implode(',', array_column(AvisCommunaute::cases(), 'value'))],
        ]);

        $vote = $forum->voter(
            $this->utilisateur($requete),
            $thread,
            AvisCommunaute::from($donnees['avis']),
        );

        if ($vote === null) {
            return Enveloppe::erreur(
                CodeErreur::ActionImpossible,
                'Ce sujet est verrouillé : il ne reçoit plus de vote.',
            );
        }

        $frais = $thread->fresh();

        return Enveloppe::donnee([
            'mon_vote' => $vote->opinion->value,
            'votes' => $frais?->repartitionDesAvis(),
            'votes_total' => $frais?->totalDesVotes(),
        ]);
    }

    /** `POST /forum/threads/{slug}/replies` */
    public function repondre(Request $requete, Thread $thread, AnimationDuForum $forum): JsonResponse
    {
        $donnees = $requete->validate([
            'corps' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $reponse = $forum->repondre($this->utilisateur($requete), $thread, $donnees['corps']);

        if ($reponse === null) {
            // Un fil verrouillé ne se rouvre pas par une réponse : la
            // modération a décidé, et contourner sa décision par l'API serait
            // une faille.
            return Enveloppe::erreur(
                CodeErreur::ActionImpossible,
                'Ce sujet est verrouillé : il ne reçoit plus de réponse.',
            );
        }

        return Enveloppe::donnee(
            ReponseResource::make($reponse->loadMissing('author')),
            statut: 201,
        );
    }

    /**
     * `POST /forum/threads/{slug}/report`
     *
     * Un signalement est un ACTE, pas un vote : cinq personnes qui signalent
     * le même message ne le rendent pas cinq fois plus répréhensible. Le
     * service rend le signalement existant si l'on signale deux fois — du
     * point de vue de qui clique, le résultat voulu est atteint.
     */
    public function signaler(Request $requete, Thread $thread, SignalementDeContenu $signalements): JsonResponse
    {
        $donnees = $requete->validate([
            'motif' => ['required', 'string', 'in:'.implode(',', array_keys(SignalementDeContenu::motifs()))],
            'detail' => ['nullable', 'string', 'max:1000'],
        ]);

        $signalement = $signalements->signaler(
            $this->utilisateur($requete),
            $thread,
            $donnees['motif'],
            $donnees['detail'] ?? null,
        );

        return Enveloppe::donnee([
            'uuid' => $signalement->uuid,
            'message' => 'Merci. Un modérateur va examiner ce contenu.',
        ], statut: 201);
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
