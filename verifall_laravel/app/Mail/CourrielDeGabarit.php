<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Core\Marketing\Emails\RenduDeGabarit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\SerializesModels;

/**
 * Le mailable générique : un courriel entièrement décrit par son gabarit.
 *
 * ## Pourquoi UN mailable et pas vingt
 *
 * Les six mailables historiques portent chacun une structure propre — une
 * boucle sur des articles, un tableau de dates, un encadré. Ils la gardent.
 *
 * Mais la plupart des courriels qui restaient à écrire ont exactement la même
 * forme : un titre, deux paragraphes, un bouton. Leur donner une classe chacun
 * aurait produit vingt fichiers identiques à un `view()` près — et surtout
 * vingt endroits où modifier le texte, ce que l'écran d'administration est
 * précisément censé éviter.
 *
 * ## `ShouldQueue`, comme `OtpCodeMail`
 *
 * Ces courriels partent depuis une requête — une leçon terminée, un badge
 * décroché, un paiement encaissé. Attendre un SMTP distant ferait tourner la
 * roue du navigateur pendant que quelqu'un vient de réussir quelque chose.
 *
 * ## Ce qu'il refuse
 *
 * Une clé hors catalogue lève, immédiatement. Un courriel dont la clé n'existe
 * pas partirait sans objet et sans corps : mieux vaut une exception au moment
 * de l'envoi qu'un message vide dans trois mille boîtes.
 */
final class CourrielDeGabarit extends Mailable implements ShouldQueue
{
    use SerializesModels;

    /**
     * @param  array<string, string|int|null>  $variables
     */
    public function __construct(
        public readonly string $cle,
        public readonly array $variables = [],
        public readonly ?string $lienDuBouton = null,
    ) {
        CatalogueDesCourriels::evenement($cle);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: app(RenduDeGabarit::class)->sujet(
                $this->cle,
                CatalogueDesCourriels::evenement($this->cle)['libelle'],
                $this->variables,
            ),
        );
    }

    public function content(): Content
    {
        $rendu = app(RenduDeGabarit::class);

        return new Content(
            markdown: 'emails.gabarit',
            with: [
                'titre' => $rendu->titre(
                    $this->cle,
                    CatalogueDesCourriels::evenement($this->cle)['libelle'],
                    $this->variables,
                ),
                /*
                 | Le Markdown est converti EN HTML ici, et purifié dans la vue.
                 |
                 | L'ordre compte : purifier le Markdown brut encoderait le `>`
                 | des citations en `&gt;`, et la citation deviendrait une ligne
                 | de texte commençant par une entité. On convertit d'abord, on
                 | nettoie le HTML ensuite.
                 */
                'corps' => Markdown::parse($rendu->corps($this->cle, $this->variables) ?? ''),
                'libelleDuBouton' => $rendu->libelleDuBouton($this->cle, $this->variables),
                'lienDuBouton' => $this->lienDuBouton,
            ],
        );
    }
}
