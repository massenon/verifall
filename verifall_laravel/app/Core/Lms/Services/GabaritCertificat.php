<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Core\Application\Reglages\Reglages;
use App\Models\Certificate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tout ce dont la page d'un certificat a besoin pour se dessiner.
 *
 * ## Pourquoi un service, et pas des lectures dans la vue
 *
 * Le certificat s'affiche à DEUX endroits qui doivent produire le même
 * document : `Citizen\Certificat`, pour son titulaire, et
 * `Public\VerifierCertificat`, pour le recruteur qui contrôle un code. Deux
 * gabarits lisant chacun les réglages de leur côté finiraient par ne plus
 * afficher la même chose — et c'est précisément le document où l'écart se
 * paierait le plus cher.
 *
 * ## Ce service ne lève jamais
 *
 * Même conduite que `Parrainage` depuis le lot 3, et pour la même raison
 * apprise à ses dépens : la page publique de vérification est atteinte par un
 * lien imprimé sur un diplôme, souvent par quelqu'un qui ne connaît pas la
 * plateforme. Une table `settings` momentanément illisible — une migration en
 * cours — ne doit pas lui rendre un 500. Le document sort alors avec ses
 * valeurs par défaut, ce qui est très exactement ce qu'un repli doit faire.
 *
 * ## Le QR est engendré côté serveur
 *
 * `bacon/bacon-qr-code` était déjà installé et déjà employé pour le parrainage.
 * En SVG, et pas dans un canvas : un QR dessiné en JavaScript **ne s'imprime
 * pas** — et un certificat est fait pour être imprimé. C'est aussi ce qui le
 * garde net à toute taille ; un QR flou ne se scanne pas.
 */
final class GabaritCertificat
{
    /** La teinte de repli — celle du produit. */
    public const COULEUR_PAR_DEFAUT = '#065F46';

    public function __construct(private readonly Reglages $reglages) {}

    /**
     * Les éléments de mise en page, prêts pour la vue.
     *
     * @return array{
     *     couleur: string,
     *     couleurSecondaire: string,
     *     signatureUrl: ?string,
     *     signatureNom: string,
     *     signatureQualite: string,
     *     fondUrl: ?string,
     *     mention: string,
     * }
     */
    public function habillage(): array
    {
        $couleur = $this->couleur();

        return [
            'couleur' => $couleur,
            /*
             | La seconde teinte du dégradé se DÉDUIT de la première plutôt que
             | de se régler : deux curseurs laisseraient composer des paires
             | illisibles, et personne n'a envie d'accorder deux couleurs pour
             | obtenir une vague. La rotation se fait en CSS (`filter:
             | hue-rotate`) sur la même valeur.
             */
            'couleurSecondaire' => $couleur,
            'signatureUrl' => $this->urlFichier('cert_signature'),
            'signatureNom' => $this->texte('cert_signature_name', 'René Fadonougbo'),
            'signatureQualite' => $this->texte('cert_signature_role', 'Directeur de la publication'),
            'fondUrl' => $this->urlFichier('cert_background'),
            'mention' => $this->texte(
                'cert_mention',
                "Ce certificat atteste d'un parcours de formation. Son authenticité se vérifie à tout moment sur verifall.com.",
            ),
        ];
    }

    /**
     * Le QR de vérification d'un certificat, en SVG prêt à insérer.
     *
     * Il mène à la page PUBLIQUE de vérification, jamais à l'espace citoyen :
     * le recruteur qui le scanne n'a pas de compte, et ne doit pas en avoir
     * besoin pour contrôler un diplôme.
     */
    public function qrCode(Certificate $certificat, int $taille = 150): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($taille, 0), new SvgImageBackEnd));

        return $writer->writeString($this->adresseDeVerification($certificat));
    }

    /**
     * Le même QR, en `data:` — pour être POSÉ DANS un autre SVG.
     *
     * Le certificat est désormais un document SVG entier. Y imbriquer le SVG du
     * QR tel quel demanderait d'en retirer la déclaration XML et de lui
     * réattribuer un `viewBox` : deux manipulations de chaîne sur du balisage
     * produit par une bibliothèque, donc deux occasions de casser au prochain
     * changement de version.
     *
     * Un `<image href="data:…">` évite tout cela, reste vectoriel — c'est du SVG
     * encodé, pas une image matricielle — et s'imprime net. Il rend aussi le
     * document AUTONOME : enregistré ou envoyé par courriel, il porte son QR
     * avec lui, là où une URL exigerait que le serveur réponde.
     */
    public function qrCodeDataUri(Certificate $certificat, int $taille = 150): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->qrCode($certificat, $taille));
    }

    /**
     * L'adresse que porte le QR.
     *
     * La page PUBLIQUE, jamais l'espace citoyen : le recruteur qui scanne n'a
     * pas de compte, et ne doit pas en avoir besoin pour contrôler un diplôme.
     */
    public function adresseDeVerification(Certificate $certificat): string
    {
        return route('public.verify-certificate', ['code' => $certificat->code]);
    }

    /** La couleur du décor, validée avant d'entrer dans une feuille de style. */
    private function couleur(): string
    {
        $valeur = $this->texte('cert_accent_color', self::COULEUR_PAR_DEFAUT);

        /*
         | Un second contrôle, après celui du formulaire. Cette valeur est
         | interpolée dans un attribut `style` : une chaîne libre y ouvrirait
         | une injection CSS, et le formulaire n'est pas le seul chemin
         | d'écriture — un `Reglages::set()` depuis une commande ou un seeder ne
         | passe par aucune validation.
         */
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $valeur) === 1
            ? $valeur
            : self::COULEUR_PAR_DEFAUT;
    }

    private function texte(string $cle, string $repli): string
    {
        try {
            $valeur = trim((string) $this->reglages->get($cle, $repli));
        } catch (Throwable) {
            return $repli;
        }

        return $valeur !== '' ? $valeur : $repli;
    }

    /**
     * L'URL publique d'un réglage-fichier, ou `null`.
     *
     * Les réglages-fichiers sont déposés sur le disque `public` : ce sont des
     * éléments de charte, destinés à s'afficher. Une route signée n'aurait pas
     * de sens sur une image imprimée dans un diplôme que son titulaire partage.
     */
    private function urlFichier(string $cle): ?string
    {
        try {
            $chemin = (string) $this->reglages->get($cle, '');
        } catch (Throwable) {
            return null;
        }

        if ($chemin === '') {
            return null;
        }

        return Storage::disk('public')->url($chemin);
    }
}
