<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Support;

/**
 * Le catalogue FERMÉ des échecs de l'API.
 *
 * Un enum et non des chaînes libres : le client branche son comportement sur
 * ces valeurs — réessayer, redemander un jeton, proposer une recharge — et une
 * faute de frappe dans un contrôleur rendrait le branchement muet sans qu'aucun
 * test ne s'en aperçoive. Ajouter un cas ici est une évolution compatible ;
 * en renommer un est une RUPTURE, donc une v2.
 *
 * Le statut HTTP vit à côté du code, dans le même `match`, pour la même raison
 * qu'ailleurs dans ce projet : deux listes tenues à la main finissent par
 * diverger, et c'est celle qu'on regarde le moins qui ment.
 */
enum CodeErreur: string
{
    case NonAuthentifie = 'non_authentifie';
    case CompteInactif = 'compte_inactif';
    case CompteEnSuppression = 'compte_en_suppression';
    case DroitManquant = 'droit_manquant';
    case Introuvable = 'introuvable';
    case MethodeRefusee = 'methode_refusee';
    case Validation = 'validation';
    case Conflit = 'conflit';
    case SoldeInsuffisant = 'solde_insuffisant';
    case DebitDepasse = 'debit_depasse';
    case ClientObsolete = 'client_obsolete';
    case ActionImpossible = 'action_impossible';
    case ErreurServeur = 'erreur_serveur';

    public function statutHttp(): int
    {
        return match ($this) {
            self::NonAuthentifie => 401,
            // 402 « Payment Required » : le seul code HTTP qui dise exactement
            // « il manque de quoi payer », et que le client mobile traduit en
            // proposition de recharge plutôt qu'en message d'échec.
            self::SoldeInsuffisant => 402,
            self::CompteInactif, self::CompteEnSuppression, self::DroitManquant => 403,
            self::Introuvable => 404,
            self::MethodeRefusee => 405,
            self::Conflit => 409,
            self::ActionImpossible, self::Validation => 422,
            // 426 « Upgrade Required » : la version installée ne sait plus
            // parler à ce serveur. Un 400 ferait afficher « erreur réseau ».
            self::ClientObsolete => 426,
            self::DebitDepasse => 429,
            self::ErreurServeur => 500,
        };
    }

    /**
     * Message par défaut, rédigé pour être affiché TEL QUEL.
     *
     * Le client mobile ne traduit pas : il montre ce qu'on lui donne. Un
     * message technique ici deviendrait un message technique à l'écran.
     */
    public function message(): string
    {
        return match ($this) {
            self::NonAuthentifie => 'Votre session a expiré. Reconnectez-vous.',
            self::CompteInactif => "Ce compte n'a pas encore été activé.",
            self::CompteEnSuppression => 'Ce compte est en cours de suppression.',
            self::DroitManquant => "Vous n'avez pas accès à cette ressource.",
            self::Introuvable => 'Cette ressource est introuvable.',
            self::MethodeRefusee => "Cette action n'est pas disponible ici.",
            self::Validation => 'Certaines informations sont incorrectes.',
            self::Conflit => 'Cette action a déjà été effectuée.',
            self::SoldeInsuffisant => "Vous n'avez pas assez d'unités.",
            self::DebitDepasse => 'Trop de requêtes. Patientez un instant.',
            self::ClientObsolete => "Cette version de l'application n'est plus prise en charge. Mettez-la à jour.",
            self::ActionImpossible => "Cette action n'est pas possible en l'état.",
            self::ErreurServeur => "Une anomalie s'est produite de notre côté. Réessayez dans un instant.",
        };
    }
}
