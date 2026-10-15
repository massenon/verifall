<?php

declare(strict_types=1);

namespace App\Core\Marketing\Emails;

/**
 * Tout ce que la plateforme envoie par courriel, déclaré en un seul endroit.
 *
 * ## Une liste FERMÉE, et pourquoi
 *
 * Pas de création libre de gabarit. Une ligne dont la clé ne correspond à aucun
 * envoi est un réglage que personne ne lit, sur un écran qui laisse croire
 * qu'on ajoute un courriel en ajoutant une ligne. Inversement, un envoi absent
 * de cette liste devient le seul dont personne ne peut ajuster le texte, sans
 * qu'aucun écran ne l'explique.
 *
 * **Le catalogue et le code doivent donc se répondre exactement**, et un test
 * le vérifie dans les deux sens.
 *
 * ## `porte` : ce qui est câblé, et ce qui ne l'est pas encore
 *
 * Ce drapeau dit si un envoi part RÉELLEMENT aujourd'hui. Il n'est pas un
 * aveu d'inachèvement caché dans un coin : l'écran d'administration l'affiche,
 * parce qu'un gabarit qu'on peut rédiger sans que rien ne l'expédie est
 * exactement le genre de piège que ce chantier a corrigé quatre fois.
 *
 * ## `parDefautActif` : les bavards sont livrés ÉTEINTS
 *
 * Plusieurs événements de cette liste existaient jusqu'ici comme notifications
 * INTERNES seulement. Les doubler d'un courriel est un choix de produit, pas
 * une évidence technique : recevoir un message à chaque réaction sur un billet
 * de forum est ce qui fait cliquer sur « se désabonner de tout ». Ceux-là sont
 * déclarés, câblés, et éteints — l'administration les allume si elle le veut.
 *
 * Les courriels TRANSACTIONNELS, eux, ne s'éteignent pas : un code à usage
 * unique qu'un réglage peut couper est un compte qu'on ne peut plus créer. Ils
 * portent `verrouille`.
 */
final class CatalogueDesCourriels
{
    /**
     * @var array<string, array{
     *     libelle: string,
     *     declencheur: string,
     *     variables: list<string>,
     *     porte: bool,
     *     parDefautActif: bool,
     *     verrouille: bool,
     * }>
     */
    public const EVENEMENTS = [

        // ══════════════════════════════════ compte et sécurité (verrouillés)

        'otp' => [
            'libelle' => 'Code à usage unique',
            'declencheur' => 'Inscription, réinitialisation du mot de passe, suppression de compte, changement d\'adresse',
            'variables' => ['prenom', 'code', 'minutes'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        'bienvenue_compte' => [
            'libelle' => 'Bienvenue — compte créé',
            'declencheur' => 'Dernière étape de l\'inscription franchie, compte actif',
            'variables' => ['prenom', 'unites_offertes', 'lien_tableau_de_bord'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        'mot_de_passe_change' => [
            'libelle' => 'Mot de passe modifié',
            'declencheur' => 'Réinitialisation menée à son terme',
            // Ce courriel ne se coupe pas : il est le seul signal qu'a quelqu'un
            // dont le compte vient d'être pris. Le supprimer transformerait un
            // vol de compte en événement silencieux.
            'variables' => ['prenom', 'quand'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        'export_pret' => [
            'libelle' => 'Export RGPD prêt au téléchargement',
            'declencheur' => 'Fin de la préparation de l\'archive demandée par la personne',
            'variables' => ['prenom', 'lien', 'expire_le'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        'suppression_programmee' => [
            'libelle' => 'Suppression de compte programmée',
            'declencheur' => 'Demande de suppression confirmée, avant le délai de rétractation',
            'variables' => ['prenom', 'date_effacement', 'lien_annulation'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        'compte_suspendu' => [
            'libelle' => 'Compte suspendu',
            'declencheur' => 'Suspension prononcée depuis l’écran Utilisateurs',
            // Verrouillé : une sanction dont on n'informe pas la personne n'est
            // pas une sanction, c'est une panne de son point de vue.
            'variables' => ['prenom', 'motif', 'lien_contact'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        'compte_retabli' => [
            'libelle' => 'Compte rétabli',
            'declencheur' => 'Levée d\'une suspension (écran à écrire)',
            'variables' => ['prenom', 'lien_tableau_de_bord'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        // ═══════════════════════════════════════════════ identité vérifiée

        'kyc_approuve' => [
            'libelle' => 'Vérification d\'identité approuvée',
            'declencheur' => 'Décision favorable sur un dossier KYC',
            'variables' => ['prenom', 'lien_profil'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        'kyc_refuse' => [
            'libelle' => 'Vérification d\'identité refusée',
            'declencheur' => 'Décision défavorable sur un dossier KYC',
            // Le motif est OBLIGATOIRE dans ce message : un refus sans raison
            // n'est pas contestable, et se transforme en message au support.
            'variables' => ['prenom', 'motif', 'lien_depot'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        // ═════════════════════════════════════════════════ fact-checking

        'analyse_terminee' => [
            'libelle' => 'Votre vérification est terminée',
            'declencheur' => 'Fin de l\'analyse d\'un contenu soumis par un citoyen',
            'variables' => ['prenom', 'verdict', 'sujet', 'lien_rapport'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        'alerte_intox' => [
            'libelle' => 'Alerte — intox en circulation',
            'declencheur' => 'Publication d\'un démenti marqué urgent par l\'équipe',
            'variables' => ['prenom', 'titre', 'resume', 'lien_fact_check'],
            'porte' => true,
            'parDefautActif' => false,
            'verrouille' => false,
        ],

        'verification_assignee' => [
            'libelle' => 'Prise en charge d\'une vérification (personnel)',
            'declencheur' => 'Assignation d\'une vérification à un mentor ou à un fact-checkeur',
            'variables' => ['prenom', 'sujet', 'lien_verification'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        'contre_expertise_tranchee' => [
            'libelle' => 'Réponse à votre demande de contre-expertise',
            'declencheur' => 'Décision rendue sur une contre-expertise demandée par un citoyen',
            'variables' => ['prenom', 'sujet', 'decision', 'lien_rapport'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        // ═════════════════════════════════════════════════════ Académie

        'cours_termine' => [
            'libelle' => 'Cours terminé — félicitations',
            'declencheur' => 'Avancement d\'un cours porté à 100 %',
            'variables' => ['prenom', 'cours', 'xp', 'lien_certificat'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        'relance_de_cours' => [
            'libelle' => 'Relance d\'un cours commencé',
            // Envoyée À LA MAIN depuis les statistiques d'un cours, jamais par
            // un planificateur : une relance automatique devient vite le
            // message que tout le monde filtre.
            'declencheur' => 'Clic sur « Relancer » dans les statistiques d\'un cours',
            'variables' => ['prenom', 'cours', 'progression', 'lien_cours'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        'nouveau_cours' => [
            'libelle' => 'Un nouveau cours est disponible',
            'declencheur' => 'Publication d\'un cours au catalogue',
            'variables' => ['prenom', 'cours', 'niveau', 'lien_cours'],
            'porte' => true,
            'parDefautActif' => false,
            'verrouille' => false,
        ],

        // ══════════════════════════════════════════ récompenses et économie

        'badge_debloque' => [
            'libelle' => 'Badge débloqué',
            'declencheur' => 'Attribution d\'un badge de progression',
            'variables' => ['prenom', 'badge', 'xp', 'lien_recompenses'],
            'porte' => true,
            // Éteint : un courriel par badge est le premier pas vers le filtre
            // automatique, qui emportera ensuite les messages qui comptent.
            'parDefautActif' => false,
            'verrouille' => false,
        ],

        'unites_creditees' => [
            'libelle' => 'Unités créditées',
            'declencheur' => 'Recharge, dotation mensuelle, parrainage ou ajustement',
            'variables' => ['prenom', 'unites', 'motif', 'solde', 'lien_unites'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        // ═══════════════════════════════════════════════════ communauté

        'mention_forum' => [
            'libelle' => 'Vous êtes cité dans le forum',
            'declencheur' => 'Mention @ dans un message du forum',
            'variables' => ['prenom', 'auteur', 'sujet', 'lien_message'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        'reaction_recue' => [
            'libelle' => 'Réaction sur votre message',
            'declencheur' => 'Réaction déposée sur un message de forum',
            'variables' => ['prenom', 'auteur', 'sujet', 'lien_message'],
            'porte' => true,
            // Éteint, et c'est le cas d'école : un courriel par réaction est ce
            // qui fait cliquer sur « se désabonner de tout ».
            'parDefautActif' => false,
            'verrouille' => false,
        ],

        // ═════════════════════════════════════════════════ infolettre

        'confirmation_abonnement' => [
            'libelle' => 'Confirmation d\'abonnement à l\'infolettre',
            'declencheur' => 'Saisie d\'une adresse dans le formulaire d\'abonnement',
            // Verrouillé : sans ce message, aucune adresse ne peut se confirmer,
            // et la liste cesse purement et simplement de grandir.
            'variables' => ['prenom', 'lien_confirmation'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        'recapitulatif_hebdomadaire' => [
            'libelle' => 'Infolettre hebdomadaire',
            'declencheur' => 'Tâche planifiée du lundi, sur les fact-checks de la semaine',
            'variables' => ['prenom', 'periode', 'lien_fact_checks'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => false,
        ],

        'diffusion_infolettre' => [
            'libelle' => 'Diffusion manuelle de l\'infolettre',
            'declencheur' => 'Onglet « Diffuser » de l\'écran Infolettre',
            // Le corps est écrit à l'envoi : ce gabarit ne sert que d'objet de
            // repli, d'où l'absence de marqueur de corps.
            'variables' => ['prenom'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        // ═══════════════════════════════════════════════════ maintenance

        'maintenance_terminee' => [
            'libelle' => 'Le service est rétabli',
            'declencheur' => 'Réouverture du site depuis l’écran Maintenance',
            /*
             | Verrouillé, et ce n'est pas un excès de zèle : les gens qui le
             | reçoivent ont DEMANDÉ ce message, une fois, sur une page qui leur
             | promettait de les prévenir. L'éteindre ferait de cette promesse
             | un mensonge — et laisserait des adresses en attente d'un courriel
             | qui ne partirait jamais.
             */
            'variables' => ['prenom', 'duree', 'lien_accueil'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],

        // ══════════════════════════════════════════════════════ contact

        'message_contact' => [
            'libelle' => 'Accusé de réception d\'un message de contact',
            'declencheur' => 'Envoi du formulaire de contact public',
            'variables' => ['prenom', 'sujet'],
            'porte' => true,
            'parDefautActif' => true,
            'verrouille' => true,
        ],
    ];

    /** @return list<string> */
    public static function cles(): array
    {
        return array_keys(self::EVENEMENTS);
    }

    public static function existe(string $cle): bool
    {
        return array_key_exists($cle, self::EVENEMENTS);
    }

    /**
     * @return array{libelle: string, declencheur: string, variables: list<string>, porte: bool, parDefautActif: bool, verrouille: bool}
     */
    public static function evenement(string $cle): array
    {
        return self::EVENEMENTS[$cle] ?? throw new \InvalidArgumentException(
            sprintf('Courriel inconnu : « %s ». La liste est fermée, voir CatalogueDesCourriels.', $cle),
        );
    }

    /** @return list<string> */
    public static function variables(string $cle): array
    {
        return self::evenement($cle)['variables'];
    }

    public static function estVerrouille(string $cle): bool
    {
        return self::evenement($cle)['verrouille'];
    }

    /**
     * Les libellés seuls — la forme qu'attendait l'ancien `GabaritsDEmail::CLES`.
     *
     * @return array<string, string>
     */
    public static function libelles(): array
    {
        return array_map(static fn (array $e): string => $e['libelle'], self::EVENEMENTS);
    }
}
