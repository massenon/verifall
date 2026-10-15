<?php

declare(strict_types=1);

namespace App\Core\FactChecking\ValueObjects;

use App\Enums\TypeContenu;

/**
 * Ce qu'on donne à un adaptateur pour qu'il travaille.
 *
 * Volontairement pauvre : l'affirmation, son type, son contexte, et le chemin
 * du fichier s'il y en a un. Un adaptateur n'a pas à connaître l'utilisateur,
 * son solde ni son historique — lui passer le modèle entier ouvrirait la porte
 * à ce qu'un service tiers reçoive des données personnelles sans raison.
 */
final readonly class SacDePreuves
{
    public function __construct(
        public string $affirmation,
        public TypeContenu $type,
        public ?string $contexte = null,
        public ?string $cheminFichier = null,
        /** Langue déclarée — le contexte béninois est prioritaire. */
        public string $langue = 'fr',
        /**
         * Phase 3 — `null` tant qu'aucune classification n'est disponible
         * (anciens appels, moteurs appelés hors du pipeline de claims). Le
         * prompt et la fraîcheur de recherche se replient alors sur le
         * comportement d'avant cette phase, à l'identique.
         */
        public ?ContexteDuClaim $contexteTemporel = null,
        /**
         * Contrat V-10 — `null` tant que le cutover n'est pas actif
         * ({@see config('factcheck.collecteur_de_preuves_actif')}). Le
         * prompt et les adaptateurs LLM se replient alors sur le comportement
         * d'avant cette phase, à l'identique : `sources:[{url}]` librement
         * rendu par le modèle. Un paquet non NUL change le contrat de
         * citation — voir {@see ConstructeurDePrompt::pour()}.
         */
        public ?PaquetDePreuves $paquetDePreuves = null,
    ) {}

    /**
     * Version tronquée, sûre à transmettre à un journal.
     */
    public function extraitCourt(int $longueur = 120): string
    {
        return mb_substr(trim($this->affirmation), 0, $longueur);
    }

    /**
     * Même sac, avec un paquet de preuves attaché — contrat V-10.
     */
    public function avecPaquet(PaquetDePreuves $paquet): self
    {
        return new self(
            affirmation: $this->affirmation,
            type: $this->type,
            contexte: $this->contexte,
            cheminFichier: $this->cheminFichier,
            langue: $this->langue,
            contexteTemporel: $this->contexteTemporel,
            paquetDePreuves: $paquet,
        );
    }
}
