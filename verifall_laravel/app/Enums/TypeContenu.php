<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Format du contenu soumis à vérification.
 *
 * Détermine le coût et les moteurs mobilisés : on n'analyse pas une image
 * comme une phrase.
 */
enum TypeContenu: string
{
    case Texte = 'text';
    case Lien = 'url';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';

    /** Le libellé, dans la langue de la requête — la clé est la VALEUR de l'enum. */
    public function libelle(): string
    {
        return (string) __('types.contenu.'.$this->value);
    }

    public function icone(): string
    {
        return match ($this) {
            self::Texte => 'article',
            self::Lien => 'link',
            self::Image => 'image',
            self::Video => 'videocam',
            self::Audio => 'mic',
        };
    }

    /**
     * Coût en unités — lu dans la configuration, jamais du client.
     */
    public function cout(): int
    {
        return (int) config('factcheck.costs.'.$this->value, 1);
    }

    /** Le contenu est-il un fichier téléversé plutôt qu'une saisie ? */
    /**
     * Le moteur SANS LEQUEL ce type ne peut pas être analysé.
     *
     * ## Pourquoi cette notion existe
     *
     * Un fichier audio n'est pas analysable tant qu'il n'a pas été transcrit :
     * Gemini et la recherche web figurent bien au catalogue de ce type, mais
     * ils travaillent sur du TEXTE, et sans transcription il n'y a pas de texte.
     * Le registre rendrait donc deux moteurs, l'analyse partirait, l'unité
     * serait débitée, et le rapport sortirait vide.
     *
     * Déclarer la dépendance permet à l'écran de FERMER le type au lieu de
     * laisser quelqu'un payer pour rien — et de le rouvrir tout seul le jour où
     * un fournisseur de transcription est souscrit. Aucune liste à maintenir.
     *
     * Le texte, le lien et l'image n'ont pas d'indispensable : chacun garde au
     * moins un moteur capable de travailler seul, Gemini étant multimodal.
     */
    public function moteurIndispensable(): ?MoteurAnalyse
    {
        return match ($this) {
            self::Video, self::Audio => MoteurAnalyse::Transcription,
            default => null,
        };
    }

    public function estFichier(): bool
    {
        return in_array($this, [self::Image, self::Video, self::Audio], true);
    }
}
