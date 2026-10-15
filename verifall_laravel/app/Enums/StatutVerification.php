<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Où en est une vérification.
 *
 * `RevueHumaine` est un état à part entière et non un échec : quand les moteurs
 * se contredisent, publier un faux consensus serait pire que d'attendre. Le
 * rapport existe, il porte les deux thèses, et un humain tranche.
 */
enum StatutVerification: string
{
    case EnFile = 'queued';
    case EnCours = 'analyzing';
    /**
     * Les moteurs n'ont pas pu répondre : l'analyse est REPROGRAMMÉE.
     *
     * Ce n'est pas un échec mais une attente. Rendre un rapport creux —
     * « verdict indisponible » — serait la pire réponse possible : la personne
     * repart sans rien, avec l'impression que la plateforme ne marche pas.
     * Elle est prévenue, et l'analyse repart d'elle-même.
     */
    case EnAttenteDeReprise = 'retry_scheduled';

    case Terminee = 'completed';
    case RevueHumaine = 'human_review';
    case Echouee = 'failed';

    public function estFinal(): bool
    {
        return in_array($this, [self::Terminee, self::RevueHumaine, self::Echouee], true);
    }

    /**
     * Les états où l'analyse n'est pas finie, pour filtrer en base.
     *
     * Une REPRISE PROGRAMMÉE en fait partie : du point de vue de la personne,
     * sa demande est toujours en cours. L'exclure du compte « en cours » lui
     * ferait croire qu'elle s'est volatilisée.
     *
     * Une seule source : deux listes écrites à la main finissent toujours par
     * diverger, et c'est le compteur qui ment en premier.
     *
     * @return list<string>
     */
    public static function valeursEnCours(): array
    {
        return array_values(array_map(
            static fn (self $statut): string => $statut->value,
            array_filter(self::cases(), static fn (self $statut): bool => ! $statut->estFinal()),
        ));
    }

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('statuts.verification.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::EnFile, self::EnCours => 'vbadge vbadge-progress',
            self::EnAttenteDeReprise => 'vbadge vbadge-doubt',
            self::Terminee => 'vbadge vbadge-true',
            self::RevueHumaine => 'vbadge vbadge-doubt',
            self::Echouee => 'vbadge vbadge-false',
        };
    }
}
