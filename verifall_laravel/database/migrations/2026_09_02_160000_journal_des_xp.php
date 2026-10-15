<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le journal des points d'expérience — ce que les unités avaient et pas l'XP.
 *
 * ## Le commentaire de `User` l'annonçait déjà, et il était faux
 *
 * `app/Models/User.php` place `units`, `xp` et `streak_days` hors `$fillable`
 * avec cette justification : « l'économie ne s'écrit que par EconomyService, de
 * façon transactionnelle ». C'est vrai des unités, qui tiennent `unit_logs`.
 * **Ce ne l'était pas de l'XP** : quatre endroits appelaient
 * `$utilisateur->increment('xp', ...)` directement — le quiz de cours, le quiz
 * d'accueil, les badges, et la prime de fin de cours ajoutée la veille.
 *
 * On savait donc combien de points quelqu'un possédait, jamais d'où ils
 * venaient. Une correction de solde était impossible à justifier, et un litige
 * — « j'ai fini ce cours et je n'ai rien reçu » — impossible à trancher
 * autrement qu'en croyant l'un ou l'autre.
 *
 * ## Calqué sur `unit_logs`, volontairement
 *
 * Mêmes colonnes, même discipline : `delta` signé, `balance_after` pour rendre
 * chaque ligne vérifiable sans rejouer l'historique, `reason` en valeur d'enum,
 * `label` canonique — donc écrit dans UNE langue, celle du repli, parce qu'un
 * journal panaché ne se cherche plus. Deux journaux qui se ressemblent se
 * relisent avec la même habitude ; en inventer un second dialecte n'aurait
 * servi personne.
 *
 * `balance_after` est un `integer` SIGNÉ ici, là où les unités le prennent non
 * signé. L'XP ne se dépense pas aujourd'hui, mais un ajustement d'administrateur
 * négatif est le premier besoin qui viendra — et une colonne non signée
 * transformerait cette correction en erreur SQL au lieu d'un solde à zéro.
 *
 * ## Aucune mise à jour prévue
 *
 * Comme pour les unités : un mouvement erroné se corrige par un mouvement
 * inverse, pas par une réécriture. Un journal qu'on récrit ne prouve rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->integer('delta');

            // Signé — voir le bloc ci-dessus.
            $table->integer('balance_after');

            $table->string('reason', 30);
            $table->string('label');

            // Origine facultative : un quiz, un cours, un badge.
            $table->nullableMorphs('source');

            $table->timestamps();

            $table->index(['user_id', 'created_at']);

            // Sert la répartition « d'où viennent vos XP », qui groupe par motif
            // pour un compte donné.
            $table->index(['user_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_logs');
    }
};
