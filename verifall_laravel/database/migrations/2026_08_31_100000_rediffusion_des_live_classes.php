<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B-06 — la rediffusion d'une Live Class n'existait nulle part.
 *
 * ## Le défaut, et pourquoi il n'était pas là où on le croyait
 *
 * Le bouton « Voir le replay » d'une séance terminée pointait sur
 * `route('citizen.tutorials')` — la liste des tutoriels. On cliquait pour
 * revoir une séance et on atterrissait sur un catalogue.
 *
 * Le réflexe est de corriger le lien. Il n'y a rien à corriger : `live_classes`
 * ne portait **aucune** colonne de rediffusion. Le lien vers la liste était un
 * repli assumé, faute de quoi que ce soit à ouvrir. Le défaut est ici, dans le
 * schéma, pas dans le gabarit.
 *
 * ## Trois colonnes, et le rôle distinct de chacune
 *
 * `replay_url` — l'adresse de l'enregistrement. Nullable, et elle le restera
 * pour la plupart des séances : toutes ne sont pas enregistrées, et une carte
 * doit pouvoir dire « pas de rediffusion » sans que ce soit une anomalie.
 *
 * `replay_published_at` — la date de MISE À DISPOSITION, qui n'est pas celle du
 * téléversement. Une séance s'enregistre le mardi et se publie le jeudi, une
 * fois relue et coupée. Sans cette colonne, déposer l'adresse publierait
 * aussitôt : il faudrait retenir l'URL ailleurs en attendant, c'est-à-dire
 * nulle part. C'est aussi elle qui permet de retirer une rediffusion sans
 * perdre son adresse.
 *
 * `recording_minutes` — la durée de l'enregistrement, distincte de `minutes`
 * qui est celle **prévue** pour la séance. Une séance d'une heure annoncée peut
 * en durer quarante ; afficher « 60 min » sur une vidéo qui en fait 40 fait
 * renoncer avant de lancer la lecture.
 *
 * Aucun index : on ne cherche jamais une séance PAR sa rediffusion, on lit
 * celle d'une séance déjà trouvée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_classes', function (Blueprint $table): void {
            $table->string('replay_url', 500)->nullable()->after('join_url');
            $table->timestamp('replay_published_at')->nullable()->after('replay_url');
            $table->unsignedSmallInteger('recording_minutes')->nullable()->after('replay_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('live_classes', function (Blueprint $table): void {
            $table->dropColumn(['replay_url', 'replay_published_at', 'recording_minutes']);
        });
    }
};
