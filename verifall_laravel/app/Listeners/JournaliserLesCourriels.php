<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Journalise ce qui part — et ce qui ne part pas.
 *
 * ## Pourquoi un écouteur sur le transport, et pas une ligne par Mailable
 *
 * `CLAUDE.md` porte un encadré entier sur ce que la file d'attente fait
 * échouer en silence : sans `queue:work`, le code OTP d'inscription ne part
 * jamais, la page répond 200, et rien ne le signale. Le symptôme observé —
 * « l'inscription s'arrête à la deuxième étape » — a coûté un lot entier à
 * diagnostiquer, faute de pouvoir répondre à « le courriel est-il parti ? ».
 *
 * Écouter le TRANSPORT plutôt que chaque Mailable a une conséquence directe :
 * **ce qu'on ajoutera demain est journalisé sans qu'on y pense.** Sept
 * Mailables aujourd'hui, et pas un seul appel à écrire dans chacun.
 *
 * ## Le CORPS n'est jamais stocké
 *
 * `OtpCodeMail` le documente en toutes lettres : le code « ne doit apparaître
 * NULLE PART ailleurs — ni en journal, ni en base, ni dans une exception ».
 * Cet écouteur garde le destinataire, l'objet et l'état. Rien du contenu.
 *
 * ## Un échec de journalisation ne doit PAS empêcher l'envoi
 *
 * Tout est sous `try/catch`. Si la table est indisponible, le courriel part
 * quand même : un journal indisponible est un inconvénient, un courriel non
 * parti est une inscription qui échoue.
 */
final class JournaliserLesCourriels
{
    /**
     * Avant l'envoi — la ligne est écrite ici, en « échoué » par défaut.
     *
     * ## L'ordre compte, et il est contre-intuitif
     *
     * Écrire d'abord « échoué », puis basculer en « envoyé » après le
     * transport, garantit qu'un envoi qui LÈVE laisse une trace. L'inverse —
     * n'écrire qu'après succès — ne journaliserait que ce qui a marché,
     * c'est-à-dire exactement ce dont personne n'a besoin.
     */
    public function handleMessageSending(MessageSending $evenement): void
    {
        try {
            MailLog::query()->create([
                'uuid' => (string) Str::uuid(),
                'recipient' => $this->destinataire($evenement->message),
                'subject' => Str::limit((string) $evenement->message->getSubject(), 250, ''),
                'mailable' => $this->classeDuMailable($evenement->data),
                'status' => MailLog::ECHOUE,
                'error' => 'Envoi commencé, jamais confirmé.',
            ]);
        } catch (Throwable $souci) {
            Log::warning('Journal des courriels indisponible : '.$souci->getMessage());
        }
    }

    /**
     * Après l'envoi — la ligne bascule en « envoyé ».
     */
    public function handleMessageSent(MessageSent $evenement): void
    {
        try {
            $ligne = MailLog::query()
                ->where('recipient', $this->destinataire($evenement->message))
                ->where('status', MailLog::ECHOUE)
                ->latest('id')
                ->first();

            if ($ligne === null) {
                return;
            }

            $ligne->forceFill([
                'status' => MailLog::ENVOYE,
                'error' => null,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $souci) {
            Log::warning('Journal des courriels indisponible : '.$souci->getMessage());
        }
    }

    /**
     * Le premier destinataire, ou une mention explicite.
     *
     * Un courriel sans destinataire ne devrait pas exister ; s'il en arrive un,
     * la ligne doit le dire plutôt que d'être écrite vide — une chaîne vide
     * dans une colonne de recherche est un trou qu'on ne retrouve jamais.
     */
    private function destinataire(Email $message): string
    {
        $adresses = $message->getTo();

        return $adresses === [] ? 'destinataire inconnu' : $adresses[0]->getAddress();
    }

    /**
     * La classe du Mailable, si Laravel la transmet.
     *
     * Elle permet de filtrer « tous les codes OTP » sans se fier au libellé du
     * sujet — qui est surchargeable depuis l'administration depuis le lot 9, et
     * ne peut donc plus servir de clé.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function classeDuMailable(array $donnees): ?string
    {
        $mailable = $donnees['__laravel_mailable'] ?? null;

        return is_string($mailable) ? Str::limit($mailable, 185, '') : null;
    }
}
