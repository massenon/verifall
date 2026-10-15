<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\Concerns\GardeDemonstration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Citoyens de démonstration — Adderly, le profil du prototype, et huit autres.
 *
 * Les soldes et compteurs reprennent ceux affichés dans les maquettes : une
 * démonstration dont les chiffres diffèrent de la maquette fait douter de la
 * maquette autant que du code.
 */
final class CitizenSeeder extends Seeder
{
    use GardeDemonstration;

    /**
     * @var list<array{prenom: string, nom: string, email: string, profession: string, units: int, xp: int, streak: int}>
     */
    private const PROFILS = [
        // Le compte de référence du prototype : 34 unités, 1 280 XP, série de 7 jours.
        ['prenom' => 'Adderly', 'nom' => 'Dossou', 'email' => 'adderly@verifall.com',
            'profession' => 'Journaliste', 'units' => 34, 'xp' => 1280, 'streak' => 7],
        ['prenom' => 'Marie', 'nom' => 'Akpovi', 'email' => 'marie@verifall.com',
            'profession' => 'Enseignante', 'units' => 128, 'xp' => 3420, 'streak' => 21],
        ['prenom' => 'Ibrahim', 'nom' => 'Zakari', 'email' => 'ibrahim@verifall.com',
            'profession' => 'Étudiant', 'units' => 12, 'xp' => 640, 'streak' => 3],
        ['prenom' => 'Chantal', 'nom' => 'Adjovi', 'email' => 'chantal@verifall.com',
            'profession' => 'Sage-femme', 'units' => 58, 'xp' => 2110, 'streak' => 12],
        ['prenom' => 'Sylvain', 'nom' => 'Kponou', 'email' => 'sylvain@verifall.com',
            'profession' => 'Développeur', 'units' => 4, 'xp' => 180, 'streak' => 0],
        ['prenom' => 'Aïcha', 'nom' => 'Bio', 'email' => 'aicha@verifall.com',
            'profession' => 'Commerçante', 'units' => 76, 'xp' => 1950, 'streak' => 9],
        ['prenom' => 'Pascal', 'nom' => 'Tossou', 'email' => 'pascal@verifall.com',
            'profession' => 'Fonctionnaire', 'units' => 0, 'xp' => 90, 'streak' => 0],
        ['prenom' => 'Nadia', 'nom' => 'Sagbo', 'email' => 'nadia@verifall.com',
            'profession' => 'Documentaliste', 'units' => 210, 'xp' => 5600, 'streak' => 34],
        ['prenom' => 'Rachid', 'nom' => 'Amoussou', 'email' => 'rachid@verifall.com',
            'profession' => 'Radio locale', 'units' => 19, 'xp' => 870, 'streak' => 5],
    ];

    public function run(): void
    {
        $motDePasse = $this->motDePasseDemo();

        foreach (self::PROFILS as $index => $profil) {
            $compte = User::firstOrNew(['email' => $profil['email']]);

            $compte->forceFill([
                'uuid' => $compte->uuid ?? (string) Str::uuid(),
                'name' => $profil['prenom'].' '.$profil['nom'],
                'first_name' => $profil['prenom'],
                'last_name' => $profil['nom'],
                'profession' => $profil['profession'],
                'password' => $motDePasse,
                'email_verified_at' => now(),
                'otp_verified_at' => now(),

                /*
                 | Économie et gamification s'écrivent normalement par leurs
                 | services, transactionnellement — d'où leur absence de
                 | $fillable. Le seeder pose un ÉTAT INITIAL, pas une
                 | transaction : forceFill est ici l'outil juste, et le seul.
                 */
                'units' => $profil['units'],
                'xp' => $profil['xp'],
                'streak_days' => $profil['streak'],

                // Le premier compte a déjà fait la visite : la démonstration
                // doit pouvoir montrer les deux états.
                'tour_done_at' => $index === 0 ? now()->subDays(3) : null,
            ])->save();

            $compte->syncRoles([UserRole::Citoyen->value]);
        }
    }
}
