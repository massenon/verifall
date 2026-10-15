<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les pièces acceptées pour une vérification d'identité.
 *
 * ## Une liste CLOSE, et béninoise
 *
 * Elle n'est pas générique : la CNI biométrique et la **carte consulaire** sont
 * les documents que possèdent réellement les gens ici, et la seconde n'apparaît
 * dans aucune liste standard. Proposer « Driver's license / State ID » à
 * quelqu'un à Cotonou l'oblige à choisir la mauvaise case, et le dossier se
 * fait refuser pour une raison de formulaire.
 *
 * La liste est fermée parce que l'examinateur doit savoir à quoi s'attendre :
 * un champ libre laisserait entrer « attestation du chef de quartier », qui
 * n'est pas une pièce d'identité et qu'il faudrait refuser une par une.
 */
enum TypePieceIdentite: string
{
    case CarteNationale = 'cni';
    case Passeport = 'passeport';
    case PermisDeConduire = 'permis';
    case CarteConsulaire = 'carte_consulaire';

    public function libelle(): string
    {
        return match ($this) {
            self::CarteNationale => 'Carte nationale d’identité (CNI / CIP)',
            self::Passeport => 'Passeport',
            self::PermisDeConduire => 'Permis de conduire',
            self::CarteConsulaire => 'Carte consulaire',
        };
    }

    /**
     * Le verso est-il exigé ?
     *
     * Un passeport porte tout sur sa page d'identité ; demander un verso qui
     * n'existe pas bloquerait le dépôt sans que personne comprenne pourquoi.
     */
    public function exigeLeVerso(): bool
    {
        return $this !== self::Passeport;
    }
}
