<?php

declare(strict_types=1);

use App\Core\Identity\Services\DossiersKyc;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La chaîne documentaire KYC, décidée par le porteur du projet le 1er septembre 2026.
 *
 * ## Ce qui existait, et pourquoi c'était intenable
 *
 * `users.kyc_verified_at`, la permission `kyc.review`, et un écran
 * d'administration qui ne pouvait que lever ou retirer le drapeau. **Aucune
 * pièce, aucun dossier, aucun moyen pour un citoyen de demander quoi que ce
 * soit** : il lisait « Identité non vérifiée » sans aucune action possible.
 * Le lot 7 l'avait livré comme une demi-mesure assumée, en l'annonçant à
 * l'écran. Deux issues étaient posées — construire la chaîne, ou retirer le
 * badge. Le porteur du projet a tranché : construire.
 *
 * ## Le numéro de pièce n'est PAS conservé en clair
 *
 * `document_number_hash` porte un HMAC ({@see IdentityHasher}),
 * jamais le numéro. L'examinateur lit le numéro sur l'IMAGE ; le stocker en
 * clair n'ajouterait rien à sa décision et créerait un fichier de numéros de
 * pièces d'identité — exactement le genre de base qu'on ne veut pas détenir.
 *
 * Le haché sert à une seule chose, et elle est utile : reconnaître qu'une même
 * pièce est présentée par deux comptes. Une comparaison d'égalité suffit pour
 * cela ; on n'a jamais besoin de relire la valeur.
 *
 * ## Les images vivent sur le disque PRIVÉ et s'effacent
 *
 * `front_path` et compagnie sont des chemins sur le disque `local`
 * (`storage/app/private`), jamais servis par une URL de stockage. Ils ne
 * transitent que par un contrôleur qui vérifie la signature, la session ET
 * l'habilitation.
 *
 * `purged_at` matérialise la **rétention** : les images sont détruites
 * {@see DossiersKyc::RETENTION_JOURS} jours après la
 * décision, par la purge RGPD déjà planifiée. Ce qui reste ensuite — type de
 * pièce, haché, décision, motif — suffit à PROUVER que le contrôle a eu lieu
 * sans conserver la pièce elle-même. Garder des scans de cartes d'identité
 * indéfiniment serait le pire dossier de la plateforme en cas de fuite.
 *
 * ## `cascadeOnDelete` sur `user_id`
 *
 * Contrairement à la contre-expertise, ici la cascade est la bonne réponse :
 * un compte supprimé emporte ses pièces d'identité. Conserver le dossier d'une
 * personne effacée irait contre la demande même qu'elle a formulée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('document_type', 24);

            // HMAC, jamais le numéro. Indexé : sa seule utilité est de
            // retrouver une pièce déjà présentée par un autre compte.
            $table->string('document_number_hash', 64)->nullable()->index();

            // Chemins sur le disque PRIVÉ. Nuls après purge — le dossier
            // survit à ses pièces.
            $table->string('front_path')->nullable();
            $table->string('back_path')->nullable();
            $table->string('selfie_path')->nullable();

            $table->string('status', 16)->default('pending');

            // Motif de refus : obligatoire au refus, et montré au citoyen.
            // Un refus sans raison n'apprend rien et fait redéposer la même pièce.
            $table->text('rejection_reason')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Quand les images ont été détruites. Nul tant qu'elles existent.
            $table->timestamp('purged_at')->nullable();

            $table->timestamps();

            // La file d'examen se lit « en attente, les plus anciens d'abord ».
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_submissions');
    }
};
