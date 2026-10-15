<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce que la fiche d'un cours, une leçon et un article ne pouvaient pas porter.
 *
 * ## Le référencement du Hub, qui n'était pas surchargeable
 *
 * `blog_posts` portait `meta_title` et `meta_description` ; `hub_articles` non,
 * et `Referencement::pourFaitVerifie()` composait titre et description à partir
 * des seuls champs éditoriaux. Ce n'était pas un bug — rien ne se cassait —
 * mais un manque : aucun rédacteur ne pouvait ajuster ce que Google affiche
 * d'un démenti.
 *
 * Les colonnes seules ne suffisaient pas : `pourFaitVerifie()` a été modifiée
 * dans le même mouvement pour les honorer. Sans cela, le formulaire aurait
 * enregistré des valeurs que rien ne lit.
 *
 * ## `prerequisites` et `outcomes` : deux listes, un seul format
 *
 * `outcomes` existait déjà en JSON. `prerequisites` adopte le même : l'écran
 * saisit une ligne par attente, et le stockage garde la liste plutôt qu'un bloc
 * de texte. Une liste stockée en texte se ré-affiche telle qu'elle a été tapée,
 * retours à la ligne compris — et se met en page de travers dès qu'on change de
 * gabarit.
 *
 * ## `xp_reward` est sur le COURS, pas sur la leçon
 *
 * La prime est attribuée à 100 % d'avancement : elle ne peut pas vivre sur une
 * leçon, qui ne sait rien de l'ensemble. Les leçons portent la durée ; le cours
 * porte la récompense.
 *
 * ## `lesson_attachments` — disque PRIVÉ, et ce n'est pas une précaution de trop
 *
 * Un cours PAYANT dont les PDF sont servis par une URL devinable est un cours
 * vendu une fois et diffusé cent. Les pièces vivent donc hors du site, comme
 * les justificatifs KYC, et ne transitent que par une route signée qui vérifie
 * l'inscription.
 *
 * `original_name` est conservé pour l'AFFICHAGE seulement — le fichier sur le
 * disque porte un nom aléatoire. Sans lui, la liste afficherait des uuid, et
 * personne ne saurait lequel ouvrir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            // Même format que `outcomes`, déjà en JSON : une liste reste une liste.
            $table->json('prerequisites')->nullable()->after('outcomes');

            // 5 caractères : « fr », « en », et la place pour « fr-BJ » si le
            // besoin d'une variante locale se présente.
            $table->string('language', 5)->default('fr')->after('level');

            /*
             | La prime versée à 100 % d'avancement. Par défaut ZÉRO, et non une
             | valeur « raisonnable » : une récompense qu'on n'a pas décidée est
             | une inflation d'XP que personne n'a voulue.
             */
            $table->unsignedInteger('xp_reward')->default(0)->after('delivers_certificate');

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('audio_url')->nullable()->after('video_url');
        });

        Schema::create('lesson_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();

            // Chemin sur le disque PRIVÉ, nom aléatoire.
            $table->string('path');

            // Le nom d'origine, pour l'affichage. Le fichier, lui, ne le porte pas.
            $table->string('original_name');

            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        Schema::table('hub_articles', function (Blueprint $table): void {
            // `blog_posts` les portait ; ces trois-là manquaient, et
            // `pourFaitVerifie()` a été modifiée pour les honorer.
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            // Les deux autres existaient déjà.
            $table->string('meta_keywords', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn('meta_keywords');
        });

        Schema::table('hub_articles', function (Blueprint $table): void {
            $table->dropColumn(['meta_title', 'meta_description', 'meta_keywords']);
        });

        Schema::dropIfExists('lesson_attachments');

        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('audio_url');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn([
                'prerequisites', 'language', 'xp_reward',
                'meta_title', 'meta_description', 'meta_keywords',
            ]);
        });
    }
};
