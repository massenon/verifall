<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

/**
 * Qui délivre et révoque les certificats.
 *
 * ## Trois verbes, et aucun n'est `delete`
 *
 * `certificate => ['view', 'issue', 'revoke']`. La matrice ne déclare pas de
 * suppression, et c'est le point de tout le dispositif : **un certificat ne
 * s'efface pas**.
 *
 * Chaque certificat porte un code que n'importe qui peut contrôler sur la page
 * publique. Effacer une ligne rendrait ce contrôle négatif — le porteur d'un
 * certificat authentique passerait pour un fraudeur, sans recours et sans
 * trace de ce qui s'est passé.
 *
 * La révocation est la réponse : elle est motivée, datée, et VISIBLE sur la
 * page de contrôle. Les CGU §7 s'y engagent par écrit.
 *
 * Le mentor porte `view` et `issue` mais jamais `revoke` : délivrer un
 * certificat à un élève qui a fini son parcours relève de la pédagogie ;
 * annuler un certificat déjà remis relève d'une décision institutionnelle.
 */
final class CertificatePolicy
{
    public function viewAny(User $utilisateur): bool
    {
        return $utilisateur->can('certificate.view');
    }

    public function view(User $utilisateur, Certificate $certificat): bool
    {
        return $utilisateur->can('certificate.view');
    }

    public function issue(User $utilisateur): bool
    {
        return $utilisateur->can('certificate.issue');
    }

    public function revoke(User $utilisateur, Certificate $certificat): bool
    {
        return $utilisateur->can('certificate.revoke');
    }
}
