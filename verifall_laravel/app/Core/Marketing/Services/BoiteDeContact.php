<?php

declare(strict_types=1);

namespace App\Core\Marketing\Services;

use App\Core\Application\Contracts\DepotMessagesContact;
use App\Core\Application\Reglages\Reglages;
use App\Mail\MessageDeContactRecu;
use App\Models\ContactMessage;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Le formulaire de contact, côté métier.
 *
 * ## Ce qu'il remplace
 *
 * Le prototype portait un formulaire `data-vf-form` : le script appelait
 * `preventDefault()`, attendait 900 ms et affichait « Message envoyé ! Notre
 * équipe vous répondra très vite. » Aucune destination, aucun enregistrement.
 * **Quelqu'un écrivait, était remercié, et son message n'arrivait nulle part.**
 *
 * C'est plus grave ici qu'ailleurs : sur une plateforme de fact-checking, celui
 * qui écrit signale souvent une intox. Le formulaire perdu, c'est la matière
 * première du produit qui se perd.
 *
 * ## L'ordre des opérations, qui n'est pas indifférent
 *
 * On ÉCRIT d'abord, on prévient ensuite. Un serveur SMTP qui refuse ne doit
 * rien coûter : le message est déjà en base et l'écran d'administration le
 * montre. L'ordre inverse — envoyer puis écrire — perd le message à la
 * première panne de courriel, en silence.
 */
final class BoiteDeContact
{
    /**
     * Délai minimal entre l'affichage du formulaire et son envoi.
     *
     * Un robot poste en quelques centaines de millisecondes ; un humain met au
     * moins quelques secondes à écrire un message. Trois secondes ne gênent
     * personne — c'est moins que le temps de saisir une adresse — et écartent
     * les soumissions automatiques les plus grossières.
     */
    public const SECONDES_MINIMALES = 3;

    /** Fenêtre du second rideau anti-rafale, en minutes. */
    private const FENETRE_MINUTES = 10;

    /** Nombre de messages tolérés par empreinte d'IP sur cette fenêtre. */
    private const MAXIMUM_PAR_FENETRE = 5;

    public function __construct(
        private readonly DepotMessagesContact $depot,
        private readonly IdentityHasher $hasher,
        private readonly Reglages $reglages,
    ) {}

    /**
     * Enregistre le message et prévient l'équipe.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function recevoir(array $donnees, ?string $ip = null): ContactMessage
    {
        $empreinte = $ip !== null && $ip !== '' ? $this->hasher->hash($ip) : null;

        $message = $this->depot->enregistrer([...$donnees, 'ip_hash' => $empreinte]);

        $this->prevenirEquipe($message);

        return $message;
    }

    /**
     * Le piège à robots : un champ que personne ne voit et que seul un
     * automate remplit.
     *
     * Il s'appelle `website` et non `honeypot` — un robot qui reconnaît le
     * second l'évite. Il est masqué par le style, jamais par `type="hidden"` :
     * les robots ignorent les champs cachés, mais remplissent volontiers ce
     * qui ressemble à un champ ordinaire.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function ressembleAUnRobot(array $donnees): bool
    {
        $piege = trim((string) ($donnees['website'] ?? ''));

        if ($piege !== '') {
            return true;
        }

        /*
         | Le second signal : la vitesse. `rendu_a` porte l'horodatage signé du
         | moment où la page a été servie. Sa signature est vérifiée en amont —
         | ici on ne juge que le délai.
         */
        $rendu = $donnees['rendu_a'] ?? null;

        if (! is_numeric($rendu)) {
            // Absent ou illisible : on ne bloque pas. Un navigateur ancien, un
            // cache agressif ou un formulaire rouvert d'un onglet dormant
            // suffisent à le perdre — refuser ici écarterait des humains.
            return false;
        }

        return (time() - (int) $rendu) < self::SECONDES_MINIMALES;
    }

    /**
     * Le second rideau, derrière la limite de cadence de la route.
     *
     * `throttle` compte les REQUÊTES et s'appuie en partie sur la session ;
     * celui-ci compte les messages RÉELLEMENT écrits par empreinte d'IP, donc
     * il survit à un vidage de cookies. Les deux ensemble coûtent une requête
     * indexée et rendent la rafale peu rentable.
     */
    public function tropDeMessagesRecents(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        return $this->depot->envoisRecents($this->hasher->hash($ip), self::FENETRE_MINUTES)
            >= self::MAXIMUM_PAR_FENETRE;
    }

    /**
     * Envoie la notification — sans jamais faire échouer l'enregistrement.
     *
     * Le `try` enveloppe TOUT, y compris la lecture du réglage : une adresse de
     * contact mal formée ne doit pas produire une page 500 devant quelqu'un qui
     * vient d'écrire. Son message est déjà sauvé ; c'est l'essentiel.
     */
    private function prevenirEquipe(ContactMessage $message): void
    {
        try {
            $destinataire = $this->reglages->get('contact_email');

            if (! is_string($destinataire) || $destinataire === '') {
                /*
                 | Aucune adresse configurée. On le JOURNALISE plutôt que de
                 | retomber sur une adresse en dur : une valeur par défaut
                 | inventée enverrait les messages dans une boîte que personne
                 | ne relève, ce qui ressemble à un fonctionnement normal.
                 */
                Log::warning('Message de contact reçu, mais `contact_email` n\'est pas renseigné.', [
                    'contact_message_uuid' => $message->uuid,
                ]);

                return;
            }

            Mail::to($destinataire)->send(new MessageDeContactRecu($message));
        } catch (\Throwable $e) {
            Log::error('Notification de message de contact non envoyée.', [
                'contact_message_uuid' => $message->uuid,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
