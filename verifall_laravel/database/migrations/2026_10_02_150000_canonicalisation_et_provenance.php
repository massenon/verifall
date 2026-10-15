<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — URL demandée, finale, canonique, domaine, éditeur, dates.
 *
 * Contrat V-9 : une preuve est « un document retrouvable, daté, attribué à un
 * éditeur ». `source_url` seule ne tenait aucune de ces trois promesses — elle
 * ne distinguait pas l'adresse DEMANDÉE de l'adresse réellement ouverte, ni
 * un redirecteur d'un éditeur.
 *
 * Toutes NULLABLES, défaut `NULL` partout : les lignes déjà écrites restent
 * lisibles sans qu'aucune ne soit retouchée. `url_finale` et `recupere_le` ne
 * seront RÉELLEMENT renseignées qu'au collecteur de la phase 6 — aucun
 * adaptateur ne fetch encore une page aujourd'hui — mais la colonne existe
 * dès maintenant : « aucune migration au-delà de la phase 5 » pour la 6.
 *
 * `role` et `pertinence` appartiennent nommément à V-10 (phase 7) dans le
 * contrat d'origine ; consolidées ici plutôt que dans une migration de plus,
 * puisqu'aucune des deux n'a encore été poussée en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_evidences', function (Blueprint $table): void {
            // Après résolution des redirections — renseignée au collecteur
            // de la phase 6, pas avant.
            $table->string('url_finale', 500)->nullable()->after('source_url');

            // <link rel="canonical"> de la page — idem, phase 6.
            $table->string('url_canonique', 500)->nullable()->after('url_finale');

            // Domaine ENREGISTRABLE (eTLD+1), calculable dès aujourd'hui
            // depuis `source_url` par {@see DomaineDeLaSource::domaineEnregistrable()}.
            $table->string('domaine', 255)->nullable()->after('url_canonique');

            // Jamais un redirecteur — NULL quand l'éditeur ne peut pas être
            // établi, plutôt que de porter l'infrastructure de découverte.
            $table->string('editeur', 255)->nullable()->after('domaine');

            // PRIMAIRE | SECONDAIRE | FACT_CHECK | TECHNIQUE — phase 7.
            $table->string('role', 20)->nullable()->after('editeur');

            // Relation au CLAIM, pas à la source — phase 7/10.
            $table->unsignedTinyInteger('pertinence')->nullable()->after('reliability');

            // Renseignée SI ET SEULEMENT SI la page a été ouverte — la forme
            // forte de `fetched` (lot 4) une fois le collecteur en place.
            $table->timestamp('recupere_le')->nullable()->after('published_at');

            // La période où le FAIT vaut, pas la date de publication du
            // document : une page de 2024 peut établir un fait valable
            // depuis 2016.
            $table->date('valide_du')->nullable()->after('recupere_le');
            $table->date('valide_au')->nullable()->after('valide_du');
        });
    }

    public function down(): void
    {
        Schema::table('report_evidences', function (Blueprint $table): void {
            $table->dropColumn([
                'url_finale', 'url_canonique', 'domaine', 'editeur', 'role',
                'pertinence', 'recupere_le', 'valide_du', 'valide_au',
            ]);
        });
    }
};
