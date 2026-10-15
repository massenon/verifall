<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Verdict rendu sur une affirmation.
 *
 * Quatre valeurs, et pas une de plus. « NonVerifiable » n'est pas un échec :
 * c'est un résultat honnête, et le seul acceptable quand les preuves manquent.
 * Sans lui, un moteur serait poussé à trancher au hasard.
 */
enum VerdictFactCheck: string
{
    case Vrai = 'true';
    case Faux = 'false';
    case Trompeur = 'misleading';
    case NonVerifiable = 'unverifiable';

    /**
     * Le mot du verdict, dans la langue de la requête.
     *
     * Il passe par les fichiers de langue et non par un `match` en dur : ces
     * quatre mots sont ce que la plateforme AFFIRME, et ils doivent se lire en
     * anglais aussi bien qu'en français pour quelqu'un qui reçoit le lien
     * depuis le Nigeria voisin.
     *
     * La clé est la VALEUR de l'enum, jamais son nom. C'est aussi ce que porte
     * la base, donc une traduction manquante se repère à ce qu'elle affiche la
     * clé brute plutôt qu'un mot faux.
     */
    public function libelle(): string
    {
        return (string) __('verdicts.court.'.$this->value);
    }

    /**
     * Le libellé dans TOUTES les langues, en minuscules.
     *
     * Pour ce qui doit reconnaître un mot écrit ailleurs, plus tôt, par
     * quelqu'un d'autre — l'import d'un tableur, typiquement. Le fichier ne
     * change pas de langue parce que son lecteur en a changé : y répondre par
     * `libelle()` seul revenait à refuser « trompeur » dès qu'un membre du
     * personnel avait consulté le site public en anglais, sans que rien ne
     * l'explique.
     *
     * Accepter les deux langues vaut mieux que figer le français : un tableur
     * préparé en anglais fonctionne aussi, et personne n'a à deviner laquelle
     * était attendue.
     *
     * @return list<string>
     */
    public function libellesReconnus(): array
    {
        $libelles = [$this->value];

        foreach (Langue::cases() as $langue) {
            $libelles[] = (string) __('verdicts.court.'.$this->value, [], $langue->value);
        }

        return array_values(array_unique(array_map(mb_strtolower(...), $libelles)));
    }

    /** Classe de pastille du design system. */
    public function badge(): string
    {
        return match ($this) {
            self::Vrai => 'vbadge vbadge-true',
            self::Faux => 'vbadge vbadge-false',
            self::Trompeur => 'vbadge vbadge-doubt',
            self::NonVerifiable => 'vbadge vbadge-progress',
        };
    }

    /**
     * Pastille du FRONT PUBLIC (facts.html).
     *
     * Le prototype n'y affiche que des analyses fausses ; sa pastille rouge est
     * la seule que le build figé de Tailwind contienne. Les quatre variantes
     * sont donc déclarées dans `pages/public-facts.css`, celle du faux
     * reproduisant l'originale à l'identique.
     */
    public function pastillePublique(): string
    {
        return 'fact-pill '.match ($this) {
            self::Vrai => 'fact-pill--vrai',
            self::Faux => 'fact-pill--faux',
            self::Trompeur => 'fact-pill--trompeur',
            self::NonVerifiable => 'fact-pill--inconnu',
        };
    }

    /**
     * Bannière de verdict, en tête de la page de détail publique.
     *
     * Pleine et non translucide, contrairement à la pastille des cartes : sur
     * la page de détail, le verdict n'accompagne pas le titre, il l'annonce.
     */
    public function pastilleVerdict(): string
    {
        return 'fact-verdict '.match ($this) {
            self::Vrai => 'fact-verdict--vrai',
            self::Faux => 'fact-verdict--faux',
            self::Trompeur => 'fact-verdict--trompeur',
            self::NonVerifiable => 'fact-verdict--inconnu',
        };
    }

    /**
     * Couleur de la barre de fiabilité.
     *
     * Le prototype la colore selon le verdict (`cb.style.background = t.color`)
     * et il a raison : une barre VERTE sous un verdict « non vérifiable » dit
     * « tout va bien » là où il n'y a précisément aucune assurance — et sous un
     * « faux », elle féliciterait le lecteur d'avoir trouvé une intox.
     *
     * Les teintes sont celles des pastilles du design system, reprises
     * telles quelles.
     */
    public function couleurBarre(): string
    {
        return match ($this) {
            self::Vrai => '#16A34A',
            self::Faux => '#DC2626',
            self::Trompeur => '#D97706',
            self::NonVerifiable => '#2563EB',
        };
    }

    /**
     * Pastille pour les pages PUBLIQUES.
     *
     * Le shell public ne charge pas `citizen.css`, où vivent les classes
     * `vbadge` : le badge y sortait gris, sans distinguer un verdict « vrai »
     * d'un « faux ». Y ajouter la feuille citoyenne entière importerait un
     * design system complet pour trois pastilles, et ses collisions avec lui.
     *
     * Ces classes-ci sont dans le build Tailwind figé du prototype — vérifié.
     * `vdoubt` n'y est pas : « trompeur » emprunte l'orangé d'investigation.
     */
    public function badgePublic(): string
    {
        $base = 'inline-flex items-center rounded-full text-white font-bold px-4 py-1.5';

        return $base.' '.match ($this) {
            self::Vrai => 'bg-vtrue',
            self::Faux => 'bg-vfalse',
            self::Trompeur => 'bg-vinvest',
            self::NonVerifiable => 'bg-primary',
        };
    }

    /**
     * Phrase destinée au citoyen, pas au technicien.
     */
    public function explication(): string
    {
        return (string) __('verdicts.explication.'.$this->value);
    }

    /*
    |---------------------------------------------------------------------------
    | Le Hero du rapport citoyen
    |---------------------------------------------------------------------------
    |
    | Ces six méthodes ont été ramenées ici le 25 septembre 2026. Elles vivaient
    | dans un `match (true)` en tête de `rapport.blade.php`, qui testait
    | `str_contains($verdict, 'faux')` sur la valeur de CET enum — une logique
    | métier dans un gabarit, et un second jeu de règles à côté de celui-ci.
    |
    | Ce n'était pas qu'une question de propreté. Les dix-huit classes que ce
    | tableau composait — `bg-red-50/80`, `border-emerald-200`, `bg-amber-600`…
    | — N'EXISTENT PAS dans le build Tailwind figé. Le Hero s'affichait sans
    | fond, sans bordure et sans badge coloré, et aucun test ne pouvait le dire
    | : écrites dans un tableau PHP, elles n'étaient dans aucun attribut
    | `class`, donc invisibles pour `ClassesTailwindPresentesTest`.
    |
    | Les classes rendues ici sont celles de `assets/css/pages/rapport.css`,
    | écrites à la main — la feuille PEUT grandir, contrairement au build figé.
    |
    */

    /** Fond et bordure pastel de la carte Hero. */
    public function carteRapport(): string
    {
        return match ($this) {
            self::Vrai => 'vf-report-hero-true',
            self::Faux => 'vf-report-hero-false',
            self::Trompeur => 'vf-report-hero-warning',
            self::NonVerifiable => 'vf-report-hero-neutral',
        };
    }

    /** Pastille pleine « VERDICT : … ». */
    public function badgeRapport(): string
    {
        return match ($this) {
            self::Vrai => 'vf-badge-true',
            self::Faux => 'vf-badge-false',
            self::Trompeur => 'vf-badge-warning',
            self::NonVerifiable => 'vf-badge-neutral',
        };
    }

    /** Teinte du texte et de l'icône du médaillon. */
    public function texteRapport(): string
    {
        return match ($this) {
            self::Vrai => 'vf-text-true',
            self::Faux => 'vf-text-false',
            self::Trompeur => 'vf-text-warning',
            self::NonVerifiable => 'vf-text-neutral',
        };
    }

    /**
     * Icône du grand médaillon central — Material Symbols Rounded.
     *
     * `cancel` et non `close` : le disque barré se lit comme un refus même
     * réduit à seize pixels dans un aperçu de partage, là où une croix nue
     * passe pour un bouton de fermeture.
     */
    public function medaillon(): string
    {
        return match ($this) {
            self::Vrai => 'check_circle',
            self::Faux => 'cancel',
            self::Trompeur => 'warning',
            self::NonVerifiable => 'help',
        };
    }

    /**
     * Le mot du badge, en toutes lettres.
     *
     * Distinct de `libelle()` : celui-ci rend « Faux », le badge du rapport
     * annonce « INFORMATION FAUSSE ». Le premier qualifie une ligne de
     * tableau, le second est une DÉCISION affichée en grand.
     */
    public function libelleRapport(): string
    {
        return (string) __('verdicts.rapport.libelle.'.$this->value);
    }

    /** La pilule d'explication, sous l'affirmation analysée. */
    public function phraseRapport(): string
    {
        return (string) __('verdicts.rapport.phrase.'.$this->value);
    }
}
