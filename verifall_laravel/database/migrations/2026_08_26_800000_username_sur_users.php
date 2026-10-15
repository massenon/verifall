<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Un identifiant public stable, pour que les mentions aient une cible.
 *
 * ## Pourquoi il en fallait un
 *
 * `users` ne portait que `name`, et un nom contient des espaces : « @Awa »
 * pour « Awa Sossou » est ambigu dès qu'une deuxième Awa s'inscrit, et le
 * jour où quelqu'un change de nom, toutes les mentions qui le visaient
 * cessent de pointer sur lui.
 *
 * ## Il n'est PAS demandé à l'inscription
 *
 * Il est dérivé du nom. Ajouter un champ obligatoire au formulaire pour une
 * fonctionnalité que la personne ne connaît pas encore ferait abandonner des
 * inscriptions, et le nom suffit à produire un identifiant lisible.
 *
 * ## Nullable, et c'est délibéré
 *
 * Un compte dont le nom ne produirait aucun identifiant utilisable — un nom
 * entièrement composé de caractères non latins, par exemple — reste valide
 * sans identifiant. Il ne pourra simplement pas être mentionné, ce qui est
 * une gêne, pas une panne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->unique()->after('name');
        });

        $this->remplirLesComptesExistants();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('username');
        });
    }

    /**
     * Remplit les comptes déjà en base.
     *
     * Par lots : sur une base en service, charger tous les comptes d'un coup
     * ferait tomber la migration bien avant d'avoir traité le premier millier.
     */
    private function remplirLesComptesExistants(): void
    {
        $pris = [];

        DB::table('users')->select('id', 'name')->orderBy('id')->chunk(200, function ($lot) use (&$pris): void {
            foreach ($lot as $compte) {
                $base = Str::slug((string) $compte->name);

                if ($base === '') {
                    continue;
                }

                $base = mb_substr($base, 0, 40);
                $candidat = $base;
                $suffixe = 1;

                // La collision se résout par un suffixe numérique. Deux
                // « Awa Sossou » donnent `awa-sossou` et `awa-sossou-2`.
                while (
                    isset($pris[$candidat])
                    || DB::table('users')->where('username', $candidat)->exists()
                ) {
                    $suffixe++;
                    $candidat = $base.'-'.$suffixe;
                }

                $pris[$candidat] = true;

                DB::table('users')->where('id', $compte->id)->update(['username' => $candidat]);
            }
        });
    }
};
