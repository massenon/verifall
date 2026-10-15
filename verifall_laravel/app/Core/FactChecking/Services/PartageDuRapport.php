<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Enums\VerdictFactCheck;

/**
 * Ce qu'on envoie quand on partage un rapport.
 *
 * ## Pourquoi le verdict passe AVANT l'affirmation
 *
 * C'est le « sandwich de vérité », et ce n'est pas une préférence de style.
 * Dans une conversation WhatsApp, la plupart des gens lisent la première ligne
 * et rien d'autre. Un message qui commence par « Le Président a été
 * destitué… » propage la rumeur auprès de tous ceux-là, y compris quand la
 * suite la dément : répéter un mensonge pour le corriger l'ancre.
 *
 * On énonce donc ce qui est établi d'abord, l'affirmation ensuite, entre
 * guillemets, et le lien en dernier.
 *
 * ## Et pourquoi un service plutôt qu'un `@php` dans le gabarit
 *
 * Ce texte part chez des tiers, sur quatre canaux, depuis deux écrans. Recopié
 * dans les vues, il aurait divergé au premier ajustement — et c'est la seule
 * phrase du produit que des gens liront sans jamais ouvrir la page.
 */
final class PartageDuRapport
{
    /** L'objet d'un courriel, et le titre d'un partage. */
    public function objet(): string
    {
        return (string) __('cit_rapport.objet_de_partage');
    }

    /**
     * Le corps du message : verdict, affirmation, lien.
     *
     * `verdict` est NULLABLE dès qu'un rapport est MIXTE (contrat V-8) :
     * aucun mot unique ne résume alors ce qui est établi, et c'est
     * exactement le genre de message qui ancrerait une rumeur si l'on y
     * laissait planter l'appel plutôt que d'annoncer la nuance.
     */
    public function message(?VerdictFactCheck $verdict, string $affirmation, string $lien): string
    {
        return sprintf(
            "%s : %s\n« %s »\n\n%s",
            mb_strtoupper((string) __('cit_rapport.verdict')),
            mb_strtoupper($verdict?->libelleRapport() ?? (string) __('cit_rapport.verdict_mixte_partage')),
            trim($affirmation),
            $lien,
        );
    }

    /**
     * Les adresses des quatre canaux, dans l'ordre d'usage au Bénin.
     *
     * WhatsApp en premier, et de loin : c'est là que l'infox circule, donc
     * c'est là que le démenti doit pouvoir repartir. X, Facebook et le
     * courriel suivent.
     *
     * Chaque paramètre est encodé : une affirmation contient volontiers des
     * `&`, des `#` et des sauts de ligne, qui tronqueraient l'adresse.
     *
     * @return array<string, string>
     */
    public function adresses(?VerdictFactCheck $verdict, string $affirmation, string $lien): array
    {
        $message = $this->message($verdict, $affirmation, $lien);
        $libelle = $verdict?->libelleRapport() ?? (string) __('cit_rapport.verdict_mixte_partage');

        return [
            'whatsapp' => 'https://api.whatsapp.com/send?text='.rawurlencode($message),

            /*
             | X limite le texte : on y envoie le verdict et l'affirmation, pas
             | le message entier, et le lien part dans son propre paramètre —
             | c'est lui qui déclenche l'aperçu.
             */
            'x' => 'https://twitter.com/intent/tweet?text='
                .rawurlencode(mb_strtoupper($libelle).' : '.trim($affirmation))
                .'&url='.rawurlencode($lien),

            /*
             | Facebook n'accepte plus de texte pré-rempli depuis 2017 : le
             | paramètre `quote` est ignoré. Seule l'adresse compte, et c'est
             | l'aperçu Open Graph de la page qui portera le verdict — d'où le
             | soin apporté au titre dans `Referencement::pourRapportPublic()`.
             */
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($lien),

            'courriel' => 'mailto:?subject='.rawurlencode($this->objet())
                .'&body='.rawurlencode($message),
        ];
    }
}
