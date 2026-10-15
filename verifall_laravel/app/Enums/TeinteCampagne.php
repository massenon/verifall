<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * La teinte d'une campagne, traduite vers une classe qui EXISTE.
 *
 * ## Pourquoi une traduction, et pas la classe directement
 *
 * Le vocabulaire des campagnes est celui de Bootstrap — `success`, `primary`,
 * `info`. Le build Tailwind du prototype est FIGÉ et n'en connaît aucun sous
 * cette forme : ni `bg-success`, ni `bg-info`. Les écrire aurait produit des
 * boutons sans fond, et le défaut serait resté invisible en relecture — la
 * classe EST là, elle ne fait simplement rien.
 *
 * Les classes rendues vivent donc dans `assets/css/pages/campagnes.css`, où
 * elles sont réellement déclarées. Le nom, lui, reste celui du porteur : c'est
 * son vocabulaire qui pilote la donnée, pas le nôtre.
 */
enum TeinteCampagne: string
{
    case Succes = 'success';
    case Principale = 'primary';
    case Information = 'info';
    case Alerte = 'danger';

    /**
     * Un nom inconnu se rabat sur la teinte principale.
     *
     * Plutôt que de lever : une couleur invalide en base ne doit pas empêcher
     * une campagne de s'afficher. Le pire cas est un bouton vert au lieu d'un
     * bouton bleu, ce qui n'a jamais coûté un visiteur.
     */
    public static function depuis(?string $nom): self
    {
        return self::tryFrom((string) $nom) ?? self::Principale;
    }

    /** La classe de fond de l'appel à l'action. */
    public function classe(): string
    {
        return 'vf-campaign-tone-'.$this->value;
    }
}
