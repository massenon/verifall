<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Console\Commands\AncienneBase\LitLAncienneBase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Les comptes de l'ancienne plateforme, avec leurs identifiants de connexion
 * INTACTS — adresse et empreinte de mot de passe à l'octet près.
 *
 *     php artisan db:seed --class=AnciensComptesSeeder
 *
 * ## Pourquoi un seeder qui délègue, et pas un fichier de comptes
 *
 * La reprise existe déjà : `verifall:importer-ancienne-base`, écrite, testée,
 * exécutée en production (voir `docs/MIGRATION_ANCIENNE_BASE.md`). Elle garde
 * les empreintes bcrypt telles quelles, recâble les vérifications, pose
 * `otp_verified_at` et reporte les soldes. Un second chemin d'import aurait
 * dû refaire tout cela, et diverger au premier correctif.
 *
 * Et surtout : **aucune empreinte ni adresse n'entre dans le dépôt.** Recopier
 * 133 empreintes et adresses personnelles dans `database/seeders/data/`
 * les aurait livrées à quiconque lit le code — la règle « jamais de secret en
 * dur » et le RGPD l'interdisent tous deux. Le seeder lit le dump là où il est.
 *
 * ## Ce qu'il garantit en plus de la commande
 *
 * Après l'import, il RELIT le dump et compare chaque empreinte à celle de la
 * base. Un compte absent arrête le seeder en échec : c'est la promesse faite aux
 * anciens utilisateurs (« reconnectez-vous avec votre mot de passe »), et elle
 * se vérifie plutôt qu'elle ne se suppose. Une empreinte différente est
 * signalée sans lever : c'est une adresse déjà inscrite avant la reprise, dont
 * le compte existant gagne (§3.2 de la documentation).
 *
 * Idempotent, comme la commande : rejoué, il ne duplique rien et n'écrase aucun
 * compte existant. Hors du verrou de démonstration, volontairement : ce sont des
 * comptes RÉELS, destinés précisément à la production.
 */
final class AnciensComptesSeeder extends Seeder
{
    use LitLAncienneBase;

    public function run(): void
    {
        $chemin = (string) (config('verifall.ancienne_base.dump')
            ?: base_path('../existingdb/massenon_verifalltest.sql'));

        if (! is_readable($chemin)) {
            throw new RuntimeException(
                'Dump de l\'ancienne base introuvable : '.$chemin
                .'. Renseignez ANCIENNE_BASE_DUMP dans .env.'
            );
        }

        $code = Artisan::call('verifall:importer-ancienne-base', ['--fichier' => $chemin]);
        $this->command?->getOutput()->write(Artisan::output());

        if ($code !== 0) {
            throw new RuntimeException('La reprise des comptes a échoué (code '.$code.') ; rien n\'a été contrôlé.');
        }

        [$total, $identiques, $absents, $differents] = $this->controlerLesEmpreintes($chemin);

        // Les adresses ne sont jamais imprimées : cette sortie finit dans les journaux.
        if ($absents > 0) {
            throw new RuntimeException(sprintf(
                '%d compte(s) sur %d de l\'ancienne base sont ABSENTS après la reprise.',
                $absents, $total,
            ));
        }

        $this->command?->info(sprintf(
            '  Identifiants intacts : %d / %d empreintes identiques à l\'octet près.',
            $identiques, $total,
        ));

        if ($differents > 0) {
            /*
             | Pas une panne : c'est la règle « le compte existant gagne ». Une
             | adresse déjà inscrite sur la nouvelle plateforme AVANT la reprise
             | garde le mot de passe qu'elle emploie aujourd'hui, plutôt que
             | celui d'il y a six mois. Signalé, donc, et non levé.
             */
            $this->command?->warn(sprintf(
                '  %d compte(s) gardent leur mot de passe de la NOUVELLE plateforme : adresse déjà inscrite avant la reprise.',
                $differents,
            ));
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} total, identiques, absents, différents
     */
    private function controlerLesEmpreintes(string $chemin): array
    {
        $total = 0;
        $identiques = 0;
        $absents = 0;
        $differents = 0;

        foreach ($this->lireTable($chemin, 'users') as $ancien) {
            $adresse = mb_strtolower(trim((string) $ancien['email']));

            if ($adresse === '') {
                continue;
            }

            $total++;
            // `withTrashed` : un compte supprimé en douceur dans l'ancienne base
            // est repris supprimé, et n'en est pas moins présent.
            $empreinte = User::withTrashed()->where('email', $adresse)->value('password');

            if ($empreinte === null) {
                $absents++;
            } elseif (hash_equals((string) $empreinte, (string) $ancien['password'])) {
                $identiques++;
            } else {
                $differents++;
            }
        }

        return [$total, $identiques, $absents, $differents];
    }
}
