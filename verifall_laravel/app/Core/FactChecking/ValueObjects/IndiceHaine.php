<?php

declare(strict_types=1);

namespace App\Core\FactChecking\ValueObjects;

/**
 * Hate Speech Index — bloc « 🛡️ Hate Speech Indicator » du rapport.
 *
 * Mesure la rhétorique hostile du contenu SOUMIS, indépendamment de sa
 * véracité : une information peut être exacte et formulée pour stigmatiser, ou
 * fausse et parfaitement courtoise. Confondre les deux axes ferait juger le
 * fond sur la forme.
 *
 * Le score reste NULL quand aucun moteur ne l'a évalué. Afficher « 0 % » dans
 * ce cas reviendrait à certifier une absence qu'on n'a pas mesurée — sur ce
 * sujet-là, c'est exactement l'erreur à ne pas commettre.
 */
final readonly class IndiceHaine
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public ?int $score,
        public array $tags = [],
        public ?string $note = null,
    ) {}

    public static function nonMesure(): self
    {
        return new self(null);
    }

    public function mesure(): bool
    {
        return $this->score !== null;
    }

    /**
     * Agrège les avis de plusieurs moteurs.
     *
     * On retient le MAXIMUM et non la moyenne : un seul moteur qui repère un
     * ciblage ethnique doit faire lever le drapeau, même si trois autres n'ont
     * rien vu. Moyenner reviendrait à diluer le signal le plus grave.
     *
     * @param  list<self>  $avis
     */
    public static function agreger(array $avis): self
    {
        $mesures = array_values(array_filter($avis, static fn (self $a): bool => $a->mesure()));

        if ($mesures === []) {
            return self::nonMesure();
        }

        $score = max(array_map(static fn (self $a): int => (int) $a->score, $mesures));

        $tags = [];
        $notes = [];

        foreach ($mesures as $avisMesure) {
            $tags = array_merge($tags, $avisMesure->tags);

            if ($avisMesure->note !== null && $avisMesure->note !== '') {
                $notes[] = $avisMesure->note;
            }
        }

        return new self(
            $score,
            array_values(array_unique($tags)),
            $notes === [] ? null : implode(' ', array_slice($notes, 0, 3)),
        );
    }

    /**
     * Libellé du bandeau. Trois paliers plutôt qu'un seuil unique : entre
     * « conforme » et « haineux » il y a la zone où une formulation dérape sans
     * franchir la ligne, et c'est celle qu'il faut nommer.
     */
    public function libelle(): string
    {
        return match (true) {
            ! $this->mesure() => 'Non évalué',
            $this->score < 20 => 'Conforme',
            $this->score < 60 => 'Rhétorique à surveiller',
            default => 'Signaux de discours haineux',
        };
    }

    public function badge(): string
    {
        return match (true) {
            ! $this->mesure() => 'vbadge vbadge-progress',
            $this->score < 20 => 'vbadge vbadge-true',
            $this->score < 60 => 'vbadge vbadge-doubt',
            default => 'vbadge vbadge-false',
        };
    }

    /** Le bloc doit-il être mis en avant dans le rapport ? */
    public function alerte(): bool
    {
        return $this->mesure() && $this->score >= 60;
    }
}
