<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les cinq formes de question de la banque.
 *
 * Ce n'est pas une nuance d'affichage : c'est ce qui décide QUI corrige. Une
 * question à choix se corrige toute seule ; une réponse courte ne se corrige
 * qu'à la main, et c'est elle — elle seule — qui alimente la file de
 * `correction-examens.html`. Un examen sans réponse courte rend sa note à la
 * soumission ; un examen qui en contient une attend un mentor.
 */
enum TypeQuestion: string
{
    case Qcm = 'qcm';
    case VraiFaux = 'true_false';
    case MultiSelect = 'multi_select';
    case ReponseCourte = 'short_answer';
    case Appariement = 'matching';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('types.question.'.$this->value);
    }

    public function badge(): string
    {
        return 'st-badge st-badge-info';
    }

    /**
     * La machine sait-elle trancher seule ?
     *
     * Une réponse courte notée par comparaison de chaînes recalerait
     * « recherche inversée » face à « la recherche inversée ». Mieux vaut une
     * file de correction assumée qu'une note fausse rendue vite.
     */
    public function correctionAutomatique(): bool
    {
        return $this !== self::ReponseCourte;
    }

    /** Plusieurs propositions peuvent-elles être justes en même temps ? */
    public function reponsesMultiples(): bool
    {
        return $this === self::MultiSelect || $this === self::Appariement;
    }

    /** A-t-elle besoin de propositions saisies à l'avance ? */
    public function exigeDesPropositions(): bool
    {
        return $this !== self::ReponseCourte;
    }
}
