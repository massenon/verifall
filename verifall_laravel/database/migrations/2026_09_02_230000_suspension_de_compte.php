<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La colonne qui manquait, et les deux courriels qui l'attendaient.
 *
 * ## Ce qui existait, et ce qui n'existait pas
 *
 * `MatricePermissions` déclare `user.suspend` depuis l'origine ; le catalogue
 * des courriels déclare `compte_suspendu` et `compte_retabli` depuis le
 * 2 septembre ; `Staff\Utilisateurs` documente lui-même que « la suspension
 * demande une colonne qui n'existe pas ». Trois déclarations, aucun stockage.
 *
 * ## Trois colonnes, pas une
 *
 * Un `is_suspended` booléen aurait suffi à bloquer l'accès, et n'aurait répondu
 * à aucune des questions qu'on pose ensuite.
 *
 *   · **`suspended_at`** — la DATE, pas un drapeau. Une suspension se conteste,
 *     et « depuis quand » est la première question. Elle sert aussi de drapeau :
 *     non nulle vaut suspendu.
 *   · **`suspension_reason`** — le motif, OBLIGATOIRE au service. Une sanction
 *     sans raison lisible n'est pas contestable ; elle se transforme en message
 *     au support, puis en défiance. C'est aussi ce que le courriel affiche.
 *   · **`suspended_by`** — QUI a tranché. Le journal d'audit le sait déjà, mais
 *     le lire suppose de chercher ; ici, l'information est sur la ligne qu'on
 *     regarde au moment où l'on se demande à qui parler.
 *
 * ## Aucune n'est dans `$fillable`
 *
 * Même discipline que `units` et `xp` : une suspension ne s'écrit que par
 * `SuspensionDeCompte`, qui ferme les sessions, journalise et prévient dans le
 * même geste. Une requête qui pourrait la poser au passage d'un autre
 * formulaire serait une porte dérobée dans la modération.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('otp_verified_at');
            $table->string('suspension_reason', 500)->nullable()->after('suspended_at');

            $table->foreignId('suspended_by')->nullable()->after('suspension_reason')
                ->constrained('users')->nullOnDelete();

            // La liste du back-office filtre sur cet état ; sans index, elle
            // scanne la table entière dès quelques milliers de comptes.
            $table->index('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['suspended_at']);
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
