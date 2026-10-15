<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Core\Application\Contracts\DepotNotes;
use App\Enums\StatutSignalementContenu;
use App\Enums\VerdictFactCheck;
use App\Models\ContentReport;
use App\Models\HubArticle;
use App\Models\Thread;
use App\Models\User;

/**
 * La réputation d'un citoyen — adossée aux vérifications, jamais au volume.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CE QUI NE COMPTE PAS, ET POURQUOI
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Ni le nombre de messages, ni le nombre de « j'aime », ni l'ancienneté du
 * compte. Compter les messages récompense celui qui parle beaucoup ; sur une
 * plateforme de fact-checking, c'est exactement le comportement qu'il ne faut
 * pas encourager, parce qu'il est aussi celui du militant et du spammeur.
 *
 * CE QUI COMPTE est un FAIT vérifiable en base : un signalement retenu par la
 * modération, un sujet escaladé dont l'analyse a rendu un démenti, une note qui
 * a franchi le pont, un démenti repris en brève du Hub. Chacun de ces faits a
 * demandé le travail de quelqu'un d'autre pour être confirmé — c'est ce qui le
 * rend difficile à fabriquer.
 *
 * La réputation se CALCULE donc, et ne se saisit jamais : aucune colonne
 * `reputation` n'existe, et c'est délibéré. Une colonne se met à jour à la
 * main, dérive, et finit par affirmer quelque chose qu'aucun fait ne soutient.
 */
final class ReputationCitoyenne
{
    /**
     * Un sujet escaladé dont l'analyse a conclu au FAUX ou au TROMPEUR.
     *
     * Le fait le plus lourd du barème, et de loin : la personne a repéré une
     * désinformation avant la plateforme. C'est précisément le service que le
     * forum est censé rendre.
     */
    public const POINTS_DEMENTI = 5;

    /**
     * Un sujet escaladé dont l'analyse a conclu au VRAI ou au NON VÉRIFIABLE.
     *
     * Moins que le démenti, mais pas zéro : lever un doute est un résultat.
     * Mettre zéro pousserait à ne signaler que ce qu'on croit déjà faux, donc
     * à escalader ses convictions plutôt que ses doutes.
     */
    public const POINTS_VERIFICATION = 2;

    /** Un signalement de contenu retenu par la modération. */
    public const POINTS_SIGNALEMENT_RETENU = 3;

    /**
     * Un signalement REJETÉ retire des points.
     *
     * Sans coût, signaler tout ce qui déplaît devient gratuit et la file de
     * modération se remplit de règlements de comptes. Le coût reste inférieur
     * au gain d'un signalement retenu : quelqu'un qui se trompe une fois sur
     * deux reste bénéficiaire, parce qu'on veut qu'il continue de signaler.
     */
    public const POINTS_SIGNALEMENT_REJETE = -2;

    /** Une note de communauté qui a franchi le pont. */
    public const POINTS_NOTE_PUBLIEE = 2;

    /**
     * Un démenti issu de son signalement, repris en brève du Hub.
     *
     * Le signalement n'a pas seulement été vérifié : il a été DIFFUSÉ. Au
     * Bénin, une rumeur voyage en image sur WhatsApp — la reprise éditoriale
     * est le moment où le démenti se met à circuler à parité.
     */
    public const POINTS_REPRIS_AU_HUB = 5;

    public function __construct(private readonly DepotNotes $notes) {}

    /**
     * Le détail chiffré, avec les faits qui le composent.
     *
     * Les COMPTES sont rendus en même temps que le total, et l'écran les
     * affiche : un total seul ne se vérifie pas, et un chiffre qu'on ne peut
     * pas vérifier finit par n'être qu'une décoration.
     *
     * @return array{
     *     points: int,
     *     dementis: int,
     *     verifications: int,
     *     signalements_retenus: int,
     *     signalements_rejetes: int,
     *     notes_publiees: int,
     *     repris_au_hub: int,
     *     palier: string,
     * }
     */
    public function pour(User $citoyen): array
    {
        $escalades = $this->escaladesParVerdict($citoyen);

        $dementis = $escalades['dementis'];
        $verifications = $escalades['verifications'];

        $retenus = ContentReport::where('user_id', $citoyen->id)
            ->where('status', StatutSignalementContenu::Retenu->value)
            ->count();

        $rejetes = ContentReport::where('user_id', $citoyen->id)
            ->where('status', StatutSignalementContenu::Rejete->value)
            ->count();

        $notesPubliees = $this->notes->compterNotesPubliees($citoyen);
        $reprises = $this->reprisesAuHub($citoyen);

        $points = $dementis * self::POINTS_DEMENTI
            + $verifications * self::POINTS_VERIFICATION
            + $retenus * self::POINTS_SIGNALEMENT_RETENU
            + $rejetes * self::POINTS_SIGNALEMENT_REJETE
            + $notesPubliees * self::POINTS_NOTE_PUBLIEE
            + $reprises * self::POINTS_REPRIS_AU_HUB;

        // Jamais négatif à l'affichage : un score négatif serait une sanction
        // publique jamais prononcée par personne. Le malus freine la montée,
        // il ne crée pas de mauvaise réputation.
        $points = max(0, $points);

        return [
            'points' => $points,
            'dementis' => $dementis,
            'verifications' => $verifications,
            'signalements_retenus' => $retenus,
            'signalements_rejetes' => $rejetes,
            'notes_publiees' => $notesPubliees,
            'repris_au_hub' => $reprises,
            'palier' => $this->palier($points),
        ];
    }

    /**
     * Le palier affiché à côté du chiffre.
     *
     * Quatre paliers, et ils ne remplacent JAMAIS les faits : l'écran montre
     * toujours les comptes qui les produisent. Un libellé seul serait un
     * chiffre inventé au sens de la règle §5 du chantier — « Contributeur
     * confirmé » ne veut rien dire si l'on ne peut pas voir sur quoi il repose.
     *
     * Les bornes sont volontairement basses : à 10 points, on a par exemple
     * deux démenti nés de ses signalements — c'est déjà rare sur un forum, et
     * un palier qu'on n'atteint jamais ne récompense rien.
     */
    private function palier(int $points): string
    {
        return match (true) {
            $points >= 30 => 'Vigie',
            $points >= 10 => 'Contributeur confirmé',
            $points >= 1 => 'Contributeur',
            // Pas « débutant » ni « niveau 0 » : la personne n'a simplement
            // aucune vérification confirmée à son actif, et l'écran le dit.
            default => 'Aucune vérification confirmée',
        };
    }

    /**
     * Répartit les escalades de quelqu'un selon le verdict rendu.
     *
     * Lu sur le RAPPORT d'analyse, pas sur le sujet : le verdict officiel
     * apposé par un modérateur est un acte éditorial, et le compter ici
     * reviendrait à faire dépendre la réputation d'un citoyen de l'assiduité
     * d'un modérateur.
     *
     * @return array{dementis: int, verifications: int}
     */
    private function escaladesParVerdict(User $citoyen): array
    {
        $verdicts = Thread::query()
            ->join('verifications', 'verifications.id', '=', 'threads.verification_id')
            ->join('analysis_reports', 'analysis_reports.verification_id', '=', 'verifications.id')
            ->where('threads.escalated_by', $citoyen->id)
            ->whereNull('threads.deleted_at')
            ->pluck('analysis_reports.verdict');

        $dementis = $verdicts
            ->filter(static fn (mixed $verdict): bool => in_array(
                $verdict,
                [VerdictFactCheck::Faux->value, VerdictFactCheck::Trompeur->value],
                true,
            ))
            ->count();

        return [
            'dementis' => $dementis,
            'verifications' => $verdicts->count() - $dementis,
        ];
    }

    /**
     * Combien de ses escalades ont été reprises en brève du Hub.
     *
     * Le rapprochement se fait par EMPREINTE de contenu — c'est le seul lien
     * qui existe entre une vérification et un fait consigné par l'équipe. Il
     * est exact quand il répond, et muet sinon : à défaut d'empreinte, on ne
     * compte rien plutôt que de deviner.
     */
    private function reprisesAuHub(User $citoyen): int
    {
        $empreintes = Thread::query()
            ->join('verifications', 'verifications.id', '=', 'threads.verification_id')
            ->where('threads.escalated_by', $citoyen->id)
            ->whereNull('threads.deleted_at')
            ->whereNotNull('verifications.content_fingerprint')
            ->pluck('verifications.content_fingerprint')
            ->all();

        if ($empreintes === []) {
            return 0;
        }

        return HubArticle::query()
            ->join('verified_facts', 'verified_facts.id', '=', 'hub_articles.verified_fact_id')
            ->where('hub_articles.is_published', true)
            ->whereNull('hub_articles.deleted_at')
            ->whereIn('verified_facts.content_fingerprint', $empreintes)
            ->distinct()
            ->count('hub_articles.id');
    }
}
