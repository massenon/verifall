<?php

declare(strict_types=1);

namespace App\Core\Application\Reglages;

/**
 * Ce qu'un réglage accepte — et donc comment il se saisit et se valide.
 *
 * Le type porte les DEUX à la fois, et c'est délibéré : séparer « quel contrôle
 * afficher » de « quoi accepter » laisse dériver les deux. Un champ affiché en
 * `type="url"` mais validé comme du texte libre accepte
 * `javascript:alert(1)` — et ce lien finit dans le pied de page du site
 * public.
 */
enum TypeDeReglage: string
{
    case Texte = 'texte';
    case TexteLong = 'texte_long';
    case Nombre = 'nombre';
    case Courriel = 'courriel';
    case Url = 'url';
    case MotDePasse = 'mot_de_passe';
    case Booleen = 'booleen';
    case Choix = 'choix';
    case Fichier = 'fichier';
    case Image = 'image';

    /**
     * Les règles imposées par le type lui-même.
     *
     * @return list<string>
     */
    public function reglesDeBase(): array
    {
        return match ($this) {
            self::Texte => ['nullable', 'string', 'max:255'],
            self::TexteLong => ['nullable', 'string', 'max:5000'],
            self::Nombre => ['nullable', 'integer', 'min:0', 'max:1000000'],
            self::Courriel => ['nullable', 'email:rfc,dns', 'max:255'],
            /*
             | `https` seul. Une URL déclarée ici finit dans le pied de page, un
             | manifeste ou une balise OpenGraph : en HTTP, elle déclasse la
             | page qui la porte et expose ceux qui la suivent.
             */
            self::Url => ['nullable', 'url', 'starts_with:https://', 'max:255'],
            self::MotDePasse => ['nullable', 'string', 'max:500'],
            self::Booleen => ['boolean'],
            self::Choix => ['nullable', 'string', 'max:60'],
            /*
             | La liste des types autorisés est FERMÉE, et ne contient aucun
             | format exécutable. On ne fait jamais confiance au nom du fichier
             | ni à ce que le navigateur annonce : `mimes` vérifie le contenu.
             */
            self::Image => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            self::Fichier => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:5120'],
        };
    }

    /** Le contrôle HTML correspondant. */
    public function controle(): string
    {
        return match ($this) {
            self::TexteLong => 'textarea',
            self::Booleen => 'checkbox',
            self::Choix => 'select',
            self::Fichier, self::Image => 'file',
            default => 'input',
        };
    }

    /** L'attribut `type` d'un `<input>`. */
    public function typeHtml(): string
    {
        return match ($this) {
            self::Nombre => 'number',
            self::Courriel => 'email',
            self::Url => 'url',
            self::MotDePasse => 'password',
            default => 'text',
        };
    }

    public function estUnFichier(): bool
    {
        return $this === self::Fichier || $this === self::Image;
    }
}
