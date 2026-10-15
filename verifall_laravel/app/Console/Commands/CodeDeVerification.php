<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Identity\Services\OtpService;
use App\Enums\OtpPurpose;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Émet et affiche un code de vérification — EN LOCAL SEULEMENT.
 *
 * ## Le mur qu'elle abat
 *
 * L'inscription se termine par un code à six chiffres envoyé par courriel.
 * `InscriptionCitoyen::envoyerLeCode()` le met à la FILE (`Mail::…->queue()`) ;
 * en développement, il suffit que `queue:work` ne tourne pas, ou que le SMTP
 * distant soit injoignable depuis le poste, pour que le message ne parte
 * jamais. L'écran affiche « saisissez le code reçu » et attend indéfiniment.
 *
 * C'est le symptôme signalé — « l'inscription s'arrête à la deuxième étape ».
 * Le formulaire n'a aucun défaut ; c'est le courriel qui manque.
 *
 * `verifall:diagnostic` dit POURQUOI rien ne part. Cette commande-ci débloque
 * la personne qui est déjà devant l'écran.
 *
 * ## Pourquoi une commande, et pas le code affiché à l'écran en local
 *
 * L'affichage direct était l'option envisagée, et elle a été écartée. Un
 * gabarit qui rend un code sous condition d'environnement est à UNE variable
 * mal réglée de le rendre partout — et personne ne s'en apercevrait, puisque
 * rien ne casse. Une commande, elle, doit être tapée : elle ne peut pas fuir
 * toute seule.
 *
 * C'est aussi le précédent de la maison : `verifall:code-2fa` résout le même
 * genre de mur, de la même façon.
 *
 * ## Ce qu'elle ne contourne pas
 *
 * Elle refuse hors environnement local, et surtout : elle n'affiche AUCUN code
 * existant — ils sont hachés en base, et c'est très bien ainsi. Elle en émet un
 * NOUVEAU par le service ordinaire, ce qui invalide le précédent exactement
 * comme un renvoi depuis l'écran. La vérification, elle, reste inchangée.
 */
final class CodeDeVerification extends Command
{
    protected $signature = 'verifall:code-otp
        {email : Adresse du compte}
        {--usage=registration : registration · password_reset · login_challenge · account_deletion}';

    protected $description = 'Émet et affiche un code de vérification à six chiffres (local uniquement).';

    public function handle(OtpService $otp): int
    {
        /*
         | Premier geste, avant toute lecture de la base : sur un serveur où
         | cette commande n'a rien à faire, aucune requête ne doit partir.
         */
        if (! app()->environment('local', 'testing')) {
            $this->error('Cette commande ne s\'exécute qu\'en environnement local.');

            return self::FAILURE;
        }

        $usage = OtpPurpose::tryFrom((string) $this->option('usage'));

        if ($usage === null) {
            $this->error('Usage inconnu. Valeurs acceptées : '.implode(' · ', array_column(OtpPurpose::cases(), 'value')));

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $utilisateur = User::query()->where('email', $email)->first();

        if ($utilisateur === null) {
            $this->error('Aucun compte pour cette adresse.');

            /*
             | Le rappel qui fait gagner le plus de temps : à l'inscription, le
             | compte EST créé au passage de l'étape 2, avant l'envoi du code.
             | Si l'adresse est introuvable, c'est que le formulaire n'a pas
             | encore atteint cette étape — le blocage est ailleurs.
             */
            $this->line('  <fg=gray>À l\'inscription, le compte est créé dès l\'étape 2 : une adresse');
            $this->line('  introuvable signifie que le formulaire n\'y est pas encore arrivé.</>');

            return self::FAILURE;
        }

        $code = $otp->issue($utilisateur->email, $usage, '127.0.0.1');

        $this->newLine();
        $this->line('  <fg=gray>'.$utilisateur->email.' · '.$usage->value.'</>');
        $this->line('  <options=bold;fg=green>'.$code.'</>');
        $this->newLine();
        $this->line('  <fg=gray>Valable 10 minutes. Le code précédent, s\'il en existait un, vient');
        $this->line('  d\'être invalidé — exactement comme un renvoi depuis l\'écran.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
