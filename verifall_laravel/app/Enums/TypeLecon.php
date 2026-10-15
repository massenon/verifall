<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nature d'une leçon.
 *
 * Le prototype n'en connaissait que deux. `Audio` a été ajouté le 2 septembre
 * 2026 : un cours peut porter un enregistrement — entretien, témoignage,
 * chronique — sans qu'il faille l'habiller d'une vidéo pour le déposer.
 *
 * À ne pas confondre avec le TYPE DE CONTENU d'une vérification
 * ({@see TypeContenu}), dont l'audio est fermé faute de moteur de
 * transcription : ici on DIFFUSE un fichier, on ne l'analyse pas.
 */
enum TypeLecon: string
{
    case Texte = 'text';
    case Video = 'video';
    case Audio = 'audio';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('types.lecon.'.$this->value);
    }

    /** Icône Material du prototype. */
    public function icone(): string
    {
        return match ($this) {
            self::Texte => 'article',
            self::Video => 'play_circle',
            self::Audio => 'graphic_eq',
        };
    }
}
