<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identités sociales rattachées à un compte.
 *
 * Une table à part, et non deux colonnes sur `users` : quelqu'un peut relier
 * Google ET Facebook, et perdre l'un ne doit pas lui fermer la porte. Deux
 * colonnes auraient imposé un fournisseur unique, puis une migration le jour où
 * l'on en ajoute un troisième.
 *
 * Ce qu'on NE stocke PAS : ni jeton d'accès, ni jeton de rafraîchissement. Le
 * produit ne demande aucune permission au-delà de l'identité — il n'a donc rien
 * à faire au nom de la personne, et conserver des jetons créerait un butin sans
 * contrepartie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 20);

            /*
             | L'identifiant CHEZ le fournisseur. C'est lui la clé, jamais
             | l'adresse : une adresse se change, se recycle, se réattribue.
             | L'identifiant, lui, désigne la même personne pour toujours.
             */
            $table->string('provider_user_id');

            // Conservée pour l'affichage et le rapprochement, jamais comme
            // preuve d'identité à elle seule.
            $table->string('email')->nullable();
            $table->string('avatar_url', 500)->nullable();

            /*
             | Le fournisseur a-t-il attesté avoir vérifié l'adresse au moment
             | du rattachement ? Conservé pour pouvoir répondre plus tard à
             | « pourquoi ce compte a-t-il été relié ? ».
             */
            $table->boolean('email_verified_by_provider')->default(false);

            $table->timestamp('linked_at');
            $table->timestamp('last_login_at')->nullable();

            $table->timestamps();

            /*
             | Une identité sociale ne désigne qu'UN compte. Sans cette
             | contrainte, deux comptes pourraient revendiquer le même profil
             | Google et se voler la connexion l'un à l'autre.
             */
            $table->unique(['provider', 'provider_user_id']);

            // Et un compte n'a qu'un seul profil par fournisseur.
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
