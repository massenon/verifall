<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les langues servies par la plateforme.
 *
 * ## Deux, et pas davantage aujourd'hui
 *
 * La désinformation qui touche le Bénin circule aussi en fon, yoruba, bariba
 * et dendi, et massivement en haoussa depuis le Nigeria voisin. Ces langues
 * comptent, et elles ne sont pas ici : les ajouter demanderait des
 * vérificateurs qui les parlent, pas des fichiers de traduction. Écrire une
 * interface en fon sans pouvoir y publier un seul fact-check serait une
 * promesse vide.
 *
 * L'anglais entre parce que le voisin nigérian produit une part importante de
 * ce qui circule au Bénin — l'exemple du prototype lui-même est une vidéo du
 * Sénat nigérian.
 *
 * ## Le français est le DÉFAUT, en toutes circonstances
 *
 * Jamais déduit de l'en-tête `Accept-Language` du navigateur. Un téléphone
 * vendu au Bénin sort d'usine en anglais dans une proportion notable, et
 * servir l'anglais à quelqu'un qui lit le français parce que son appareil n'a
 * jamais été configuré serait une dégradation, pas une attention.
 *
 * Le choix explicite est retenu ; à défaut de choix, le français.
 */
enum Langue: string
{
    case Fr = 'fr';
    case En = 'en';

    public static function defaut(): self
    {
        return self::Fr;
    }

    /**
     * Le nom de la langue, écrit DANS cette langue.
     *
     * Il passe par les fichiers de langue pour suivre le motif des autres
     * énumérations, mais `lang/fr/langues.php` et `lang/en/langues.php`
     * portent VOLONTAIREMENT les mêmes valeurs : quelqu'un qui ne lit pas le
     * français doit reconnaître « English » sur la page française, sinon il ne
     * trouve pas le bouton qui l'en sortirait.
     */
    public function libelle(): string
    {
        return (string) __('langues.nom.'.$this->value);
    }

    /** Deux lettres, pour le sélecteur compact de l'en-tête. */
    public function code(): string
    {
        return match ($this) {
            self::Fr => 'FR',
            self::En => 'EN',
        };
    }

    /**
     * La valeur de l'attribut `lang` du document.
     *
     * Distincte du code de langue : un lecteur d'écran change de voix sur
     * cette valeur, et un moteur de recherche s'en sert pour savoir à qui
     * proposer la page.
     */
    public function balise(): string
    {
        return match ($this) {
            self::Fr => 'fr',
            self::En => 'en',
        };
    }

    public static function depuis(?string $valeur): self
    {
        return self::tryFrom((string) $valeur) ?? self::defaut();
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(static fn (self $langue): string => $langue->value, self::cases());
    }
}
