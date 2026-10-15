<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Identity\Services\TwoFactorService;
use App\Models\User;
use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;

/**
 * Affiche le code TOTP courant d'un compte du personnel — EN LOCAL SEULEMENT.
 *
 * POURQUOI ELLE EXISTE. Les comptes du back-office exigent un second facteur, ce
 * qui est juste : le mot de passe d'un compte de démonstration circule dans une
 * documentation. Mais en développement, personne n'a l'application
 * d'authentification sous la main, et l'écran de connexion devient un mur.
 *
 * La réponse habituelle — « désactive la 2FA pour ce compte » — ne marche même
 * pas ici : `StaffLogin` bascule alors sur l'ENRÔLEMENT, qui redemande un code.
 * Le seul déblocage réel est de fournir le code.
 *
 * CE QU'ELLE N'AFFAIBLIT PAS. Elle refuse de s'exécuter hors environnement
 * local, elle n'est jamais planifiée, et elle ne change rien à la vérification :
 * le code affiché est celui que l'application d'authentification afficherait.
 * En production, elle échoue avant de lire quoi que ce soit.
 */
final class CodeDeConnexionStaff extends Command
{
    protected $signature = 'verifall:code-2fa {email : Adresse du compte}';

    protected $description = 'Affiche le code de double authentification d\'un compte du personnel (local uniquement).';

    public function handle(TwoFactorService $deuxFacteurs, Google2FA $google2fa): int
    {
        /*
         | Le garde-fou, et il est volontairement le tout premier geste : il
         | s'exécute AVANT de lire la base, pour qu'aucune requête ne parte sur
         | un serveur où cette commande n'a rien à faire.
         */
        if (! app()->environment('local', 'testing')) {
            $this->error('Cette commande ne s\'exécute qu\'en environnement local.');

            return self::FAILURE;
        }

        $utilisateur = User::query()->where('email', $this->argument('email'))->first();

        if ($utilisateur === null) {
            $this->error('Aucun compte pour cette adresse.');

            return self::FAILURE;
        }

        /*
         | Compte jamais enrôlé : on l'enrôle et on confirme dans la foulée.
         |
         | Sans cela la commande dirait « pas de secret » et laisserait la
         | personne devant le même mur — alors que c'est précisément le cas où
         | elle en a le plus besoin.
         */
        if (! $deuxFacteurs->estActive($utilisateur)) {
            /*
             | Purge d'abord. Si le compte porte un secret devenu illisible —
             | clé d'application changée — `preparer()` lèverait avant d'avoir
             | rien écrit : `save()` compare à l'état d'origine, et cette
             | comparaison déchiffre justement la valeur qu'on veut remplacer.
             */
            $deuxFacteurs->reinitialiser($utilisateur);

            $preparation = $deuxFacteurs->preparer($utilisateur);
            $utilisateur->forceFill(['two_factor_confirmed_at' => now()])->save();

            $this->warn('Compte non enrôlé : un secret vient d\'être créé.');
            $this->line('  Secret     : '.$preparation['secret']);
            $this->line('  Récupérat. : '.implode(' ', $preparation['codes']));
            $this->newLine();

            $utilisateur->refresh();
        }

        $secret = (string) $utilisateur->two_factor_secret;
        $code = $google2fa->getCurrentOtp($secret);

        // Le temps restant compte autant que le code : à trois secondes de la
        // fin, il faut attendre le suivant plutôt que le saisir et échouer.
        $restant = 30 - (int) (time() % 30);

        $this->newLine();
        $this->info('  Compte  : '.$utilisateur->email.'  ('.$utilisateur->getRoleNames()->implode(', ').')');
        $this->info('  Code    : '.$code);
        $this->line('  Valide encore '.$restant.' seconde(s).');
        $this->newLine();
        $this->line('  À scanner dans une application d\'authentification :');
        $this->line('  '.$deuxFacteurs->uriProvisioning($utilisateur));
        $this->newLine();

        return self::SUCCESS;
    }
}
