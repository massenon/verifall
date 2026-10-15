<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mise à jour du profil citoyen.
 *
 * La logique vit ici et non dans le composant Livewire : la même opération
 * devra être servie à l'application mobile par l'API, et une règle écrite dans
 * un composant ne se réutilise pas.
 */
final class ProfilService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Champs modifiables librement. L'ADRESSE E-MAIL N'EN FAIT PAS PARTIE :
     * elle sert d'identifiant de connexion et de canal de récupération, et sa
     * modification passe par une vérification du nouveau destinataire
     * (`changerEmail`). La laisser ici offrirait une prise de compte à quiconque
     * emprunte une session ouverte.
     *
     * @param  array{first_name: string, last_name: string, phone: ?string, city: ?string, bio: ?string}  $donnees
     */
    public function mettreAJour(User $utilisateur, array $donnees): User
    {
        $avant = $utilisateur->only(['first_name', 'last_name', 'phone', 'city', 'bio']);

        return DB::transaction(function () use ($utilisateur, $donnees, $avant): User {
            $utilisateur->fill($donnees);

            // `name` est dérivé, jamais saisi : deux sources pour une même
            // information finiraient par diverger.
            $utilisateur->name = trim($donnees['first_name'].' '.$donnees['last_name']);
            $utilisateur->save();

            $this->audit->recordDiff(
                'profile', $utilisateur, $utilisateur,
                $avant,
                $utilisateur->only(['first_name', 'last_name', 'phone', 'city', 'bio']),
            );

            return $utilisateur;
        });
    }

    /**
     * Applique un changement d'adresse DÉJÀ vérifié par code.
     *
     * L'appelant est responsable d'avoir validé l'OTP envoyé à la NOUVELLE
     * adresse : c'est la seule preuve que le demandeur en a la maîtrise.
     */
    public function changerEmail(User $utilisateur, string $nouvelle): User
    {
        $ancienne = $utilisateur->email;

        return DB::transaction(function () use ($utilisateur, $nouvelle, $ancienne): User {
            $utilisateur->forceFill([
                'email' => $nouvelle,
                // La vérification vient d'avoir lieu, sur cette adresse-là.
                'email_verified_at' => now(),
            ])->save();

            /*
             | Journalisé avec les DEUX adresses : un changement d'e-mail est le
             | premier geste d'une prise de compte, et savoir vers quoi elle a
             | basculé est ce qui permet de la défaire.
             */
            $this->audit->record(
                AuditLogService::MODIFICATION, 'profile', $utilisateur,
                cible: $utilisateur,
                avant: ['email' => $ancienne],
                apres: ['email' => $nouvelle],
                cibleLibelle: 'Changement d\'adresse e-mail',
            );

            return $utilisateur;
        });
    }
}
