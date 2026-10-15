<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Core\Community\Notifications\MentionDansLeForum;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Repère les personnes citées dans un message, et les prévient.
 *
 * ## Ce qu'une mention doit garantir
 *
 * Qu'on ne puisse pas s'en servir pour harceler. Trois garde-fous, et chacun
 * répond à un usage précis du dispositif par quelqu'un de mal intentionné :
 *
 * 1. **Un plafond par message.** Sans lui, un seul message citant deux cents
 *    personnes devient un envoi de masse déguisé.
 * 2. **Jamais soi-même.** Se mentionner produirait une notification à chaque
 *    publication, ce qui n'apprend rien à personne.
 * 3. **Une notification par personne et par message.** Citer quelqu'un trois
 *    fois dans le même texte ne le prévient qu'une fois.
 *
 * ## Le canal courriel respecte les préférences
 *
 * `ActiviteForum` existe déjà dans les préférences de notification et n'était
 * consommée nulle part. La brancher à moitié — envoyer sans lire la préférence
 * — produirait des courriels que personne ne peut arrêter, sur un produit qui
 * s'engage par ailleurs sur le consentement.
 */
final class Mentions
{
    /**
     * Le nombre maximal de personnes prévenues par message.
     *
     * Cinq, et non trois : une discussion à quatre participants doit pouvoir
     * les citer tous. Au-delà, ce n'est plus une conversation, c'est une
     * diffusion.
     */
    public const MAXIMUM_PAR_MESSAGE = 5;

    /**
     * Extrait les identifiants cités.
     *
     * L'expression accepte les lettres, les chiffres et le trait d'union,
     * c'est-à-dire exactement ce que produit la fabrique d'identifiants. Un
     * `@` suivi d'autre chose n'est pas une mention : sans cette limite, une
     * adresse électronique écrite dans un message en produirait une.
     *
     * @return list<string>
     */
    public function extraire(string $texte): array
    {
        $texte = strip_tags($texte);

        // Les adresses électroniques sont retirées AVANT la recherche : dans
        // « ecrivez a awa@verifall.com », le `@verifall` n'est pas une mention.
        $texte = preg_replace('/\S+@\S+\.\S+/u', ' ', $texte) ?? $texte;

        preg_match_all('/(?<![\w\/])@([a-z0-9][a-z0-9-]{1,49})/i', $texte, $trouvees);

        return array_values(array_unique(array_map(
            static fn (string $identifiant): string => mb_strtolower($identifiant),
            $trouvees[1] ?? [],
        )));
    }

    /**
     * Les comptes réellement cités, hors l'auteur.
     *
     * @return Collection<int, User>
     */
    public function destinataires(string $texte, User $auteur): Collection
    {
        $identifiants = $this->extraire($texte);

        if ($identifiants === []) {
            return collect();
        }

        return User::query()
            ->whereIn('username', $identifiants)
            // Jamais soi-même : se mentionner produirait une notification à
            // chaque publication.
            ->whereKeyNot($auteur->getKey())
            // Un compte jamais vérifié n'a pas d'adresse confirmée : lui écrire
            // reviendrait à envoyer un courriel à une adresse non prouvée.
            ->whereNotNull('otp_verified_at')
            ->limit(self::MAXIMUM_PAR_MESSAGE)
            ->get();
    }

    /**
     * Prévient les personnes citées.
     *
     * @param  Thread|Reply  $contenu  ce dans quoi la mention a été écrite
     * @return int le nombre de personnes prévenues
     */
    public function notifier(Model $contenu, User $auteur, string $texte): int
    {
        $destinataires = $this->destinataires($texte, $auteur);

        if ($destinataires->isEmpty()) {
            return 0;
        }

        $sujet = $contenu instanceof Reply ? $contenu->thread : $contenu;

        if ($sujet === null) {
            return 0;
        }

        foreach ($destinataires as $destinataire) {
            $destinataire->notify(new MentionDansLeForum(
                auteur: $auteur->name,
                titreDuSujet: $sujet->title,
                slugDuSujet: $sujet->slug,
                extrait: Str::limit(trim(strip_tags($texte)), 140),
            ));
        }

        return $destinataires->count();
    }
}
