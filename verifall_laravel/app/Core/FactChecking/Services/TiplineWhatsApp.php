<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Application\Reglages\Reglages;

/**
 * La ligne WhatsApp : on transfère un message suspect, on reçoit un verdict.
 *
 * ## Pourquoi elle compte plus que le reste, dans ce contexte précis
 *
 * En Afrique de l'Ouest francophone, la désinformation ne circule pas sur le
 * web. Elle circule sur WhatsApp, en images, en captures d'écran et en
 * messages vocaux transférés. Une tipline transforme la vérification d'une
 * DÉMARCHE — se souvenir d'un site, l'ouvrir, coller un lien — en un RÉFLEXE :
 * transférer. C'est un changement de nature, pas de degré.
 *
 * Trois choses la rendent réaliste ici :
 *
 * 1. Le moteur de déduplication existe déjà. La même rumeur arrive cent fois,
 *    elle n'est vérifiée qu'une : une tipline démultiplie l'intérêt de ce
 *    mécanisme au lieu de faire exploser la facture.
 * 2. Elle est asynchrone par nature, donc compatible avec un hébergement
 *    mutualisé qui interdit le temps réel.
 * 3. Elle donne enfin un usage à la transcription audio — un vocal transféré,
 *    transcrit, puis vérifié comme du texte.
 *
 * ## CE SERVICE NE PARLE PAS ENCORE À META, et le dit
 *
 * Il porte l'état de configuration et rien d'autre. L'envoi et la réception
 * réels supposent un compte Meta Business, un numéro vérifié et un rappel
 * déclaré : trois démarches administratives qu'aucun code ne remplace.
 *
 * Écrire un client d'API contre un contrat qu'on devine produirait du code
 * intestable, faux sur les détails, et qu'il faudrait réécrire le jour de
 * l'ouverture du compte. Ce qui est écrit ici est ce qui peut l'être
 * honnêtement : la porte, sa serrure, et l'état de la clé.
 */
final class TiplineWhatsApp
{
    public function __construct(private readonly Reglages $reglages) {}

    /**
     * La tipline est-elle en service ?
     *
     * Les TROIS valeurs sont exigées. Deux sur trois ne produisent pas une
     * tipline à moitié fonctionnelle : elles produisent une tipline qui reçoit
     * sans pouvoir répondre, ce qui est pire que rien — quelqu'un transfère une
     * rumeur et n'obtient jamais de verdict.
     */
    public function estActive(): bool
    {
        return $this->manquants() === [];
    }

    /**
     * Ce qu'il reste à renseigner, en clair.
     *
     * @return array<string, string> clé du réglage => libellé
     */
    public function manquants(): array
    {
        $requis = [
            'whatsapp_phone_number_id' => 'Identifiant du numéro WhatsApp',
            'whatsapp_token' => 'Jeton WhatsApp Business',
            'whatsapp_verify_token' => 'Jeton de vérification du rappel',
        ];

        return array_filter(
            $requis,
            fn (string $libelle, string $cle): bool => $this->valeur($cle) === null,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Vérifie le jeton présenté par Meta à l'ouverture du rappel.
     *
     * Comparaison à TEMPS CONSTANT. Une comparaison ordinaire s'arrête au
     * premier caractère différent, et le temps de réponse trahit alors combien
     * de caractères étaient corrects : c'est ainsi qu'on reconstitue un secret,
     * caractère par caractère.
     */
    public function jetonDeRappelValide(?string $presente): bool
    {
        $attendu = $this->valeur('whatsapp_verify_token');

        if ($attendu === null || $presente === null || $presente === '') {
            return false;
        }

        return hash_equals($attendu, $presente);
    }

    /** Le numéro déclaré, ou null. Jamais le jeton : il ne sort pas d'ici. */
    public function identifiantDuNumero(): ?string
    {
        return $this->valeur('whatsapp_phone_number_id');
    }

    private function valeur(string $cle): ?string
    {
        $valeur = $this->reglages->get($cle);

        if (! is_string($valeur)) {
            return null;
        }

        $valeur = trim($valeur);

        return $valeur === '' ? null : $valeur;
    }
}
