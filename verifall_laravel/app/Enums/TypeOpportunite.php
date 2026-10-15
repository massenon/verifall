<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nature d'une opportunité publiée au Hub.
 *
 * Une liste FERMÉE plutôt qu'un champ libre. Sur un champ libre, la même chose
 * finit écrite de six façons — « stage », « Stage », « stage pro », « internship »
 * — et le filtre cesse de fonctionner sans que personne s'en aperçoive : il
 * rend simplement moins de résultats.
 *
 * `Autre` existe pour que l'équipe ne soit jamais bloquée par une catégorie
 * manquante. Si elle s'en sert souvent, c'est le signe qu'un cas manque à cette
 * liste — pas qu'il faut ouvrir le champ.
 */
enum TypeOpportunite: string
{
    case Cdi = 'cdi';
    case Cdd = 'cdd';
    case Stage = 'stage';
    case Alternance = 'alternance';
    case Volontariat = 'volontariat';
    case Bourse = 'bourse';
    case Financement = 'financement';
    case Conference = 'conference';
    case AppelACandidature = 'appel';
    case Autre = 'autre';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('types.opportunite.'.$this->value);
    }

    /**
     * Ces opportunités sont-elles un emploi salarié ?
     *
     * Sert à nuancer le message de mise en garde : « aucun frais de dossier »
     * vise le recrutement, où l'arnaque est la plus répandue. L'écrire sous une
     * conférence payante serait faux.
     */
    public function estUnEmploi(): bool
    {
        return in_array($this, [self::Cdi, self::Cdd, self::Stage, self::Alternance], true);
    }
}
