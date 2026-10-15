<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Le sondage d'un sujet : une question, des options, un vote par personne.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LES RÉSULTATS NE SONT VISIBLES QU'APRÈS AVOIR VOTÉ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Ce n'est pas une coquetterie d'interface, et la règle vit dans le SERVICE
 * plutôt que dans la vue : montrer le résultat avant le vote oriente tous les
 * votes suivants. C'est un effet mesuré et robuste — on se range à ce que la
 * majorité affichée semble penser — et sur un forum où l'on discute de rumeurs
 * politiques, il transforme un sondage en machine à fabriquer du consensus.
 *
 * Une vue qui oublierait la condition suffirait à annuler la protection : le
 * service ne rend donc tout simplement PAS les chiffres tant que la personne
 * n'a pas voté.
 */
final class Sondages
{
    /** Au moins deux options : une question à une seule réponse n'en est pas une. */
    public const OPTIONS_MINIMUM = 2;

    /**
     * Au plus six options.
     *
     * Au-delà, les parts deviennent illisibles sur un téléphone — l'écran de
     * référence au Bénin — et le sondage se transforme en formulaire.
     */
    public const OPTIONS_MAXIMUM = 6;

    /**
     * Attache un sondage à un sujet.
     *
     * @param  list<string>  $options
     *
     * @throws ValidationException
     */
    public function creer(User $auteur, Thread $sujet, string $question, array $options): Poll
    {
        $question = trim($question);

        if (mb_strlen($question) < 5) {
            throw ValidationException::withMessages([
                'question' => 'Posez une question complète.',
            ]);
        }

        $libelles = $this->libellesUtiles($options);

        if (count($libelles) < self::OPTIONS_MINIMUM) {
            throw ValidationException::withMessages([
                'options' => 'Proposez au moins deux réponses distinctes.',
            ]);
        }

        if (Poll::where('thread_id', $sujet->id)->exists()) {
            // Un sujet, un sondage : deux questions concurrentes sur le même
            // fil et le lecteur ne sait plus laquelle engage la discussion.
            throw ValidationException::withMessages([
                'question' => 'Ce sujet porte déjà un sondage.',
            ]);
        }

        return DB::transaction(function () use ($auteur, $sujet, $question, $libelles): Poll {
            $sondage = Poll::create([
                'uuid' => (string) Str::uuid(),
                'thread_id' => $sujet->id,
                'user_id' => $auteur->id,
                'question' => $question,
            ]);

            foreach (array_slice($libelles, 0, self::OPTIONS_MAXIMUM) as $position => $libelle) {
                PollOption::create([
                    'uuid' => (string) Str::uuid(),
                    'poll_id' => $sondage->id,
                    'label' => $libelle,
                    'position' => $position,
                ]);
            }

            return $sondage->refresh();
        });
    }

    /**
     * Enregistre le vote de quelqu'un.
     *
     * Un second vote REMPLACE le premier — changer d'avis est légitime, peser
     * deux fois ne l'est pas. Les compteurs sont RECALCULÉS et jamais
     * incrémentés : un incrément rejoué fait dériver le résultat, et un
     * sondage qui ment avec aplomb est pire qu'un sondage absent.
     *
     * @throws ValidationException
     */
    public function voter(User $votant, Poll $sondage, PollOption $option): PollVote
    {
        if ((int) $option->poll_id !== (int) $sondage->id) {
            throw ValidationException::withMessages([
                'option' => 'Cette réponse n\'appartient pas à ce sondage.',
            ]);
        }

        if (! $sondage->estOuvert()) {
            throw ValidationException::withMessages([
                'option' => 'Ce sondage est clos.',
            ]);
        }

        return DB::transaction(function () use ($votant, $sondage, $option): PollVote {
            $existant = PollVote::where('poll_id', $sondage->id)
                ->where('user_id', $votant->id)
                ->lockForUpdate()
                ->first();

            if ($existant !== null) {
                $existant->forceFill(['poll_option_id' => $option->id])->save();
                $vote = $existant;
            } else {
                $vote = PollVote::create([
                    'poll_id' => $sondage->id,
                    'poll_option_id' => $option->id,
                    'user_id' => $votant->id,
                ]);
            }

            $this->recompter($sondage);

            return $vote;
        });
    }

    public function voteDe(Poll $sondage, ?User $votant): ?PollVote
    {
        if ($votant === null) {
            return null;
        }

        return PollVote::where('poll_id', $sondage->id)
            ->where('user_id', $votant->id)
            ->first();
    }

    public function aVote(Poll $sondage, ?User $votant): bool
    {
        return $this->voteDe($sondage, $votant) !== null;
    }

    /**
     * Les résultats — ou RIEN tant que la personne n'a pas voté.
     *
     * `null` n'est pas une erreur : c'est la réponse juste à « montre-moi les
     * chiffres » posée par quelqu'un qui ne s'est pas encore prononcé.
     * L'écran, lui, affiche les options à choisir.
     *
     * Un sondage CLOS rend ses résultats à tout le monde : il n'y a plus de
     * vote à orienter, et cacher les chiffres d'un sondage terminé reviendrait
     * à effacer ce que des gens ont pris le temps de dire.
     *
     * @return list<array{uuid: string, label: string, votes: int, part: int, choisie: bool}>|null
     */
    public function resultats(Poll $sondage, ?User $lecteur): ?array
    {
        $vote = $this->voteDe($sondage, $lecteur);

        if ($vote === null && $sondage->estOuvert()) {
            return null;
        }

        $total = (int) $sondage->votes_count;

        return $sondage->options()->get()
            ->map(static fn (PollOption $option): array => [
                'uuid' => (string) $option->uuid,
                'label' => (string) $option->label,
                'votes' => (int) $option->votes_count,
                'part' => $option->part($total),
                'choisie' => $vote !== null && (int) $vote->poll_option_id === (int) $option->id,
            ])
            ->values()
            ->all();
    }

    public function optionParUuid(Poll $sondage, string $uuid): ?PollOption
    {
        return PollOption::where('poll_id', $sondage->id)->where('uuid', $uuid)->first();
    }

    public function pourSujet(Thread $sujet): ?Poll
    {
        return Poll::with('options')->where('thread_id', $sujet->id)->first();
    }

    /** Ferme un sondage — les résultats restent lisibles de tous. */
    public function fermer(Poll $sondage): Poll
    {
        $sondage->forceFill(['is_closed' => true])->save();

        return $sondage->refresh();
    }

    private function recompter(Poll $sondage): void
    {
        $comptes = PollVote::where('poll_id', $sondage->id)
            ->selectRaw('poll_option_id, COUNT(*) as total')
            ->groupBy('poll_option_id')
            ->pluck('total', 'poll_option_id');

        foreach ($sondage->options()->get() as $option) {
            $option->forceFill([
                'votes_count' => (int) $comptes->get($option->id, 0),
            ])->save();
        }

        $sondage->forceFill([
            'votes_count' => PollVote::where('poll_id', $sondage->id)->count(),
        ])->save();
    }

    /**
     * Nettoie et dédoublonne les libellés proposés.
     *
     * Deux options au même texte donneraient un sondage où l'on ne peut pas
     * savoir ce qu'on a choisi.
     *
     * @param  list<string>  $options
     * @return list<string>
     */
    private function libellesUtiles(array $options): array
    {
        $libelles = [];

        foreach ($options as $option) {
            $libelle = trim(strip_tags($option));

            if ($libelle === '') {
                continue;
            }

            $libelle = Str::limit($libelle, 200, '');

            if (! in_array($libelle, $libelles, true)) {
                $libelles[] = $libelle;
            }
        }

        return $libelles;
    }
}
