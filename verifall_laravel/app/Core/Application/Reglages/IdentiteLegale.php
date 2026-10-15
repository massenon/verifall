<?php

declare(strict_types=1);

namespace App\Core\Application\Reglages;

/**
 * L'identité de l'éditeur, telle que les pages légales doivent la publier.
 *
 * ## Pourquoi cette classe existe
 *
 * Le prototype portait ces informations en clair dans la vue, sous forme
 * d'espaces réservés : « [Votre Adresse Complète] », « [Nom de votre
 * hébergeur] », « [Votre Nom Complet] ». Ces crochets étaient PUBLIÉS tels
 * quels.
 *
 * Des mentions légales à trous ne valent pas mieux que pas de mentions légales
 * du tout : c'est précisément le document qu'on ouvre pour savoir à qui l'on a
 * affaire, et sur une plateforme qui juge la véracité d'autrui, ne pas savoir
 * dire qui l'édite est une contradiction dans les termes.
 *
 * ## Ce qu'elle fait d'une valeur manquante
 *
 * Elle ne l'invente pas et ne la maquille pas. `manquant()` dit ce qui manque,
 * et les vues affichent un avertissement — **visible du seul personnel
 * connecté**. Le visiteur, lui, voit une ligne vide plutôt qu'un crochet : une
 * page incomplète est un fait, un faux remplissage est un mensonge.
 */
final readonly class IdentiteLegale
{
    /** Les réglages qui doivent être renseignés pour que les pages soient complètes. */
    private const OBLIGATOIRES = [
        'legal_entity' => 'Raison sociale',
        'legal_address' => 'Adresse du siège',
        'legal_director' => 'Directeur de la publication',
        'legal_host_name' => "Nom de l'hébergeur",
    ];

    public function __construct(private Reglages $reglages) {}

    public function entite(): ?string
    {
        return $this->valeur('legal_entity');
    }

    public function adresse(): ?string
    {
        return $this->valeur('legal_address');
    }

    public function directeur(): ?string
    {
        return $this->valeur('legal_director');
    }

    public function hebergeur(): ?string
    {
        return $this->valeur('legal_host_name');
    }

    public function adresseHebergeur(): ?string
    {
        return $this->valeur('legal_host_address');
    }

    public function contactHebergeur(): ?string
    {
        return $this->valeur('legal_host_contact');
    }

    public function contact(): ?string
    {
        return $this->valeur('contact_email');
    }

    /**
     * L'adresse par laquelle s'exercent les droits sur les données.
     *
     * À défaut d'adresse dédiée, celle de contact : mieux vaut une adresse
     * générale qui reçoit qu'une adresse dédiée qui n'existe pas. Un droit
     * d'accès qui rebondit est un droit refusé.
     */
    public function contactDonnees(): ?string
    {
        return $this->valeur('legal_dpo_email') ?? $this->contact();
    }

    /**
     * Ce qui manque encore, en clair.
     *
     * @return array<string, string> clé du réglage => libellé
     */
    public function manquant(): array
    {
        return array_filter(
            self::OBLIGATOIRES,
            fn (string $libelle, string $cle): bool => $this->valeur($cle) === null,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function estComplete(): bool
    {
        return $this->manquant() === [];
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
