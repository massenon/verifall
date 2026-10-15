<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\Concerns\GardeDemonstration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Les identifiants de connexion, un par rôle — documentés et affichés.
 *
 * ## Ce qu'il fait, et ce qu'il ne fait PAS
 *
 * Il ne crée aucun compte. **C'est le point.**
 *
 * Ma première version en créait quatre — `admin@`, `mentor@`, `moderateur@`,
 * `citoyen@` — avec de belles adresses prévisibles. Sept tests sont tombés, et
 * ils avaient raison : l'équipe du prototype couvre déjà chaque rôle, et
 * doubler chaque rôle rendait faux tout ce qui compte les comptes. Une
 * démonstration avec deux administrateurs ne reproduit plus le prototype.
 *
 * Le besoin réel n'était pas « d'autres comptes » : c'était **savoir avec quoi
 * se connecter**. Ce seeder répond à cette question-là. Il prend les comptes
 * qui existent, leur pose le mot de passe de démonstration, s'assure qu'ils
 * sont utilisables, et les imprime.
 *
 * ## Pourquoi il les imprime au lieu d'un fichier de documentation
 *
 * Parce qu'une information d'accès qui vit uniquement dans un document finit
 * toujours par diverger du code. Ici, la liste vient de la base : si un compte
 * change d'adresse, la sortie change avec lui.
 *
 * ## Ce qu'il n'imprime pas
 *
 * Le mot de passe. Il vient de `DEMO_PASSWORD`, il n'a aucune valeur de repli
 * dans le dépôt, et l'écrire en console le recopierait dans l'historique du
 * terminal de tout le monde.
 */
final class ComptesDeDemonstrationSeeder extends Seeder
{
    use GardeDemonstration;

    /**
     * Un compte de référence par rôle, choisi dans l'équipe du prototype.
     *
     * Ces adresses ne sont pas inventées : ce sont celles que `StaffSeeder` et
     * `CitizenSeeder` posent déjà. On les nomme ici pour qu'il existe **une**
     * réponse à « avec quoi je me connecte pour voir tel écran ? ».
     *
     * @var array<string, string> adresse => ce qu'on voit avec
     */
    private const REFERENCES = [
        'rene@verifall.com' => 'Back-office complet',
        'koffi@verifall.com' => 'Académie, côté mentor',
        'sandrine@verifall.com' => 'Modération du forum',
        'adderly@verifall.com' => 'Espace citoyen',
    ];

    public function run(): void
    {
        // Lève si production, interrupteur absent, ou mot de passe vide.
        $motDePasse = $this->motDePasseDemo();

        $lignes = [];

        foreach (self::REFERENCES as $adresse => $usage) {
            $compte = User::where('email', $adresse)->first();

            if ($compte === null) {
                /*
                 | Le compte n'existe pas encore : ce seeder tourne avant celui
                 | qui le crée, ou l'équipe a changé. On ne le fabrique PAS —
                 | ce serait recréer le doublon qu'on vient d'éviter. On le dit.
                 */
                $lignes[] = sprintf('  %-24s ABSENT — %s', $adresse, $usage);

                continue;
            }

            $compte->forceFill([
                'password' => Hash::make($motDePasse),
                /*
                 | Adresse marquée vérifiée : sans cela, chaque connexion
                 | réclame un code envoyé par courriel — qui n'est envoyé nulle
                 | part en local. Le compte serait inutilisable.
                 */
                'email_verified_at' => $compte->email_verified_at ?? now(),
            ])->save();

            $role = $compte->getRoleNames()->first() ?? '—';
            $lignes[] = sprintf('  %-24s %-12s %s', $adresse, $role, $usage);
        }

        $this->command?->info('  Se connecter avec :');
        foreach ($lignes as $ligne) {
            $this->command?->line($ligne);
        }
        $this->command?->line('  Mot de passe : la valeur de DEMO_PASSWORD dans .env');
    }

    /**
     * Les rôles couverts par les comptes de référence.
     *
     * Exposé pour que les tests puissent vérifier qu'aucun rôle n'est laissé
     * sans porte d'entrée — un rôle sans compte de référence est un écran que
     * personne ne sait aller voir.
     *
     * @return list<UserRole>
     */
    public static function rolesCouverts(): array
    {
        return [UserRole::Admin, UserRole::Mentor, UserRole::Moderateur, UserRole::Citoyen];
    }
}
