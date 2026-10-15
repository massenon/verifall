<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\Contracts\AnalysisAdapterInterface;
use App\Core\FactChecking\ValueObjects\IndiceHaine;
use App\Core\FactChecking\ValueObjects\Preuve;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\PositionPreuve;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use RuntimeException;

/**
 * Moteur d'analyse simulé.
 *
 * Aucune clé d'API n'est disponible : cet adaptateur tient la place des vrais
 * en respectant le MÊME contrat. Le jour où les clés arrivent, on écrit les
 * implémentations réelles et l'on bascule `FAKE_ADAPTERS` — l'orchestrateur, le
 * scoring, le rapport et l'écran ne bougent pas d'une ligne.
 *
 * Ses réponses sont DÉTERMINISTES, dérivées de l'affirmation : la même phrase
 * donne toujours le même verdict. Un simulateur aléatoire rendrait la
 * démonstration incompréhensible et les tests ingouvernables.
 *
 * Il refuse de tourner en production : rendre des verdicts inventés sur une
 * plateforme de fact-checking serait exactement ce qu'elle combat.
 */
final class AdaptateurSimule implements AnalysisAdapterInterface
{
    public function __construct(private readonly MoteurAnalyse $moteur) {}

    public function nom(): MoteurAnalyse
    {
        return $this->moteur;
    }

    public function supporte(TypeContenu $type): bool
    {
        return in_array($this->moteur, MoteurAnalyse::pour($type), true);
    }

    public function coutUnites(): int
    {
        // Les moteurs simulés ne coûtent rien de plus : seul le coût de base du
        // type de contenu s'applique.
        return 0;
    }

    public function analyse(SacDePreuves $entree): VerdictMoteur
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Les moteurs simulés rendent des verdicts inventés : interdits en production. '
                .'Posez FAKE_ADAPTERS=false et branchez les vrais adaptateurs.'
            );
        }

        $debut = microtime(true);
        $empreinte = crc32(mb_strtolower(trim($entree->affirmation)).$this->moteur->value);

        /*
         | Un moteur sur onze échoue, de façon reproductible : l'orchestrateur
         | doit être exercé sur le cas « un service ne répond pas », qui est le
         | cas courant en production, pas l'exception.
         */
        if ($empreinte % 11 === 0) {
            return VerdictMoteur::echec(
                $this->moteur,
                'Service indisponible (simulation)',
                (int) ((microtime(true) - $debut) * 1000),
            );
        }

        $verdict = $this->verdictSimule($entree, $empreinte);

        return VerdictMoteur::rendu(
            moteur: $this->moteur,
            verdict: $verdict,
            confiance: 55 + ($empreinte % 40),
            preuves: $this->preuvesSimulees($verdict, $empreinte),
            raisonnement: $this->raisonnement($verdict),
            latenceMs: (int) ((microtime(true) - $debut) * 1000) + 120 + ($empreinte % 800),
            indiceHaine: $this->indiceHaineSimule($entree, $empreinte),
        );
    }

    /**
     * Verdict déterministe, avec un biais volontaire sur quelques mots-clés.
     *
     * Les rumeurs du prototype — « taxe WhatsApp », « faux décret » — doivent
     * ressortir FAUX dans la démonstration : c'est le scénario que la maquette
     * raconte, et une démonstration qui le contredit fait douter du produit.
     */
    private function verdictSimule(SacDePreuves $entree, int $empreinte): VerdictFactCheck
    {
        $texte = mb_strtolower($entree->affirmation);

        foreach (['taxe', 'whatsapp', 'faux décret', 'gratuit à vie', 'miracle'] as $indice) {
            if (str_contains($texte, $indice)) {
                return VerdictFactCheck::Faux;
            }
        }

        return match ($empreinte % 4) {
            0 => VerdictFactCheck::Vrai,
            1 => VerdictFactCheck::Faux,
            2 => VerdictFactCheck::Trompeur,
            default => VerdictFactCheck::NonVerifiable,
        };
    }

    /**
     * @return list<Preuve>
     */
    private function preuvesSimulees(VerdictFactCheck $verdict, int $empreinte): array
    {
        $sources = [
            ['gouv.bj', 'Portail officiel du Gouvernement du Bénin', 95],
            ['arcep.bj', 'ARCEP Bénin', 90],
            ['lanationbenin.info', 'La Nation', 78],
            ['factuel.afp.com', 'AFP Factuel', 92],
            ['banouto.info', 'Banouto', 70],
        ];

        $position = match ($verdict) {
            VerdictFactCheck::Vrai => PositionPreuve::Confirme,
            VerdictFactCheck::Faux => PositionPreuve::Contredit,
            default => PositionPreuve::Neutre,
        };

        $preuves = [];

        // Deux sources par moteur : assez pour que le rapport ait de la
        // matière, assez peu pour ne pas noyer le lecteur.
        for ($i = 0; $i < 2; $i++) {
            [$domaine, $nom, $fiabilite] = $sources[($empreinte + $i) % count($sources)];

            $preuves[] = new Preuve(
                url: 'https://'.$domaine.'/article-'.(($empreinte + $i) % 900 + 100),
                nomSource: $nom,
                extrait: '[Simulation] Extrait de '.$nom.' consulté par le moteur '
                    .$this->moteur->libelle().'.',
                position: $position,
                fiabilite: $fiabilite,
            );
        }

        return $preuves;
    }

    /**
     * Hate Speech Index simulé.
     *
     * Seuls les moteurs sémantiques l'évaluent : une recherche d'antériorité
     * d'image n'a rien à dire de la rhétorique d'un texte, et lui faire rendre
     * un « 0 % » ferait croire à une mesure qui n'a pas eu lieu.
     */
    private function indiceHaineSimule(SacDePreuves $entree, int $empreinte): IndiceHaine
    {
        if (! in_array($this->moteur, [MoteurAnalyse::Gemini, MoteurAnalyse::DeepSeek, MoteurAnalyse::OpenAI], true)) {
            return IndiceHaine::nonMesure();
        }

        $texte = mb_strtolower($entree->affirmation);
        $tags = [];

        foreach ([
            'ces gens' => "Ciblage d'un groupe",
            'envahiss' => "Rhétorique d'invasion",
            'chasser' => "Appel à l'exclusion",
            'race' => 'Catégorisation ethnique',
            'ethnie' => 'Catégorisation ethnique',
            'tous les' => 'Généralisation stigmatisante',
        ] as $indice => $tag) {
            if (str_contains($texte, $indice)) {
                $tags[] = $tag;
            }
        }

        $tags = array_values(array_unique($tags));

        return new IndiceHaine(
            score: $tags === [] ? $empreinte % 12 : min(100, 55 + count($tags) * 15),
            tags: $tags,
            note: $tags === []
                ? 'Aucun signal de rhétorique hostile détecté.'
                : 'Signaux relevés : '.implode(', ', $tags).'.',
        );
    }

    private function raisonnement(VerdictFactCheck $verdict): string
    {
        return '[Simulation] '.$verdict->explication()
            .' Analyse produite par '.$this->moteur->libelle()
            .' sans appel à un service externe.';
    }
}
