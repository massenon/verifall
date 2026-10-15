<?php

declare(strict_types=1);

namespace App\Core\FactChecking\ValueObjects;

use App\Enums\MoteurAnalyse;
use App\Enums\VerdictFactCheck;

/**
 * Ce qu'un moteur a répondu.
 *
 * Un échec est un RÉSULTAT, pas une exception : `echoue = true` avec son motif.
 * Laisser remonter une exception ferait tomber toute l'analyse parce qu'un
 * service tiers est en panne — alors que onze autres moteurs ont répondu.
 */
final readonly class VerdictMoteur
{
    /**
     * @param  list<Preuve>  $preuves
     */
    private function __construct(
        public MoteurAnalyse $moteur,
        public ?VerdictFactCheck $verdict,
        public int $confiance,
        public array $preuves,
        public string $raisonnement,
        public int $latenceMs,
        public bool $echoue,
        public ?string $motifEchec,
        /**
         * Rhétorique du contenu, si ce moteur l'a évaluée.
         *
         * Portée par le verdict de moteur et non calculée à part : c'est le
         * même appel qui la produit — le prompt demande verdict ET score de
         * haine dans la même réponse. Deux appels coûteraient deux fois plus
         * pour une information que le modèle a déjà sous les yeux.
         */
        public IndiceHaine $indiceHaine = new IndiceHaine(null),
    ) {}

    /**
     * @param  list<Preuve>  $preuves
     */
    public static function rendu(
        MoteurAnalyse $moteur,
        ?VerdictFactCheck $verdict,
        int $confiance,
        array $preuves = [],
        string $raisonnement = '',
        int $latenceMs = 0,
        ?IndiceHaine $indiceHaine = null,
    ): self {
        return new self(
            $moteur, $verdict,
            // Bornée : une confiance de 140 % ne veut rien dire, et fausserait
            // le score général.
            max(0, min(100, $confiance)),
            $preuves, $raisonnement, $latenceMs, false, null,
            $indiceHaine ?? IndiceHaine::nonMesure(),
        );
    }

    public static function echec(MoteurAnalyse $moteur, string $motif, int $latenceMs = 0): self
    {
        return new self($moteur, null, 0, [], '', $latenceMs, true, $motif, IndiceHaine::nonMesure());
    }

    /**
     * Ce résultat pèse-t-il dans le verdict final ?
     */
    public function compteDansLeScore(): bool
    {
        return ! $this->echoue
            && $this->verdict !== null
            && $this->moteur->porteUnVerdict();
    }
}
