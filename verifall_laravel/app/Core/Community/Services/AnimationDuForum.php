<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Core\Marketing\Emails\ExpediteurDeCourriels;
use App\Enums\ActionMotClef;
use App\Enums\AvisCommunaute;
use App\Enums\PreferenceNotification;
use App\Models\ContentLike;
use App\Models\ForumCategory;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\ThreadVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Écrire dans le forum : publier, répondre, voter, aimer.
 *
 * Les compteurs affichés — réponses, « j'aime », répartition des avis — sont
 * DÉNORMALISÉS sur le sujet, parce qu'un fil se lit cent fois plus qu'il ne
 * s'écrit. Ils ne sont donc jamais incrémentés à l'aveugle : chaque écriture
 * les RECALCULE dans la même transaction que le fait qui les change. Un
 * compteur qui dérive est pire qu'un compteur absent — il ment avec aplomb.
 */
final class AnimationDuForum
{
    /**
     * Repos entre deux courriels de réaction, PAR CONTENU.
     *
     * Six heures : assez long pour qu'un billet qui décolle ne produise qu'un
     * message, assez court pour qu'une réaction du lendemain se signale encore.
     */
    public const REPOS_REACTION_HEURES = 6;

    public function __construct(
        private readonly FiltreDeMots $filtre,
        private readonly SignalementDeContenu $signalements,
        private readonly Mentions $mentions,
        private readonly ExpediteurDeCourriels $courriels,
    ) {}

    /**
     * Confronte un message aux mots surveillés, et agit.
     *
     * Appelée AVANT l'écriture pour un blocage, et APRÈS pour un signalement :
     * dans le second cas, le message existe et c'est lui qu'on signale.
     *
     * @return list<string> les termes touchés, pour la suite du traitement
     *
     * @throws MessageBloque
     */
    private function passerAuFiltre(string $texte): array
    {
        $verdict = $this->filtre->examiner($texte);

        if ($verdict === null) {
            return [];
        }

        $this->filtre->comptabiliser($verdict['termes']);

        if ($verdict['action'] === ActionMotClef::Bloquer) {
            /*
             | Journalisé côté serveur, jamais rendu à l'auteur. Révéler le mot
             | qui a déclenché transformerait chaque refus en indice, et il
             | suffirait de quelques essais pour reconstituer la liste.
             */
            Log::info('Message bloqué par la modération de mots.', ['termes' => $verdict['termes']]);

            throw new MessageBloque($verdict['termes']);
        }

        return $verdict['termes'];
    }

    public function publier(
        User $auteur,
        string $titre,
        string $corps,
        ?ForumCategory $categorie = null,
        ?string $lienSignale = null,
    ): Thread {
        $titre = trim($titre);

        /*
         | LE FILTRE PASSE AVANT LA TRANSACTION.
         |
         | Un blocage lève, et lever DANS une transaction la ferait annuler —
         | ce qui serait sans conséquence ici, mais deviendrait faux le jour où
         | la transaction fera autre chose. Refuser avant d'ouvrir coûte moins
         | et se lit mieux.
         |
         | Le titre est examiné avec le corps : une injure dans un titre reste
         | une injure, et le titre est ce qu'on voit en premier dans la liste.
         */
        $termes = $this->passerAuFiltre($titre.' '.$corps);

        $sujet = DB::transaction(function () use ($auteur, $titre, $corps, $categorie, $lienSignale): Thread {
            return Thread::create([
                'uuid' => (string) Str::uuid(),
                'slug' => $this->slugLibre($titre),
                'user_id' => $auteur->id,
                'forum_category_id' => $categorie?->id,
                'title' => $titre,
                // Purifié AVANT persistance : une seule vue qui oublierait de
                // le refaire à l'affichage suffirait à exécuter ce qui a été
                // stocké.
                'body' => $this->assainir($corps),
                'source_url' => $this->lienValide($lienSignale),
                'last_activity_at' => now(),
            ]);
        });

        /*
         | Signalement automatique, APRÈS l'écriture : c'est le sujet lui-même
         | qu'on signale, il doit donc exister.
         |
         | Il rejoint la file de modération ordinaire, celle des signalements
         | citoyens. Une seconde file « signalements automatiques » aurait
         | produit une seconde habitude, et c'est la seconde qu'on oublie de
         | relever.
         */
        if ($termes !== []) {
            $this->signalements->signaler(
                // Aucun signaleur : c'est le filtre qui a repéré, pas un
                // lecteur. Passer l'auteur le ferait se dénoncer lui-même.
                null,
                $sujet,
                'mot_surveille',
                'Détecté automatiquement : '.implode(', ', $termes),
            );
        }

        /*
         | Les mentions sont notifiées APRÈS l'écriture, et hors transaction :
         | la notification touche un canal courriel, et une panne d'envoi ne
         | doit jamais annuler la publication de quelqu'un.
         */
        $this->mentions->notifier($sujet, $auteur, $titre.' '.$corps);

        return $sujet;
    }

    public function repondre(User $auteur, Thread $sujet, string $corps, ?Reply $parent = null): ?Reply
    {
        // Un fil verrouillé ne se rouvre pas par une réponse : la modération a
        // décidé, et contourner sa décision par l'API serait une faille.
        if ($sujet->is_locked) {
            return null;
        }

        // Le même filtre que pour un sujet : une réponse est aussi visible, et
        // c'est souvent là que les échanges dérapent.
        $termes = $this->passerAuFiltre($corps);

        $reponse = DB::transaction(function () use ($auteur, $sujet, $corps, $parent): Reply {
            $reponse = Reply::create([
                'uuid' => (string) Str::uuid(),
                'thread_id' => $sujet->id,
                'user_id' => $auteur->id,
                // Un seul niveau d'imbrication : répondre à une réponse imbriquée
                // rattache au même parent, sinon un fil devient illisible sur
                // téléphone.
                'parent_id' => $parent?->parent_id ?? $parent?->id,
                'body' => $this->assainir($corps),
            ]);

            $sujet->forceFill([
                'replies_count' => Reply::where('thread_id', $sujet->id)->count(),
                'last_activity_at' => now(),
            ])->save();

            return $reponse;
        });

        if ($termes !== []) {
            $this->signalements->signaler(
                null,
                $reponse,
                'mot_surveille',
                'Détecté automatiquement : '.implode(', ', $termes),
            );
        }

        $this->mentions->notifier($reponse, $auteur, $corps);

        return $reponse;
    }

    /**
     * Enregistre ou change l'avis de quelqu'un.
     *
     * Changer d'avis est LÉGITIME : on découvre un élément, on se ravise. Ce
     * qui ne l'est pas, c'est de voter deux fois — d'où la mise à jour plutôt
     * qu'une seconde ligne.
     *
     * Revoter le même avis le RETIRE : c'est le comportement attendu d'un
     * bouton qui reste allumé, et cela laisse la possibilité de se retirer d'un
     * sondage auquel on n'aurait pas dû participer.
     */
    public function voter(User $votant, Thread $sujet, AvisCommunaute $avis): ?ThreadVote
    {
        return DB::transaction(function () use ($votant, $sujet, $avis): ?ThreadVote {
            $existant = ThreadVote::where('thread_id', $sujet->id)
                ->where('user_id', $votant->id)
                ->lockForUpdate()
                ->first();

            $vote = null;

            if ($existant !== null && $existant->opinion === $avis) {
                $existant->delete();
            } elseif ($existant !== null) {
                $existant->forceFill(['opinion' => $avis->value])->save();
                $vote = $existant;
            } else {
                $vote = ThreadVote::create([
                    'thread_id' => $sujet->id,
                    'user_id' => $votant->id,
                    'opinion' => $avis->value,
                ]);
            }

            $this->recompterLesAvis($sujet);

            return $vote;
        });
    }

    /**
     * Bascule un « j'aime ».
     *
     * @param  Thread|Reply  $cible
     */
    public function aimer(User $utilisateur, Model $cible): bool
    {
        $aime = DB::transaction(function () use ($utilisateur, $cible): bool {
            $existant = ContentLike::where('user_id', $utilisateur->id)
                ->where('likeable_type', $cible->getMorphClass())
                ->where('likeable_id', $cible->getKey())
                ->lockForUpdate()
                ->first();

            $aime = $existant === null;

            if ($aime) {
                ContentLike::create([
                    'user_id' => $utilisateur->id,
                    'likeable_type' => $cible->getMorphClass(),
                    'likeable_id' => $cible->getKey(),
                ]);
            } else {
                $existant->delete();
            }

            // Recompté, jamais incrémenté : un incrément rejoué fait dériver le
            // compteur, et personne ne s'en aperçoit avant qu'il soit absurde.
            $cible->forceFill([
                'likes_count' => ContentLike::where('likeable_type', $cible->getMorphClass())
                    ->where('likeable_id', $cible->getKey())
                    ->count(),
            ])->save();

            return $aime;
        });

        if ($aime) {
            $this->prevenirDeLaReaction($utilisateur, $cible);
        }

        return $aime;
    }

    /**
     * Prévient l'auteur qu'on a réagi à son message.
     *
     * ## Quatre gardes, et aucune n'est décorative
     *
     * 1. **Seulement à la création.** Retirer son « j'aime » n'est pas un
     *    événement dont on prévient qui que ce soit.
     * 2. **Jamais à soi-même.** Aimer son propre billet est un geste courant —
     *    on ne s'écrit pas pour se l'annoncer.
     * 3. **Un repos par CONTENU**, pas par personne. C'est celle qui compte :
     *    cinquante réactions en une heure sur un billet qui décolle, ce serait
     *    cinquante courriels, et la personne désactiverait TOUTES ses
     *    notifications — y compris celles qui comptaient. Le repos règle du même
     *    coup le va-et-vient de quelqu'un qui aime, se ravise et réaime.
     * 4. **La préférence « activité du forum »**, appliquée par l'expéditeur.
     *    C'est exactement ce que cet interrupteur désigne.
     *
     * Hors transaction : `Mail::queue()` écrit dans la table `jobs`, qui
     * participerait au `commit` du « j'aime ». Un rollback laisserait un
     * courriel annonçant une réaction qui n'a pas eu lieu.
     *
     * @param  Thread|Reply  $cible
     */
    private function prevenirDeLaReaction(User $reacteur, Model $cible): void
    {
        $auteur = $cible->author;

        if ($auteur === null || $auteur->id === $reacteur->id) {
            return;
        }

        $derniere = $cible->reaction_notified_at;

        if ($derniere !== null && $derniere->diffInHours(now()) < self::REPOS_REACTION_HEURES) {
            return;
        }

        $sujet = $cible instanceof Reply ? $cible->thread : $cible;

        if ($sujet === null) {
            return;
        }

        $envoye = $this->courriels->envoyer(
            $auteur,
            'reaction_recue',
            [
                'prenom' => $auteur->name,
                'auteur' => $reacteur->name,
                'sujet' => (string) $sujet->title,
            ],
            route('citizen.thread', ['thread' => $sujet]),
            PreferenceNotification::ActiviteForum,
        );

        /*
         | La marque n'est posée QUE si le courriel est parti.
         |
         | La poser dans tous les cas ferait qu'un envoi éteint au catalogue —
         | et il l'est par défaut — consommerait quand même le repos : le jour
         | où l'administration l'allume, les six premières heures seraient
         | muettes sans raison visible.
         */
        if ($envoye) {
            $cible->forceFill(['reaction_notified_at' => now()])->save();
        }
    }

    private function recompterLesAvis(Thread $sujet): void
    {
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

    /**
     * Un slug qui n'est pas déjà pris.
     *
     * Deux personnes signalent la même rumeur le même jour, avec le même
     * titre : sans suffixe, la seconde publication échoue sur la contrainte
     * d'unicité — au visage de quelqu'un qui rend service.
     */
    private function slugLibre(string $titre): string
    {
        $base = Str::slug(Str::limit($titre, 80, ''));
        $base = $base !== '' ? $base : 'sujet';
        $slug = $base;

        while (Thread::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    /**
     * Le lien signalé, s'il en est un.
     *
     * Une chaîne qui n'est pas une URL n'est pas une source : la stocker
     * produirait un lien mort affiché comme une preuve.
     */
    private function lienValide(?string $lien): ?string
    {
        $lien = trim((string) $lien);

        if ($lien === '' || filter_var($lien, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $lien;
    }

    private function assainir(string $texte): string
    {
        return trim((string) Purifier::clean($texte, 'default'));
    }
}
