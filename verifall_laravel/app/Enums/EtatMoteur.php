<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État réel d'un moteur d'analyse, tel qu'un écran a le droit de l'annoncer.
 *
 * ## Pourquoi cinq états et non deux
 *
 * `RegistreDesMoteurs::etat()` ne rendait qu'un booléen « disponible », calculé
 * sur une seule question : *une classe d'adaptateur existe-t-elle ?* Elle ne
 * regardait ni la clé, ni le service. Le 1er septembre 2026, cela donnait, sur
 * la configuration réelle du projet :
 *
 *   · **Perplexity** annoncé disponible avec une clé **vide** ;
 *   · **Google Fact Check** annoncé disponible avec une clé que Google refuse
 *     — « API Key not found », vérifié par appel.
 *
 * Une carte « Santé du système » qui affiche « Opérationnel » en vert sur deux
 * moteurs morts est pire que pas de carte : elle transforme une panne en
 * confiance. Les trois questions sont distinctes, et se posent dans cet ordre.
 *
 *   1. L'adaptateur est-il ÉCRIT ?        → NonPorte
 *   2. La clé est-elle RENSEIGNÉE ?       → NonConfigure
 *   3. Le service RÉPOND-il ?             → Injoignable / Operationnel
 *
 * Quand la troisième ne peut pas se poser — service sans point d'appel gratuit
 * en lecture seule, ou analyse purement locale — l'état est `Configure`, qui
 * dit exactement cela : la clé est là, personne n'a vérifié.
 */
enum EtatMoteur: string
{
    /**
     * ÉTEINT par décision d'exploitation, dans les réglages.
     *
     * À ne surtout pas confondre avec `NonConfigure` : la clé peut être là et
     * parfaitement valide. On a choisi de ne pas appeler — TinEye est payant,
     * Vision dépend d'une clé refusée, aucun fournisseur de transcription n'est
     * souscrit. Afficher « clé absente » sur un moteur qu'on a volontairement
     * coupé enverrait quelqu'un chercher une panne qui n'existe pas.
     */
    case Desactive = 'desactive';

    /** Aucun adaptateur n'est encore écrit pour ce moteur. */
    case NonPorte = 'non_porte';

    /** L'adaptateur existe, mais aucune clé n'est renseignée. */
    case NonConfigure = 'non_configure';

    /** Clé présente, service jamais contacté (pas de sonde gratuite possible). */
    case Configure = 'configure';

    /** Clé présente, mais le service la REFUSE ou ne répond pas. */
    case Injoignable = 'injoignable';

    /** Clé présente et acceptée par le fournisseur, à la dernière sonde. */
    case Operationnel = 'operationnel';

    /** `FAKE_ADAPTERS=true` : les verdicts sont inventés, rien n'est réel. */
    case Simule = 'simule';

    public function libelle(): string
    {
        return match ($this) {
            self::Desactive => 'Éteint',
            self::NonPorte => 'Non porté',
            self::NonConfigure => 'Clé absente',
            self::Configure => 'Configuré',
            self::Injoignable => 'Injoignable',
            self::Operationnel => 'Opérationnel',
            self::Simule => 'Simulé',
        };
    }

    /**
     * Ce que l'exploitant doit comprendre, en une phrase.
     *
     * Un état sans explication oblige à ouvrir le code pour savoir quoi faire ;
     * la carte doit se suffire à elle-même.
     */
    public function explication(): string
    {
        return match ($this) {
            self::Desactive => 'Éteint dans les réglages — Moteurs d’analyse.',
            self::NonPorte => "L'adaptateur reste à écrire.",
            self::NonConfigure => 'Renseignez la clé dans .env.',
            self::Configure => 'Clé présente ; ce service n’offre pas de contrôle gratuit.',
            self::Injoignable => 'Le fournisseur refuse la clé ou ne répond pas.',
            self::Operationnel => 'Clé acceptée par le fournisseur.',
            self::Simule => 'FAKE_ADAPTERS=true — les verdicts sont inventés.',
        };
    }

    /**
     * Le moteur peut-il réellement peser dans un verdict ?
     *
     * `Configure` compte : refuser de compter un moteur faute de sonde
     * reviendrait à punir un fournisseur de ne pas exposer d'URL de contrôle.
     */
    public function compteDansLeScore(): bool
    {
        return $this === self::Operationnel || $this === self::Configure;
    }

    /**
     * Classe de pastille, prise dans le build Tailwind figé et `staff.css`.
     *
     * Aucune classe nouvelle : une classe absente de `tailwind.css` n'a aucun
     * effet et personne ne s'en aperçoit avant la mise en ligne.
     */
    public function pastille(): string
    {
        return match ($this) {
            self::Operationnel => 'st-badge st-badge-pub',
            self::Configure => 'st-badge st-badge-info',
            self::Simule, self::NonConfigure => 'st-badge st-badge-warn',
            self::Injoignable => 'st-badge st-badge-danger',
            self::Desactive, self::NonPorte => 'st-badge st-badge-arch',
        };
    }
}
