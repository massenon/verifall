<?php

declare(strict_types=1);

namespace App\Core\Application\Statistiques;

use App\Enums\PeriodeTableauDeBord;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Une fenêtre de calcul du tableau de bord, et la fenêtre qui la précède.
 *
 * ## Intervalle semi-ouvert [début, fin)
 *
 * Toutes les requêtes filtrent `>= début AND < fin`. Deux périodes
 * consécutives ne comptent donc jamais deux fois la même ligne, et la
 * comparaison « vs période précédente » additionne exactement ce qu'elle dit.
 *
 * ## La période précédente a la MÊME durée
 *
 * « Cette année » au 14 septembre se compare au 1er janvier – 14 septembre de
 * l'année passée, pas à l'année passée entière : comparer huit mois et demi à
 * douze fabriquerait une baisse qui n'existe pas.
 *
 * ## Une période personnalisée invalide retombe sur le défaut
 *
 * Les bornes arrivent par l'adresse. Une date illisible, inversée ou dans le
 * futur ne lève rien : la période redevient « 30 derniers jours », et l'écran
 * affiche la période réellement calculée — jamais celle qui a été demandée.
 */
final readonly class PeriodeDAnalyse
{
    /** Au-delà, une agrégation « personnalisée » deviendrait un export. */
    public const JOURS_MAXIMUM = 366;

    private function __construct(
        public PeriodeTableauDeBord $choix,
        public CarbonImmutable $debut,
        public CarbonImmutable $fin,
    ) {}

    public static function depuis(string $choix, ?string $du = null, ?string $au = null, ?CarbonImmutable $maintenant = null): self
    {
        $maintenant ??= CarbonImmutable::now();
        $retenu = PeriodeTableauDeBord::tryFrom($choix) ?? PeriodeTableauDeBord::DEFAUT;

        return match ($retenu) {
            PeriodeTableauDeBord::Aujourdhui => new self($retenu, $maintenant->startOfDay(), $maintenant),
            PeriodeTableauDeBord::SeptJours => new self($retenu, $maintenant->subDays(6)->startOfDay(), $maintenant),
            PeriodeTableauDeBord::TrenteJours => new self($retenu, $maintenant->subDays(29)->startOfDay(), $maintenant),
            PeriodeTableauDeBord::QuatreVingtDixJours => new self($retenu, $maintenant->subDays(89)->startOfDay(), $maintenant),
            PeriodeTableauDeBord::CetteAnnee => new self($retenu, $maintenant->startOfYear(), $maintenant),
            PeriodeTableauDeBord::Personnalisee => self::personnalisee($du, $au, $maintenant)
                ?? self::depuis(PeriodeTableauDeBord::DEFAUT->value, null, null, $maintenant),
        };
    }

    private static function personnalisee(?string $du, ?string $au, CarbonImmutable $maintenant): ?self
    {
        $debut = self::date($du);
        $fin = self::date($au);

        if ($debut === null || $fin === null || $debut->gt($fin) || $debut->gt($maintenant)) {
            return null;
        }

        // Une borne de fin dans le futur est ramenée à aujourd'hui : on ne
        // compte pas des jours qui n'ont pas encore eu lieu.
        if ($fin->gt($maintenant->startOfDay())) {
            $fin = $maintenant->startOfDay();
        }

        if ($debut->diffInDays($fin) >= self::JOURS_MAXIMUM) {
            $debut = $fin->subDays(self::JOURS_MAXIMUM - 1);
        }

        $finExclusive = $fin->addDay();

        return new self(
            PeriodeTableauDeBord::Personnalisee,
            $debut,
            $finExclusive->gt($maintenant) ? $maintenant : $finExclusive,
        );
    }

    private static function date(?string $valeur): ?CarbonImmutable
    {
        if ($valeur === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $valeur);
        } catch (Throwable) {
            return null;
        }

        // `createFromFormat` accepte le 31 février en le reportant en mars.
        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $valeur ? $date : null;
    }

    /** La fenêtre de même durée, juste avant. */
    public function precedente(): self
    {
        if ($this->choix === PeriodeTableauDeBord::CetteAnnee || $this->choix === PeriodeTableauDeBord::Aujourdhui) {
            $recul = $this->choix === PeriodeTableauDeBord::CetteAnnee
                ? fn (CarbonImmutable $d): CarbonImmutable => $d->subYear()
                : fn (CarbonImmutable $d): CarbonImmutable => $d->subDay();

            return new self($this->choix, $recul($this->debut), $recul($this->fin));
        }

        $duree = (int) $this->debut->diffInSeconds($this->fin);

        return new self($this->choix, $this->debut->subSeconds($duree), $this->debut);
    }

    /** La période court-elle jusqu'à maintenant ? Certaines mesures n'existent qu'au présent. */
    public function seTermineMaintenant(): bool
    {
        return $this->fin->diffInMinutes(CarbonImmutable::now(), true) < 5;
    }

    /** Nombre de jours calendaires couverts, au moins un. */
    public function jours(): int
    {
        return max(1, (int) ceil($this->debut->diffInSeconds($this->fin) / 86400));
    }

    /**
     * Le pas des séries : heure, jour, semaine ou mois.
     *
     * Choisi pour qu'une courbe ait entre 7 et 62 points — assez pour montrer
     * une forme, trop peu pour devenir un trait illisible sur un téléphone.
     */
    public function granularite(): string
    {
        $jours = $this->jours();

        return match (true) {
            $jours <= 1 => 'heure',
            $jours <= 62 => 'jour',
            $jours <= 182 => 'semaine',
            default => 'mois',
        };
    }

    /**
     * Les tranches de la série, bornes semi-ouvertes.
     *
     * @return list<array{debut: CarbonImmutable, fin: CarbonImmutable, etiquette: string}>
     */
    public function tranches(): array
    {
        $tranches = [];
        $curseur = $this->debut;
        $granularite = $this->granularite();

        while ($curseur->lt($this->fin)) {
            $suivant = match ($granularite) {
                'heure' => $curseur->addHour(),
                'jour' => $curseur->addDay(),
                'semaine' => $curseur->addWeek(),
                default => $curseur->startOfMonth()->addMonth(),
            };

            $tranches[] = [
                'debut' => $curseur,
                'fin' => $suivant->gt($this->fin) ? $this->fin : $suivant,
                'etiquette' => match ($granularite) {
                    'heure' => $curseur->format('H\h'),
                    'jour', 'semaine' => $curseur->format('d/m'),
                    default => $curseur->translatedFormat('M Y'),
                },
            ];

            $curseur = $suivant;
        }

        return $tranches;
    }

    /** Ce qu'affiche l'en-tête : la période RÉELLEMENT calculée. */
    public function libelle(): string
    {
        if ($this->choix !== PeriodeTableauDeBord::Personnalisee) {
            return $this->choix->libelle();
        }

        return sprintf(
            'Du %s au %s',
            $this->debut->format('d/m/Y'),
            $this->fin->subSecond()->format('d/m/Y'),
        );
    }

    public function libelleComparaison(): string
    {
        return match ($this->choix) {
            PeriodeTableauDeBord::Aujourdhui => 'vs hier à la même heure',
            PeriodeTableauDeBord::CetteAnnee => "vs même période l'an dernier",
            default => sprintf('vs %d jours précédents', $this->jours()),
        };
    }

    /** Clé de cache : deux périodes égales partagent leurs agrégats. */
    public function cle(): string
    {
        // À la minute près pour les périodes glissantes : sans cet arrondi,
        // chaque seconde écoulée produirait une clé neuve et le cache ne
        // servirait jamais.
        return $this->debut->format('YmdHi').'-'.$this->fin->format('YmdHi');
    }
}
