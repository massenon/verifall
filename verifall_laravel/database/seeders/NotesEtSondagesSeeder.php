<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Application\Contracts\DepotNotes;
use App\Core\Community\Services\ScoreDePontage;
use App\Enums\StatutNoteCommunaute;
use App\Models\CommunityNote;
use App\Models\CommunityNoteVote;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Notes de communauté et sondage — démonstration du pontage.
 *
 * Ce jeu n'est pas décoratif : il montre les TROIS états que l'écran doit
 * savoir rendre, et qu'une démonstration au hasard ne produit jamais.
 *
 *   · une note PUBLIÉE — approuvée par des gens qui, sur les autres notes,
 *     votent systématiquement à l'opposé ;
 *   · une note NON RETENUE — approuvée à l'unanimité d'un seul camp, ce qui
 *     est un vote majoritaire et pas un pont ;
 *   · une note EN ATTENTE — trop peu de votes pour que l'algorithme dise quoi
 *     que ce soit.
 *
 * Les votes sont posés un par un et le score est calculé par le VRAI service :
 * un statut écrit à la main dans un seeder finirait par diverger de ce que
 * l'algorithme produit, et la démonstration mentirait sur le produit.
 *
 * À passer APRÈS `CommunauteSeeder`, qui crée les sujets.
 */
final class NotesEtSondagesSeeder extends Seeder
{
    public function run(): void
    {
        $comptes = User::query()->limit(8)->get();

        if ($comptes->count() < 6) {
            $this->command?->warn('  Moins de six comptes : le pontage ne peut rien démontrer.');

            return;
        }

        $sujets = Thread::query()->orderBy('id')->limit(3)->get();

        if ($sujets->count() < 2) {
            $this->command?->warn('  Aucun sujet de forum : lancez CommunauteSeeder d\'abord.');

            return;
        }

        // Deux camps de trois personnes, qui vont voter à l'opposé partout.
        $campA = [$comptes[0], $comptes[1], $comptes[2]];
        $campB = [$comptes[3], $comptes[4], $comptes[5]];

        $historique = $sujets[0];
        $vitrine = $sujets[1];

        /*
         | HISTORIQUE. Trois notes sur lesquelles les deux camps s'opposent :
         | c'est ce qui rend la polarisation MESURABLE. Sans elles, aucune paire
         | de votants n'a d'accord connu, et toutes les notes resteraient en
         | attente — le démarrage à froid, exactement.
         */
        foreach ([true, true, false] as $rang => $positionDuCampA) {
            $note = $this->note(
                $historique,
                $comptes[6],
                'Point de contexte n° '.($rang + 1).' : le texte cité renvoie à un document antérieur.',
            );

            $this->voter($note, $campA, $positionDuCampA);
            $this->voter($note, $campB, ! $positionDuCampA);
        }

        // NOTE QUI FRANCHIT LE PONT : les deux camps la trouvent utile.
        $pont = $this->note(
            $vitrine,
            $comptes[6],
            'Le document partagé date de 2019 : il a été abrogé par un texte publié au Journal officiel en 2021. La capture qui circule en coupe la date.',
            ['https://sgg.gouv.bj/journal-officiel/', 'https://www.gouv.bj/'],
        );
        $this->voter($pont, [$campA[0], $campA[1]], true);
        $this->voter($pont, [$campA[2]], false);
        $this->voter($pont, [$campB[0], $campB[1]], true);

        // NOTE D'UN SEUL BORD : unanimité du camp A, rejet du camp B.
        $partisane = $this->note(
            $vitrine,
            $comptes[7] ?? $comptes[6],
            'Tout le monde sait très bien ce que cache cette publication, il suffit de regarder qui la relaie.',
            ['https://example.org/tribune'],
        );
        $this->voter($partisane, $campA, true);
        $this->voter($partisane, [$campB[0], $campB[1]], false);

        // NOTE EN ATTENTE : deux votes, en dessous du plancher.
        $attente = $this->note(
            $sujets[2] ?? $vitrine,
            $comptes[6],
            'La source primaire de cette affirmation n\'a pas été retrouvée ; l\'agence citée ne la mentionne pas.',
            ['https://www.afp.com/fr'],
        );
        $this->voter($attente, [$campA[0], $campB[0]], true);

        $bilan = app(ScoreDePontage::class)->recalculerTout();

        $this->command?->info(sprintf(
            '  Notes de communauté : %d évaluée(s) — %d publiée(s), %d en attente, %d non retenue(s).',
            $bilan['evaluees'],
            $bilan['publiees'],
            $bilan['en_attente'],
            $bilan['non_retenues'],
        ));

        $this->sondage($vitrine, $comptes);
    }

    /**
     * @param  list<string>  $sources
     */
    private function note(Thread $sujet, User $auteur, string $texte, array $sources = ['https://verifall.bj/']): CommunityNote
    {
        $existante = CommunityNote::where('thread_id', $sujet->id)
            ->where('user_id', $auteur->id)
            ->first();

        if ($existante !== null) {
            return $existante;
        }

        return CommunityNote::create([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $sujet->id,
            'user_id' => $auteur->id,
            'body' => $texte,
            'sources' => $sources,
            'status' => StatutNoteCommunaute::EnAttente->value,
        ]);
    }

    /**
     * @param  list<User>  $votants
     */
    private function voter(CommunityNote $note, array $votants, bool $utile): void
    {
        foreach ($votants as $votant) {
            CommunityNoteVote::updateOrCreate(
                ['community_note_id' => $note->id, 'user_id' => $votant->id],
                ['helpful' => $utile],
            );
        }

        app(DepotNotes::class)->recompter($note->refresh());
    }

    /**
     * Un sondage, laissé SANS AUCUN VOTE.
     *
     * C'est délibéré : la démonstration doit montrer l'écran d'un sondage
     * auquel on n'a pas encore répondu — celui où les résultats sont cachés.
     * Le peupler de votes ferait disparaître de la démonstration la règle
     * qu'on tient le plus à montrer.
     *
     * @param  Collection<int, User>  $comptes
     */
    private function sondage(Thread $sujet, Collection $comptes): void
    {
        if (Poll::where('thread_id', $sujet->id)->exists()) {
            return;
        }

        $sondage = Poll::create([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $sujet->id,
            'user_id' => $sujet->user_id ?? $comptes[0]->id,
            'question' => 'Avant de partager une information, que faites-vous en premier ?',
        ]);

        $reponses = [
            'Je cherche la source originale',
            'Je regarde qui la relaie',
            'Je la soumets à Vérif\'All',
            'Je ne partage pas si j\'ai un doute',
        ];

        foreach ($reponses as $position => $libelle) {
            PollOption::create([
                'uuid' => (string) Str::uuid(),
                'poll_id' => $sondage->id,
                'label' => $libelle,
                'position' => $position,
            ]);
        }

        $this->command?->info('  Sondage posé sur « '.Str::limit($sujet->title, 40).' », sans vote : les résultats restent cachés.');
    }
}
