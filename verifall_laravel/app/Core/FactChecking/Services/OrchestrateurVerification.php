<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Economy\Services\EconomyService;
use App\Core\FactChecking\Contracts\AnalysisAdapterInterface;
use App\Core\FactChecking\ValueObjects\AffirmationExtraite;
use App\Core\FactChecking\ValueObjects\ContexteDuClaim;
use App\Core\FactChecking\ValueObjects\ElementDuPaquet;
use App\Core\FactChecking\ValueObjects\IndiceHaine;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\ActeDeLangage;
use App\Enums\CompositionDuRapport;
use App\Enums\EtapePipeline;
use App\Enums\MotifMouvementUnite;
use App\Enums\PositionPreuve;
use App\Enums\StatutVerification;
use App\Enums\VerdictFactCheck;
use App\Events\VerificationAchevee;
use App\Jobs\AnalyserVerification;
use App\Models\Claim;
use App\Models\Verification;
use App\Models\VerifiedFact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fait tourner les moteurs, agrège leurs avis, écrit le rapport.
 *
 * Deux principes, dans cet ordre.
 *
 * 1. AUCUN RAPPORT CREUX. Quand trop peu de moteurs répondent, la plateforme ne
 *    rend pas un « verdict indisponible » : elle REPROGRAMME l'analyse et le
 *    dit. Un rapport creux est la pire réponse possible — la personne repart
 *    sans rien, avec l'impression que la plateforme ne marche pas, alors que le
 *    problème est passager.
 *
 * 2. LE RAPPORT SORT TOUJOURS, dès lors qu'il y a matière. Un moteur en panne,
 *    deux moteurs qui se contredisent, aucune synthèse rédigée : rien de cela
 *    ne produit un écran vide. Ce qui change, c'est ce que le rapport ANNONCE —
 *    un verdict, ou un arbitrage en cours.
 *
 * Au bout des tentatives, si rien n'aboutit, les unités sont rendues. Une
 * personne qui a dépensé repart avec un rapport ou avec ses unités ; jamais ni
 * l'un ni l'autre, jamais un simulacre.
 */
final class OrchestrateurVerification
{
    public function __construct(
        private readonly FactCheckScoringService $scoring,
        private readonly EconomyService $economie,
        private readonly RegistreDesMoteurs $registre,
        private readonly ReponseDejaConnue $connues,
        private readonly CoherenceRapport $coherence,
        private readonly ExtractionDesAffirmations $extraction,
        private readonly CalculDeLaComposition $composition,
        private readonly AdjudicationDesPreuves $adjudication,
        private readonly DomaineDeLaSource $domaines = new DomaineDeLaSource,
        private readonly CollecteurDePreuves $collecteur = new CollecteurDePreuves,
    ) {}

    /**
     * Chronologie horodatée de l'analyse en cours.
     *
     * @var list<array{etape: string, libelle: string, at: string}>
     */
    private array $chronologie = [];

    public function executer(Verification $verification): Verification
    {
        $this->chronologie = [];

        if (! $this->prendreEnMain($verification)) {
            return $verification->refresh();
        }

        $this->franchir($verification, EtapePipeline::Reception);

        /*
         | Avant de payer pour produire une réponse, on regarde si elle existe
         | déjà. Une rumeur virale est signalée des centaines de fois : relancer
         | douze moteurs payants à chaque signalement coûterait cher pour rendre
         | douze fois la même chose, et ferait attendre chacun pour rien.
         */
        $reprise = $this->reutiliser($verification);

        if ($reprise !== null) {
            return $reprise;
        }

        $this->franchir($verification, EtapePipeline::Preparation);

        /*
         | PHASE 2 — une vérification porte 0..N claims, le verdict s'attache
         | au claim (contrat V-8). Idempotent : sur une reprise, les claims
         | existent déjà et ne sont PAS ré-extraits — une seconde extraction
         | pourrait découper différemment le même contenu et violer la
         | contrainte d'unicité (verification_id, position).
         */
        $claims = $this->chargerOuExtraireLesClaims($verification);

        $this->franchir($verification, EtapePipeline::Sources);
        $this->franchir($verification, EtapePipeline::AnalyseIA);

        $verifiables = $claims->filter(static fn (Claim $c): bool => $c->estVerifiable())->values();

        /*
         | ZÉRO CLAIM VÉRIFIABLE. Le contenu entier n'est qu'une opinion, une
         | prédiction, une question, ou ne porte aucune proposition — décision
         | V-2 : NON_FACTUEL est un état d'ingestion, pas un verdict. Aucun
         | moteur n'est interrogé, et les unités sont rendues : facturer
         | l'absence de proposition vérifiable serait indéfendable.
         */
        if ($verifiables->isEmpty()) {
            return $this->rapportInsuffisant($verification, $claims);
        }

        /** @var array<int, list<VerdictMoteur>> $resultatsParClaim indexés par id de claim */
        $resultatsParClaim = [];
        /** @var array<int, DecisionFactCheck> $decisionsParClaim */
        $decisionsParClaim = [];
        /** @var array<int, string> $synthesesParClaim */
        $synthesesParClaim = [];
        /**
         * Les claims dont le verdict a été tranché par une preuve décisive
         * plutôt que par `decider()` — voir plus bas pourquoi le contrôle
         * « une preuve forte contredit » doit les ignorer.
         *
         * @var array<int, true>
         */
        $resolusParPreuveParClaim = [];

        foreach ($verifiables as $claim) {
            $sac = new SacDePreuves(
                affirmation: $claim->texte,
                type: $verification->type,
                contexte: $verification->context,
                cheminFichier: $verification->file_path,
                // Phase 3 : `null` seulement si l'extraction n'a vraiment pas
                // pu classer ce claim — ne devrait pas arriver pour une
                // affirmation, mais ne bloque pas l'analyse si ça arrive.
                contexteTemporel: $claim->portee_temporelle !== null
                    ? new ContexteDuClaim($claim->portee_temporelle, now())
                    : null,
            );

            $resultats = $this->interrogerLesMoteurs($sac, $verification);
            $decision = $this->scoring->decider($resultats);
            $preuveDecisive = null;
            $preuvesDuClaim = array_merge(...array_map(
                static fn (VerdictMoteur $r): array => $r->preuves,
                $resultats,
            ));

            /*
             | NEWINSTRUCTION28 — « 3 moteurs qui votent ne doivent pas
             | pouvoir battre une preuve institutionnelle primaire, directe,
             | actuelle et temporellement pertinente. »
             |
             | `decider()` reste l'UNIQUE source du verdict quand les moteurs
             | s'accordent ou qu'aucune preuve ne permet de trancher — ce
             | correctif n'y touche pas. Il n'intervient que dans le cas
             | « CONTRADICTOIRE » (Vrai et Faux tous deux en tête, aucun
             | moteur minoritaire) : AVANT ce correctif, ce cas retombait
             | systématiquement sur Trompeur + arbitrage humain, y compris
             | quand l'Evidence Bundle contenait deux preuves institutionnelles
             | datées qui se contredisent — exactement le cas d'une
             | succession à une fonction publique. `preuveDecisive()` ne
             | tranche QUE si une preuve est à la fois la plus RÉCENTE entre
             | deux contradictoires ET assez FIABLE pour trancher seule ; sinon
             | elle rend `null` et rien ne change.
             */
            if ($decision->statut === StatutVerification::RevueHumaine && ! $decision->partielle) {
                $preuveDecisive = $this->adjudication->preuveDecisive($preuvesDuClaim);
                $verdictDecisif = $preuveDecisive !== null
                    ? $this->adjudication->verdictDeLaPreuveDecisive($preuveDecisive)
                    : null;

                if ($preuveDecisive !== null && $verdictDecisif !== null) {
                    $decision = DecisionFactCheck::tranchee($verdictDecisif, $preuveDecisive->fiabilite, $decision->scores);
                    $resolusParPreuveParClaim[$claim->id] = true;
                }
            }

            $resultatsParClaim[$claim->id] = $resultats;
            $decisionsParClaim[$claim->id] = $decision;
            // Calculée MAINTENANT, pas après coup : c'est la même chaîne qui
            // sert à la fois de contrôle de cohérence et, pour un rapport à
            // claim unique, de synthèse persistée — exactement le
            // comportement d'avant cette phase.
            $synthesesParClaim[$claim->id] = $this->synthese($verification, $decision, $resultats, $preuveDecisive);
        }

        $this->franchir($verification, EtapePipeline::Consolidation);

        /*
         | AUCUN RAPPORT CREUX. Un seul claim partiel suffit à reprogrammer
         | TOUTE la vérification — la granularité de la reprise reste la
         | vérification entière, pas le claim : refaire tourner les moteurs
         | d'un seul claim en laissant les autres acquis introduirait un état
         | que rien dans le schéma ne distingue aujourd'hui d'une première
         | passe, et la file de reprise existante n'a pas cette granularité.
         */
        $partiels = array_filter(
            $decisionsParClaim,
            static fn (DecisionFactCheck $d): bool => $d->partielle,
        );

        if ($partiels !== [] && $this->peutEncoreReessayer($verification)) {
            $premier = array_key_first($partiels);

            return $this->programmerUneReprise($verification, $this->motifDeReprise($resultatsParClaim[$premier]));
        }

        /*
         | Contrôle de cohérence PAR CLAIM, avant publication. Priorité entre
         | les issues, du plus au moins sévère : une incohérence l'emporte sur
         | une contradiction entre moteurs, qui l'emporte sur une simple
         | analyse partielle restée sans réponse après épuisement des
         | tentatives — les trois peuvent survenir sur des claims différents
         | du même contenu.
         */
        $statut = StatutVerification::Terminee;
        $reserve = null;

        foreach ($verifiables as $claim) {
            $decision = $decisionsParClaim[$claim->id];

            if ($decision->statut === StatutVerification::RevueHumaine && $reserve === null) {
                $statut = StatutVerification::RevueHumaine;
                $reserve = $decision->reserve;
            } elseif ($decision->partielle && $reserve === null) {
                $reserve = $decision->reserve;
            }
        }

        foreach ($verifiables as $claim) {
            /*
             | NEWINSTRUCTION28 — un claim déjà résolu par `preuveDecisive()`
             | saute les DEUX contrôles ci-dessous, et pour la même raison
             | pour chacun : ils vérifient que le verdict suit les MOTEURS et
             | n'est pas démenti par une preuve forte — exactement ce que
             | cette résolution vient déjà d'arbitrer autrement, en faveur de
             | la preuve institutionnelle la plus RÉCENTE entre deux
             | contradictoires. Les rejouer ici ferait buter le rapport sur
             | la minorité de moteurs ou sur la preuve la plus ANCIENNE des
             | deux — exactement ce que la résolution temporelle a écarté.
             */
            if (isset($resolusParPreuveParClaim[$claim->id])) {
                continue;
            }

            $decision = $decisionsParClaim[$claim->id];
            $resultats = $resultatsParClaim[$claim->id];

            $incoherence = $this->coherence->incoherence(
                $decision->verdict,
                $synthesesParClaim[$claim->id],
                $resultats,
                $this->positionsDesPreuves($resultats),
            );

            if ($incoherence !== null) {
                $statut = StatutVerification::RevueHumaine;
                $reserve = $incoherence.' Ce rapport est soumis à un arbitrage humain.';

                Log::warning('Rapport incohérent, publication bloquée', [
                    'verification' => $verification->uuid,
                    'claim' => $claim->id,
                    'verdict' => $decision->verdict->value,
                    'motif' => $incoherence,
                ]);

                break;
            }

            /*
             | PHASE 8 — « les preuves votent », à poids réel. Le comptage à
             | poids égal de `CoherenceRapport` peut laisser passer une preuve
             | institutionnelle unique renversée par plusieurs sources faibles
             | qui vont dans l'autre sens. Ce contrôle-ci voit précisément ce
             | cas, et escalade plutôt que de publier un verdict qu'une seule
             | preuve forte contredit.
             */
            $preuves = array_merge(...array_map(
                static fn (VerdictMoteur $r): array => $r->preuves,
                $resultats,
            ));

            if ($this->adjudication->unePreuveForteContredit($preuves, $decision->verdict)) {
                $statut = StatutVerification::RevueHumaine;
                $reserve = 'Une source fiable contredit le verdict retenu par les moteurs. Ce rapport est soumis à un arbitrage humain.';

                Log::warning('Rapport contredit par une preuve forte, publication bloquée', [
                    'verification' => $verification->uuid,
                    'claim' => $claim->id,
                    'verdict' => $decision->verdict->value,
                ]);

                break;
            }
        }

        $verdicts = array_map(
            static fn (Claim $c): VerdictFactCheck => $decisionsParClaim[$c->id]->verdict,
            $verifiables->all(),
        );

        $compositionStatus = $this->composition->pour($verdicts);
        $verdictScalaire = $this->composition->verdictScalaire($verdicts);

        $confiance = (int) round(array_sum(array_map(
            static fn (DecisionFactCheck $d): int => $d->confiance,
            $decisionsParClaim,
        )) / count($decisionsParClaim));

        /*
         | PHASE 8 — stockée à côté du détail par verdict, jamais à sa place :
         | `consensus` reste EXACTEMENT ce que rendait `decider()` avant cette
         | phase, et `corroboration` est additive. Un seul claim pour
         | l'instant, comme `scores` lui-même : au-delà, aucune des deux
         | notions ne se résume à un nombre unique sans perdre son sens.
         */
        $scoresDuRapport = null;

        if ($verifiables->count() === 1) {
            $claimUnique = $verifiables->first();
            $preuvesDuClaim = array_merge(...array_map(
                static fn (VerdictMoteur $r): array => $r->preuves,
                $resultatsParClaim[$claimUnique->id],
            ));

            $scoresDuRapport = [
                'consensus' => $decisionsParClaim[$claimUnique->id]->scores,
                'corroboration' => $this->adjudication->corroboration(
                    $preuvesDuClaim,
                    $decisionsParClaim[$claimUnique->id]->verdict,
                ),
            ];
        }

        /*
         | SYNTHÈSE : identique au comportement d'avant cette phase quand il
         | n'y a qu'un seul claim — c'est le cas de la quasi-totalité des
         | contenus soumis. Au-delà d'un claim, aucune synthèse composée par
         | moteur ne convient plus : soit tous concordent et la synthèse à
         | claim unique, nourrie des résultats de TOUS les claims, reste
         | honnête ; soit ils divergent et seule une énumération par claim
         | dit la vérité.
         */
        $synthese = match (true) {
            $verifiables->count() === 1 => $synthesesParClaim[$verifiables->first()->id],
            $compositionStatus === CompositionDuRapport::Mixte => $this->syntheseMixte($verifiables, $decisionsParClaim),
            default => $this->synthese(
                $verification,
                $decisionsParClaim[$verifiables->first()->id],
                array_merge(...array_values($resultatsParClaim)),
            ),
        };

        // Rhetorique du contenu SOUMIS, mesuree independamment de sa
        // veracite : une information peut etre exacte et formulee pour
        // stigmatiser. Propriété du CONTENU entier, pas d'un claim isolé —
        // tous les retours de tous les claims y contribuent.
        $haine = IndiceHaine::agreger(array_map(
            static fn (VerdictMoteur $r): IndiceHaine => $r->indiceHaine,
            array_merge(...array_values($resultatsParClaim)),
        ));

        $this->franchir($verification, EtapePipeline::Verdict);

        return DB::transaction(function () use (
            $verification, $verifiables, $resultatsParClaim, $decisionsParClaim,
            $verdictScalaire, $compositionStatus, $confiance, $statut, $reserve, $haine, $synthese, $partiels,
            $scoresDuRapport,
        ): Verification {
            foreach ($verifiables as $claim) {
                $decision = $decisionsParClaim[$claim->id];

                $this->enregistrerLesMoteurs($verification, $claim, $resultatsParClaim[$claim->id]);
                $this->enregistrerLesPreuves($verification, $claim, $resultatsParClaim[$claim->id]);

                $claim->forceFill([
                    'verdict' => $decision->verdict->value,
                    'confiance' => $decision->confiance,
                ])->save();
            }

            $verification->report()->create([
                'uuid' => (string) Str::uuid(),
                // NULL dès que la composition est MIXTE — jamais un
                // pseudo-verdict « mixte » écrit dans cette colonne (décision
                // verrouillée par NEWINSTRUCTION24).
                'verdict' => $verdictScalaire?->value,
                'composition_status' => $compositionStatus->value,
                'confidence' => $confiance,
                'summary' => $synthese,
                'caveat' => $reserve,
                'scores' => $scoresDuRapport,
                'timeline' => $this->chronologie,
                'hate_speech_score' => $haine->score,
                'hate_speech_tags' => $haine->tags,
                'hate_speech_note' => $haine->note,
                // Un rapport en arbitrage n'est pas publié : il existe, il est
                // consultable par son auteur, mais il n'engage pas encore la
                // plateforme.
                'published_at' => $statut === StatutVerification::Terminee ? now() : null,
            ]);

            // Au moins un claim est resté sans réponse exploitable après
            // épuisement des tentatives : la généralisation la plus prudente
            // de `DecisionFactCheck::ouvreDroitAuRemboursement()` à plusieurs
            // claims est de rembourser dès qu'UN SEUL d'entre eux n'a pas pu
            // être tranché, pas seulement si aucun ne l'a été.
            if ($partiels !== []) {
                $this->rembourser($verification);
            }

            $verification->forceFill([
                'status' => $statut->value,
                'completed_at' => now(),
            ])->save();

            /*
             | Les récompenses réagissent à l'événement, jamais l'inverse : le
             | fact-checking n'a aucune raison de connaître les badges, et sans
             | cette séparation chaque nouveauté marketing viendrait modifier le
             | cœur du produit.
             */
            VerificationAchevee::dispatch($verification->refresh());

            return $verification;
        });
    }

    /**
     * Les claims d'une vérification — extraits une fois, jamais deux.
     *
     * Une reprise appelle `executer()` une seconde fois sur la MÊME
     * vérification : ré-extraire casserait l'idempotence (une extraction
     * réelle, non déterministe, pourrait découper différemment le même
     * contenu) et violerait l'unicité `(verification_id, position)`.
     *
     * @return Collection<int, Claim>
     */
    private function chargerOuExtraireLesClaims(Verification $verification): Collection
    {
        $existants = $verification->claims;

        if ($existants->isNotEmpty()) {
            return $existants;
        }

        $sac = new SacDePreuves(
            affirmation: $verification->claim,
            type: $verification->type,
            contexte: $verification->context,
            cheminFichier: $verification->file_path,
        );

        $extraites = $this->extraction->extraire($sac);

        return collect($extraites)
            ->values()
            ->map(function (AffirmationExtraite $affirmation, int $position) use ($verification): Claim {
                return $verification->claims()->create([
                    'position' => $position,
                    'texte' => $affirmation->texte,
                    'texte_original' => $affirmation->texteOriginal,
                    'acte_de_langage' => $affirmation->acteDeLangage->value,
                    'portee_temporelle' => $affirmation->porteeTemporelle?->value,
                ]);
            });
    }

    /**
     * Aucun claim factuel dans tout le contenu soumis — composition INSUFFISANTE.
     *
     * Les unités sont rendues : une opinion, une question ou une prédiction
     * ne sont pas un service dégradé, elles sont simplement hors du champ de
     * ce que la plateforme vérifie.
     *
     * @param  Collection<int, Claim>  $claims
     */
    private function rapportInsuffisant(Verification $verification, Collection $claims): Verification
    {
        $this->franchir($verification, EtapePipeline::Consolidation);
        $this->franchir($verification, EtapePipeline::Verdict);

        return DB::transaction(function () use ($verification, $claims): Verification {
            $verification->report()->create([
                'uuid' => (string) Str::uuid(),
                'verdict' => null,
                'composition_status' => CompositionDuRapport::Insuffisante->value,
                'confidence' => 0,
                'summary' => $claims->count() === 1
                    ? "Ce contenu ne porte aucune affirmation vérifiable : c'est une ".mb_strtolower($claims->first()->acte_de_langage->libelle()).', pas un fait à trancher.'
                    : 'Aucune des affirmations de ce contenu ne pouvait être soumise à vérification factuelle.',
                'caveat' => null,
                'scores' => null,
                'timeline' => $this->chronologie,
                'hate_speech_score' => 0,
                'hate_speech_tags' => [],
                'hate_speech_note' => null,
                'published_at' => now(),
            ]);

            $this->rembourser($verification, 'Remboursement — aucune affirmation vérifiable dans ce contenu');

            $verification->forceFill([
                'status' => StatutVerification::Terminee->value,
                'completed_at' => now(),
            ])->save();

            VerificationAchevee::dispatch($verification->refresh());

            return $verification;
        });
    }

    /**
     * La synthèse d'un rapport MIXTE — une énumération, jamais un mot-valise.
     *
     * Composée côté serveur, jamais par un modèle : « un seul FAUX suffit à
     * rendre le rapport TROMPEUR » est exactement la règle que NEWINSTRUCTION24
     * a écartée. Chaque claim garde son verdict propre, visible séparément.
     *
     * @param  Collection<int, Claim>  $verifiables
     * @param  array<int, DecisionFactCheck>  $decisionsParClaim
     */
    private function syntheseMixte(Collection $verifiables, array $decisionsParClaim): string
    {
        $comptes = [];

        foreach ($verifiables as $claim) {
            $verdict = $decisionsParClaim[$claim->id]->verdict;
            $comptes[$verdict->value] = ($comptes[$verdict->value] ?? 0) + 1;
        }

        $resume = [];

        foreach ($comptes as $valeur => $nombre) {
            $resume[] = sprintf('%d %s', $nombre, mb_strtolower(VerdictFactCheck::from($valeur)->libelle()));
        }

        $phrases = ['Ce contenu porte plusieurs affirmations : '.implode(', ', $resume).'.'];

        foreach ($verifiables as $position => $claim) {
            $decision = $decisionsParClaim[$claim->id];

            $phrases[] = sprintf(
                '%d. « %s » — %s.',
                $position + 1,
                Str::limit($claim->texte, 140),
                mb_strtoupper($decision->verdict->libelle()),
            );
        }

        return implode(' ', $phrases);
    }

    /**
     * Relance tout de suite une analyse en attente de reprise.
     *
     * L'écran le propose une fois le délai écoulé, pour ne pas obliger la
     * personne à attendre un job programmé à la minute près. Le refus est
     * silencieux et non exceptionnel : relancer deux fois de suite n'est pas
     * une erreur, c'est un double-clic.
     *
     * @return bool vrai si la relance a bien été lancée
     */
    public function reprendreMaintenant(Verification $verification): bool
    {
        if (! $verification->reprisePossible()) {
            return false;
        }

        $verification->forceFill([
            'status' => StatutVerification::EnFile->value,
            // Efface le rendez-vous : l'écran ne doit plus annoncer une
            // attente qui n'a plus lieu d'être.
            //
            // Ce n'est PAS ce qui écarte le job différé. Celui-ci devient
            // exigible à la minute même où le bouton apparaît, et
            // `repriseProgrammeePlusTard()` le laisse alors passer. Ce qui
            // l'arrête est la prise en main atomique de `executer()` : le
            // premier des deux passe la ligne à `analyzing`, le second
            // renonce sans avoir appelé un seul moteur.
            'retry_after' => null,
        ])->save();

        AnalyserVerification::dispatch($verification->id);

        return true;
    }

    /**
     * Le pipeline s'est interrompu sur une erreur technique.
     *
     * Une exception n'est pas une réponse. Tant qu'il reste des tentatives, on
     * repropose l'analyse plus tard ; sinon on renonce franchement et on rend
     * les unités. Ce qu'il ne faut surtout pas faire, c'est laisser un écran
     * « échec » sans explication et sans remboursement : la personne a payé.
     */
    public function apresEchecTechnique(Verification $verification): void
    {
        if ($verification->estTerminee()) {
            return;
        }

        if ($this->peutEncoreReessayer($verification)) {
            $this->programmerUneReprise(
                $verification,
                "Une interruption technique a coupé l'analyse.",
            );

            return;
        }

        $this->renoncer($verification);
    }

    /**
     * Fin de parcours : plus de tentative, aucun rapport possible.
     *
     * Les unités reviennent AVANT que le statut ne change, pour qu'aucun écran
     * n'affiche « analyse interrompue » sur un compte qui n'a pas encore été
     * recrédité.
     */
    private function renoncer(Verification $verification): void
    {
        DB::transaction(function () use ($verification): void {
            $this->rembourser($verification, 'Remboursement — analyse interrompue');

            $verification->forceFill([
                'status' => StatutVerification::Echouee->value,
                'completed_at' => now(),
            ])->save();
        });

        Log::warning('Analyse abandonnée après épuisement des tentatives', [
            'verification' => $verification->uuid,
            'tentatives' => $verification->attempts,
        ]);
    }

    /**
     * Prend l'analyse en main, ou renonce si quelqu'un d'autre l'a deja prise.
     *
     * ## Pourquoi un UPDATE conditionnel et pas une lecture suivie d'une ecriture
     *
     * Deux jobs peuvent viser la meme verification EN MEME TEMPS, et ce n'est
     * pas un cas tordu : c'est le scenario nominal de la reprise. Une analyse
     * partielle programme sa reprise dans douze minutes ; a la douzieme minute,
     * l'ecran propose « reprendre maintenant ». La personne clique a l'instant
     * meme ou le job differe devient exigible. Les deux passent les gardes de
     * `AnalyserVerification`, les deux interrogent les moteurs pendant une
     * minute, et les deux ecrivent le rapport. Le second heurtait la contrainte
     * d'unicite sur `verification_id` — une erreur 500 en production, apres
     * avoir paye deux fois les moteurs.
     *
     * Verifier le statut puis l'ecrire laisse cette fenetre grande ouverte : il
     * s'ecoule tout le pipeline entre la lecture et l'ecriture concurrente.
     * L'UPDATE conditionnel ferme la fenetre dans la base elle-meme : la
     * ligne ne passe a `analyzing` qu'une fois, et le perdant le sait
     * immediatement — avant d'avoir depense un seul appel d'API.
     *
     * Les statuts pris en main sont enumeres en BLANC, pas en noir : un etat
     * ajoute plus tard devra etre autorise explicitement plutot que de se
     * glisser dans le pipeline par omission.
     *
     * @return bool vrai si CE passage tient l'analyse
     */
    private function prendreEnMain(Verification $verification): bool
    {
        $prise = Verification::query()
            ->whereKey($verification->getKey())
            ->whereIn('status', [
                StatutVerification::EnFile->value,
                StatutVerification::EnAttenteDeReprise->value,
            ])
            ->update([
                'status' => StatutVerification::EnCours->value,
                'started_at' => now(),
            ]);

        if ($prise === 0) {
            Log::info('Analyse deja prise en main, ce passage renonce', [
                'verification' => $verification->uuid,
                'statut' => $verification->refresh()->status->value,
            ]);

            return false;
        }

        $verification->refresh();

        return true;
    }

    /**
     * Reste-t-il des tentatives ?
     */
    private function peutEncoreReessayer(Verification $verification): bool
    {
        return $verification->attempts < (int) config('factcheck.runtime.reprise.tentatives_max', 3);
    }

    /**
     * Reprogramme l'analyse et prévient la personne.
     */
    private function programmerUneReprise(Verification $verification, string $motif): Verification
    {
        $delai = (int) config('factcheck.runtime.reprise.delai_minutes', 12);

        $verification->forceFill([
            'status' => StatutVerification::EnAttenteDeReprise->value,
            'attempts' => $verification->attempts + 1,
            'retry_after' => now()->addMinutes($delai),
            'retry_reason' => $motif,
            // L'étape revient en arrière : l'analyse n'a pas abouti, et laisser
            // la barre à 100 % ferait croire à un rapport disponible.
            'pipeline_step' => EtapePipeline::Sources->value,
        ])->save();

        // La reprise part d'elle-même : la personne n'a rien à faire, même si
        // l'écran lui propose de la déclencher plus tôt.
        AnalyserVerification::dispatch($verification->id)->delay(now()->addMinutes($delai));

        Log::info('Analyse reprogrammée', [
            'verification' => $verification->uuid,
            'tentative' => $verification->attempts,
            'motif' => $motif,
        ]);

        return $verification->refresh();
    }

    /**
     * Pourquoi ça n'a pas abouti — dit en clair, sans détail technique.
     *
     * @param  list<VerdictMoteur>  $resultats
     */
    private function motifDeReprise(array $resultats): string
    {
        $repondus = count(array_filter($resultats, static fn (VerdictMoteur $r): bool => ! $r->echoue));

        return $repondus === 0
            ? "Nos moteurs d'analyse sont momentanément saturés."
            : 'Trop peu de moteurs ont pu répondre pour conclure honnêtement.';
    }

    /**
     * Positions retenues sur les preuves, une par source.
     *
     * Dédupliquées comme à l'enregistrement : compter cinq fois la même source
     * ferait pencher le contrôle du côté du moteur le plus bavard.
     *
     * @param  list<VerdictMoteur>  $resultats
     * @return list<PositionPreuve>
     */
    private function positionsDesPreuves(array $resultats): array
    {
        $parSource = [];

        foreach ($resultats as $resultat) {
            foreach ($resultat->preuves as $preuve) {
                $parSource[$this->cleDeSource($preuve)] = $preuve->position;
            }
        }

        return array_values($parSource);
    }

    /**
     * Les preuves dédupliquées, indexées par source.
     *
     * UNE seule définition de la déduplication, employée par l'enregistrement
     * ET par le comptage de la synthèse. Les deux divergeaient autrefois, et le
     * rapport annonçait « 14 sources consultées » sous une liste qui en montrait
     * 13 — le genre d'écart qui fait douter de tout le reste.
     *
     * @param  list<VerdictMoteur>  $resultats
     * @return array<string, Preuve>
     */
    private function preuvesDedupliquees(array $resultats): array
    {
        /** @var array<string, Preuve> $parSource */
        $parSource = [];

        foreach ($resultats as $resultat) {
            foreach ($resultat->preuves as $preuve) {
                $cle = $this->cleDeSource($preuve);

                /*
                 | À clé égale, une preuve RÉCUPÉRÉE l'emporte sur une preuve
                 | seulement citée. Sans cette règle, l'ordre d'exécution des
                 | moteurs décidait : OpenAI passant avant Gemini, un `gouv.bj`
                 | nommé de mémoire masquait le `gouv.bj` réellement ouvert.
                 */
                if (! isset($parSource[$cle]) || (! $parSource[$cle]->recuperee && $preuve->recuperee)) {
                    $parSource[$cle] = $preuve;
                }
            }
        }

        return $parSource;
    }

    /**
     * Ce qui fait que deux preuves sont LA MÊME source.
     *
     * Le domaine réel, et non l'adresse. Gemini rapporte ses sources par des
     * liens de redirection tous distincts — `…/grounding-api-redirect/AbCd`,
     * `…/AbCe`, `…/AbCf` — même quand ils mènent à vingt pages du même média.
     * Dédupliquer sur l'URL les comptait donc comme vingt sources
     * indépendantes, et le rapport annonçait « 20 sources consultées » sous
     * une poignée de médias. C'est l'illusion de recoupement que le
     * fact-checking existe précisément pour défaire.
     *
     * Le chemin reste dans la clé quand l'adresse est DIRECTE : deux articles
     * distincts du même journal sont bien deux preuves. Il ne sert à rien sur
     * un redirecteur, où il n'est qu'un jeton opaque.
     */
    private function cleDeSource(Preuve $preuve): string
    {
        $domaine = $this->domaines->pour($preuve->url, $preuve->nomSource);

        /*
         | PHASE 5 — le domaine ENREGISTRABLE, pas l'hôte brut.
         |
         | `www.lemonde.fr` et `m.lemonde.fr` sont le même éditeur : les
         | compter comme deux sources distinctes referait l'erreur que la
         | résolution des redirecteurs a déjà corrigée pour Gemini, un cran
         | plus bas. `domaineEnregistrable()` ne retire que les préfixes de
         | présentation (`www`, `m`, `amp`, `mobile`), jamais un sous-domaine
         | qui pourrait nommer une entité différente.
         */
        $enregistrable = $this->domaines->domaineEnregistrable($domaine) ?? $domaine;

        if ($this->domaines->estUnRedirecteur($preuve->url)) {
            return $enregistrable;
        }

        return $enregistrable.(parse_url($preuve->url, PHP_URL_PATH) ?: '');
    }

    /**
     * Marque une étape franchie.
     *
     * Écrite AU MOMENT où elle est atteinte, pas à la fin : c'est ce qui permet
     * à l'écran de suivre l'analyse en direct plutôt que de faire semblant.
     */
    private function franchir(Verification $verification, EtapePipeline $etape): void
    {
        $this->chronologie[] = [
            'etape' => $etape->name,
            // `libelleCanonique()` : la chronologie est PERSISTÉE puis
            // relue longtemps après. Elle ne doit pas garder la langue qu'avait
            // le worker au moment du traitement.
            'libelle' => $etape->libelleCanonique(),
            'at' => now()->toIso8601String(),
        ];

        $verification->forceFill(['pipeline_step' => $etape->value])->save();
    }

    /**
     * Sert une réponse déjà connue, si elle existe.
     *
     * Les unités sont RENDUES dans ce cas. L'écran a bien annoncé un coût, il
     * ne mentait pas — mais faire payer une réponse qui n'a rien coûté serait
     * indéfendable, et une plateforme qui combat la désinformation a tout
     * intérêt à ce que la même rumeur soit signalée par le plus de monde
     * possible.
     */
    private function reutiliser(Verification $verification): ?Verification
    {
        $empreinte = $verification->content_fingerprint;

        if ($empreinte === null) {
            return null;
        }

        // 1. Un fait tranché par l'équipe : meilleur que douze moteurs.
        $fait = $this->connues->faitConnu($verification->claim, $empreinte);

        if ($fait !== null) {
            return $this->depuisLeFait($verification, $fait);
        }

        // 2. Une analyse récente du même contenu.
        $anterieure = $this->connues->analyseRecente($empreinte, $verification->id);

        if ($anterieure?->report !== null) {
            return $this->depuisLAnalyse($verification, $anterieure);
        }

        return null;
    }

    private function depuisLeFait(Verification $verification, VerifiedFact $fait): Verification
    {
        return DB::transaction(function () use ($verification, $fait): Verification {
            /*
             | Une reprise HUMAINE documentée produit toujours exactement UN
             | claim homogène — jamais MIXTE (contrat V-8). La fiche de
             | connaissance porte elle-même un seul verdict rédactionnel.
             */
            $claim = $verification->claims()->create([
                'position' => 0,
                'texte' => $verification->claim,
                'acte_de_langage' => ActeDeLangage::Affirmation->value,
                'verdict' => $fait->verdict->value,
                'confiance' => 100,
            ]);

            $verification->report()->create([
                'uuid' => (string) Str::uuid(),
                'verdict' => $fait->verdict->value,
                'composition_status' => CompositionDuRapport::Homogene->value,
                // Une décision humaine documentée : c'est le degré de certitude
                // le plus élevé que la plateforme puisse offrir.
                'confidence' => 100,
                'summary' => 'Cette information a déjà été vérifiée par notre équipe. '.$fait->summary,
                'caveat' => null,
                'scores' => null,
            ]);

            /*
             | TOUTES les sources deviennent des preuves, pas seulement la
             | première. Un verdict à 100 % de confiance adossé à un seul lien
             | alors que la rédaction en a recoupé quatre, c'est précisément ce
             | qu'on reproche à la désinformation.
             */
            foreach ($fait->sourcesRetenues() as $source) {
                $verification->evidences()->create([
                    'claim_id' => $claim->id,
                    'source_url' => $source['url'],
                    'source_name' => $source['name'],
                    'snippet' => mb_substr($fait->summary, 0, 300),
                    'stance' => PositionPreuve::Confirme->value,
                    'reliability' => 100,
                ]);
            }

            $fait->compterUneReutilisation();
            $this->rembourser($verification, 'Réponse déjà vérifiée — non facturée');

            $verification->forceFill([
                'status' => StatutVerification::Terminee->value,
                'completed_at' => now(),
            ])->save();

            /*
             | L'ÉVÉNEMENT PART ICI AUSSI.
             |
             | Une analyse servie depuis le cache est ACHEVÉE au même titre
             | qu'une autre : la personne a son verdict. Ne pas émettre
             | l'événement privait ce chemin — le plus fréquent — de la
             | notification, de la série, des badges, et surtout du parrainage.
             |
             | `Parrainage::honorer()` n'étant appelé que depuis cet écouteur,
             | un parrain dont le filleul tombait sur le cache n'était jamais
             | crédité, et ces unités étaient perdues sans trace.
             */
            VerificationAchevee::dispatch($verification->refresh());

            return $verification->refresh();
        });
    }

    private function depuisLAnalyse(Verification $verification, Verification $anterieure): Verification
    {
        return DB::transaction(function () use ($verification, $anterieure): Verification {
            $source = $anterieure->report;

            $verification->report()->create([
                'uuid' => (string) Str::uuid(),
                // `?->value` : un rapport antérieur MIXTE porte un verdict
                // scalaire NUL — le recopier tel quel, sans en inventer un.
                'verdict' => $source->verdict?->value,
                'composition_status' => $source->composition_status->value,
                'confidence' => $source->confidence,
                'summary' => $source->summary,
                'caveat' => $source->caveat,
                'scores' => $source->scores,
                'hate_speech_score' => $source->hate_speech_score,
                'hate_speech_tags' => $source->hate_speech_tags,
                'hate_speech_note' => $source->hate_speech_note,
                'published_at' => now(),
            ]);

            /*
             | Les claims sont RECOPIÉS, dans le même ordre — un rapport repris
             | doit rester lisible si l'analyse d'origine disparaît, et
             | conserver le détail par claim plutôt que de le réduire au seul
             | verdict scalaire du rapport.
             */
            $correspondance = [];

            foreach ($anterieure->claims as $ancien) {
                $nouveau = $verification->claims()->create($ancien->only([
                    'position', 'texte', 'texte_original', 'acte_de_langage',
                    'portee_temporelle', 'verdict', 'confiance', 'reponse',
                ]));

                $correspondance[$ancien->id] = $nouveau->id;
            }

            /*
             | Les preuves sont RECOPIÉES, pas partagées. Le rapport doit rester
             | lisible si l'analyse d'origine est supprimée — par un compte
             | effacé, par exemple — et une personne n'a pas à dépendre de la
             | demande de quelqu'un d'autre pour consulter sa propre réponse.
             */
            foreach ($anterieure->evidences as $preuve) {
                $verification->evidences()->create($preuve->only([
                    'source_url', 'source_name', 'snippet', 'stance', 'reliability', 'published_at',
                ]) + [
                    'claim_id' => $preuve->claim_id !== null ? ($correspondance[$preuve->claim_id] ?? null) : null,
                ]);
            }

            $this->rembourser($verification, 'Analyse identique déjà disponible — non facturée');

            $verification->forceFill([
                'status' => StatutVerification::Terminee->value,
                'reused_from_id' => $anterieure->id,
                'completed_at' => now(),
            ])->save();

            /*
             | L'ÉVÉNEMENT PART ICI AUSSI.
             |
             | Une analyse servie depuis le cache est ACHEVÉE au même titre
             | qu'une autre : la personne a son verdict. Ne pas émettre
             | l'événement privait ce chemin — le plus fréquent — de la
             | notification, de la série, des badges, et surtout du parrainage.
             |
             | `Parrainage::honorer()` n'étant appelé que depuis cet écouteur,
             | un parrain dont le filleul tombait sur le cache n'était jamais
             | crédité, et ces unités étaient perdues sans trace.
             */
            VerificationAchevee::dispatch($verification->refresh());

            return $verification->refresh();
        });
    }

    /**
     * @return list<VerdictMoteur>
     */
    private function interrogerLesMoteurs(SacDePreuves $sac, Verification $verification): array
    {
        $tous = $this->registre->pour($sac->type);

        if (! (bool) config('factcheck.collecteur_de_preuves_actif', false)) {
            return $this->executerLesAdaptateurs($tous, $sac, $verification);
        }

        /*
         | CONTRAT V-10, phase 7 — DÉCOUVERTE puis ANALYSE, jamais l'inverse.
         | Les moteurs qui citent LIBREMENT leurs sources ({@see MoteurAnalyse::citeEnLibre()})
         | ne reçoivent plus un sac nu : ils reçoivent le paquet que le
         | backend a déjà vérifié, et ne peuvent plus citer que ce qu'il
         | contient. Les autres moteurs (découverte, signaux) tournent
         | d'abord, SANS CHANGEMENT, et leurs preuves nourrissent le paquet.
         */
        $decouverte = array_values(array_filter($tous, static fn ($a): bool => ! $a->nom()->citeEnLibre()));
        $analyse = array_values(array_filter($tous, static fn ($a): bool => $a->nom()->citeEnLibre()));

        $resultatsDecouverte = $this->executerLesAdaptateurs($decouverte, $sac, $verification);

        $candidates = $resultatsDecouverte === [] ? [] : array_merge(...array_map(
            static fn (VerdictMoteur $r): array => $r->preuves,
            $resultatsDecouverte,
        ));

        $paquet = $this->collecteur->collecter($sac->affirmation, $candidates);

        $resultatsAnalyse = $this->executerLesAdaptateurs($analyse, $sac->avecPaquet($paquet), $verification);

        return array_merge($resultatsDecouverte, $resultatsAnalyse);
    }

    /**
     * @param  list<AnalysisAdapterInterface>  $adaptateurs
     * @return list<VerdictMoteur>
     */
    private function executerLesAdaptateurs(array $adaptateurs, SacDePreuves $sac, Verification $verification): array
    {
        $resultats = [];

        foreach ($adaptateurs as $adaptateur) {
            try {
                $resultats[] = $adaptateur->analyse($sac);
            } catch (Throwable $erreur) {
                /*
                 | Le contrat interdit aux adaptateurs de lever. Cette capture
                 | est la ceinture de sécurité : un adaptateur mal écrit ne doit
                 | pas faire tomber l'analyse entière, et son manquement doit se
                 | voir dans le journal plutôt que disparaître.
                 |
                 | Le message d'exception n'est PAS repris dans le rapport : il
                 | peut contenir une URL de service, un identifiant, une clé.
                 */
                Log::error('Adaptateur en erreur', [
                    'moteur' => $adaptateur->nom()->value,
                    'verification' => $verification->uuid,
                    'erreur' => $erreur->getMessage(),
                ]);

                $resultats[] = VerdictMoteur::echec($adaptateur->nom(), 'Erreur interne du moteur');
            }
        }

        return $resultats;
    }

    /**
     * @param  list<VerdictMoteur>  $resultats
     */
    private function enregistrerLesMoteurs(Verification $verification, Claim $claim, array $resultats): void
    {
        foreach ($resultats as $resultat) {
            $verification->engineRuns()->create([
                'claim_id' => $claim->id,
                'engine' => $resultat->moteur->value,
                'verdict' => $resultat->verdict?->value,
                'confidence' => $resultat->confiance,
                'latency_ms' => $resultat->latenceMs,
                'failed' => $resultat->echoue,
                'fail_reason' => $resultat->motifEchec,
                'reasoning' => $resultat->raisonnement !== '' ? $resultat->raisonnement : null,
            ]);
        }
    }

    /**
     * Rassemble les preuves, en écartant les doublons de source.
     *
     * Sans déduplication, une même source citée par cinq moteurs apparaîtrait
     * cinq fois et donnerait l'illusion de cinq confirmations indépendantes —
     * exactement le biais que le fact-checking doit défaire.
     *
     * @param  list<VerdictMoteur>  $resultats
     */
    private function enregistrerLesPreuves(Verification $verification, Claim $claim, array $resultats): void
    {
        $parSource = $this->preuvesDedupliquees($resultats);
        /** @var array<string, list<PositionPreuve>> $positions */
        $positions = [];

        foreach ($resultats as $resultat) {
            foreach ($resultat->preuves as $preuve) {
                $positions[$this->cleDeSource($preuve)][] = $preuve->position;
            }
        }

        $elementsParDomaine = $this->collecterLesElementsReels($claim, $parSource);

        foreach ($parSource as $cle => $preuve) {
            // Deux moteurs lisent la même source en sens opposé : la preuve est
            // marquée CONTESTÉE plutôt qu'arbitrée en silence.
            $distinctes = array_unique(array_map(
                static fn (PositionPreuve $p): string => $p->value,
                $positions[$cle],
            ));

            $position = count($distinctes) > 1 ? PositionPreuve::Contestee : $preuve->position;

            /*
             | PHASE 5 — domaine et éditeur, calculés dès maintenant depuis
             | l'URL. Un élément RÉELLEMENT récupéré par le collecteur de la
             | phase 6 (voir {@see collecterLesElementsReels()}) prime : son
             | domaine, son éditeur, son adresse finale/canonique et sa date
             | de publication viennent d'une page ouverte, pas d'un moteur qui
             | les a devinés.
             */
            $domaineAffiche = $this->domaines->pour($preuve->url, $preuve->nomSource);
            $domaineEnregistrable = $this->domaines->domaineEnregistrable($domaineAffiche) ?? $domaineAffiche;
            $element = $elementsParDomaine[$domaineEnregistrable] ?? null;

            $verification->evidences()->create([
                'claim_id' => $claim->id,
                'source_url' => $preuve->url,
                'source_name' => $preuve->nomSource,
                'url_finale' => $element?->urlFinale,
                'url_canonique' => $element?->urlCanonique,
                'domaine' => $domaineEnregistrable,
                // Jamais un redirecteur : `pour()` peut s'y replier en
                // dernier recours quand aucun nom de domaine n'est devinable
                // dans le nom de la source — ce repli vaut pour l'AFFICHAGE,
                // pas pour la colonne qui prétend identifier l'éditeur.
                'editeur' => $element?->editeur ?? ($this->domaines->estUnRedirecteur($domaineAffiche) ? null : $domaineAffiche),
                'snippet' => mb_substr($preuve->extrait, 0, 300),
                'stance' => $position->value,
                'reliability' => $preuve->fiabilite,
                'published_at' => $element?->publieeLe ?? $preuve->publieeLe,
                'fetched' => $element !== null || $preuve->recuperee,
                'recupere_le' => $element !== null ? now() : null,
            ]);
        }
    }

    /**
     * Récupère RÉELLEMENT les pages des preuves citées — phase 6, V-10.
     *
     * Désactivé par défaut ({@see config('factcheck.collecteur_de_preuves_actif')}) :
     * un moteur sans accès web nomme des domaines de mémoire, et les ouvrir
     * change le comportement de CHAQUE vérification (délai réseau borné,
     * trafic sortant réel) — un changement qui doit être une décision
     * d'exploitation explicite, jamais un effet de bord silencieux d'une
     * mise à jour. Tant qu'il est éteint, `enregistrerLesPreuves()` se
     * comporte exactement comme avant cette phase.
     *
     * @param  array<string, Preuve>  $parSource  indexées par {@see cleDeSource()}
     * @return array<string, ElementDuPaquet> indexés par domaine ENREGISTRABLE
     */
    private function collecterLesElementsReels(Claim $claim, array $parSource): array
    {
        if (! (bool) config('factcheck.collecteur_de_preuves_actif', false)) {
            return [];
        }

        if ($parSource === []) {
            return [];
        }

        $paquet = $this->collecteur->collecter($claim->texte, array_values($parSource));

        $parDomaine = [];

        foreach ($paquet->elements() as $element) {
            $domaineDeLElement = $this->domaines->pour($element->urlFinale, $element->editeur ?? '');
            $cle = $this->domaines->domaineEnregistrable($domaineDeLElement) ?? $domaineDeLElement;

            $parDomaine[$cle] = $element;
        }

        return $parDomaine;
    }

    /**
     * Synthèse citoyenne — avec repli.
     *
     * La rédaction par un modèle est un ENRICHISSEMENT. Quand elle manque, une
     * synthèse composée côté serveur prend le relais : verdict, réserve
     * éventuelle, nombre de sources. Moins élégante, mais toujours exacte — et
     * surtout, jamais absente.
     *
     * @param  list<VerdictMoteur>  $resultats
     */
    private function synthese(
        Verification $verification,
        DecisionFactCheck $decision,
        array $resultats,
        ?Preuve $preuveDecisive = null,
    ): string {
        $reussis = count(array_filter($resultats, static fn (VerdictMoteur $r): bool => ! $r->echoue));

        /*
         | Les sources sont comptées APRÈS déduplication — c'est-à-dire comme le
         | lecteur les verra. Compter les retours bruts annonçait « 14 sources
         | consultées » sous un rapport qui en montrait 13 : le genre d'écart
         | qui fait douter de tout le reste.
         */
        $preuves = $this->preuvesDedupliquees($resultats);
        $sources = count($preuves);

        /*
         | LES SOURCES OUVERTES, SÉPARÉMENT DES SOURCES CITÉES.
         |
         | « 5 sources consultées » était faux quand quatre d'entre elles
         | n'étaient que des domaines nommés de mémoire par des moteurs sans
         | accès web. On ne compte comme consulté que ce qui a été récupéré, et
         | l'on dit le reste pour ce qu'il est.
         */
        $ouvertes = count(array_filter(
            $preuves,
            static fn (Preuve $preuve): bool => $preuve->recuperee,
        ));

        /*
         | LA PHRASE DU VERDICT N'EST PLUS ICI.
         |
         | Cette synthèse commençait par `$decision->verdict->explication()` —
         | « Les sources consultées contredisent cette information. » Or le Hero
         | du rapport affiche déjà, quarante pixels plus haut et en gras dans sa
         | pilule, « Ce contenu a été identifié comme une fausse information. »
         | Deux formulations du même énoncé à deux endroits de la même page : le
         | lecteur se demande laquelle est la conclusion.
         |
         | Le verdict est dit UNE fois, par le Hero, à partir de l'enum. Ce
         | paragraphe-ci ne garde que ce que le Hero ne peut pas dire : combien
         | de moteurs ont répondu, combien de sources ont été consultées, et si
         | un humain reprend le dossier. C'est la MÉTHODE, pas la conclusion —
         | et c'est à ce titre qu'elle a sa place sous l'argumentation.
         */
        $phrases = [];

        if ($decision->partielle) {
            $phrases[] = 'Cette analyse est incomplète : trop peu de moteurs ont répondu pour conclure.';
        } else {
            $phrases[] = sprintf(
                '%d moteur%s d\'analyse ont été interrogés et %d source%s consultée%s.',
                $reussis, $reussis > 1 ? 's' : '',
                $ouvertes, $ouvertes > 1 ? 's' : '', $ouvertes > 1 ? 's' : '',
            );

            /*
             | Dit SÉPARÉMENT, et jamais additionné au compte ci-dessus : un
             | domaine qu'un moteur nomme de mémoire n'a pas été ouvert, et le
             | présenter comme consulté annoncerait un recoupement qui n'a pas eu
             | lieu. Le mentionner reste utile — c'est souvent le canal légitime
             | que le lecteur cherchait.
             */
            $citees = $sources - $ouvertes;

            if ($citees > 0) {
                $phrases[] = sprintf(
                    '%d référence%s %s citée%s par les moteurs sans avoir pu être ouverte%s.',
                    $citees, $citees > 1 ? 's' : '',
                    $citees > 1 ? 'ont été' : 'a été',
                    $citees > 1 ? 's' : '', $citees > 1 ? 's' : '',
                );
            }
        }

        /*
         | NEWINSTRUCTION28 — quand une preuve a tranché une contradiction
         | entre moteurs, le dire est ESSENTIEL : sans cette phrase, un
         | verdict FAUX sorti de nulle part pour un lecteur qui vient de voir
         | les moteurs se contredire ferait moins confiance au rapport, pas
         | plus. Construite depuis les champs RÉELS de la preuve — jamais un
         | gabarit qui nommerait une entité ou une fonction en dur.
         */
        if ($preuveDecisive !== null) {
            $phrases[] = sprintf(
                'Une preuve institutionnelle plus récente — %s%s — %s directement cette affirmation : c\'est elle qui a permis de trancher, malgré la divergence des moteurs d\'analyse entre eux.',
                $preuveDecisive->nomSource,
                $preuveDecisive->publieeLe !== null
                    ? ', datée du '.$preuveDecisive->publieeLe->locale('fr')->isoFormat('D MMMM YYYY')
                    : '',
                $decision->verdict === VerdictFactCheck::Vrai ? 'confirme' : 'contredit',
            );
        } elseif ($decision->statut === StatutVerification::RevueHumaine) {
            $phrases[] = 'Les avis divergent : un vérificateur humain examine ce dossier.';
        }

        return implode(' ', $phrases);
    }

    /**
     * Rend les unités quand l'analyse n'a pas pu aboutir.
     *
     * Idempotent : `refunded_at` empêche qu'une reprise de traitement ne
     * rembourse deux fois la même vérification.
     */
    private function rembourser(Verification $verification, ?string $libelle = null): void
    {
        if ($verification->refunded_at !== null) {
            return;
        }

        $utilisateur = $verification->user;

        if ($utilisateur === null || $verification->cost_units <= 0) {
            return;
        }

        $this->economie->crediter(
            $utilisateur,
            // Le coût FIGÉ à la soumission, jamais le tarif courant : celui-ci
            // a pu changer entre-temps.
            $verification->cost_units,
            MotifMouvementUnite::Remboursement,
            ($libelle ?? 'Remboursement — analyse incomplète').' ('.$verification->reference().')',
            $verification,
        );

        $verification->forceFill(['refunded_at' => now()])->save();
    }
}
