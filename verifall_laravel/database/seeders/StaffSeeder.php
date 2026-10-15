<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\Concerns\GardeDemonstration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Équipe du back-office — les personnes affichées dans le prototype.
 *
 * Idempotent par e-mail : rejouer le seeder ne duplique pas les comptes et ne
 * fait pas repartir les rôles de zéro.
 */
final class StaffSeeder extends Seeder
{
    use GardeDemonstration;

    /**
     * @var list<array{prenom: string, nom: string, email: string, profession: string, role: UserRole}>
     */
    private const EQUIPE = [
        [
            'prenom' => 'René', 'nom' => 'Fadonougbo',
            'email' => 'rene@verifall.com',
            'profession' => 'Directeur de la publication',
            'role' => UserRole::Admin,
        ],
        [
            'prenom' => 'Koffi', 'nom' => 'Agbodjan',
            'email' => 'koffi@verifall.com',
            'profession' => 'Mentor — Académie',
            'role' => UserRole::Mentor,
        ],
        [
            'prenom' => 'Sandrine', 'nom' => 'Houngbo',
            'email' => 'sandrine@verifall.com',
            'profession' => 'Modératrice — Forum et signalements',
            'role' => UserRole::Moderateur,
        ],
    ];

    public function run(): void
    {
        $motDePasse = $this->motDePasseDemo();

        foreach (self::EQUIPE as $membre) {
            $compte = User::firstOrNew(['email' => $membre['email']]);

            $compte->forceFill([
                // L'uuid n'est posé qu'à la création : c'est l'identifiant
                // public, le régénérer casserait toute URL déjà partagée.
                'uuid' => $compte->uuid ?? (string) Str::uuid(),
                'name' => $membre['prenom'].' '.$membre['nom'],
                'first_name' => $membre['prenom'],
                'last_name' => $membre['nom'],
                'profession' => $membre['profession'],
                'password' => $motDePasse,
                'email_verified_at' => now(),
                // Compte de démonstration : activé d'emblée, sinon la première
                // connexion réclamerait un code envoyé à une adresse fictive.
                'otp_verified_at' => now(),
            ])->save();

            /*
             | La 2FA n'est PAS pré-activée, et c'est volontaire : le back-office
             | l'exige, donc la première connexion staff passe par l'écran
             | d'enrôlement. Poser un secret ici priverait la démonstration du
             | parcours qu'elle est censée montrer — et obligerait à stocker un
             | secret TOTP dans le dépôt.
             */

            $compte->syncRoles([$membre['role']->value]);
        }
    }
}
