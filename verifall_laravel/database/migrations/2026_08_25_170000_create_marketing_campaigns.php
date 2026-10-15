<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les campagnes d'affichage du site public — fenêtres et bandeaux.
 *
 * ## Pourquoi `marketing_campaigns` et non `campaigns`
 *
 * Parce que `campaigns` existe déjà, et désigne les campagnes d'E-MAILING :
 * nom, objet, corps, audience, compteurs d'envoi. Le prototype emploie le même
 * mot pour deux choses différentes — ce qui part par courriel vers une
 * audience, et ce qui s'affiche à qui passe sur le site.
 *
 * Le mot du prototype n'est pas un nom de table. Les confondre aurait produit
 * un modèle qui sert deux maîtres, et une requête « les campagnes actives » qui
 * ne veut plus rien dire.
 *
 * ## Pourquoi en base plutôt qu'en dur
 *
 * Une campagne a une durée de vie courte et conjoncturelle : une chaîne
 * WhatsApp qu'on lance, une échéance électorale, une infolettre du lundi. La
 * couper doit prendre dix secondes à quelqu'un qui n'écrit pas de code — sinon
 * elle tourne encore trois mois après la fin de l'opération.
 *
 * ## Ce que la table NE contient PAS
 *
 * Aucun compteur d'affichage ni de clic. Ils viendraient à l'esprit — et ils
 * seraient faux tant qu'ils comptent les robots, comme le compteur de vues des
 * articles l'a fait pendant des mois. Le jour où la mesure servira, elle
 * passera par `content_visits`, qui sait déjà écarter ce qui n'est pas humain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Le slug identifie la campagne côté navigateur : c'est lui qui
            // sert à retenir qu'une personne l'a déjà écartée.
            $table->string('slug', 80)->unique();

            $table->string('title', 120);
            $table->text('text');

            $table->string('cta', 60);
            $table->string('url', 255);
            $table->string('image', 255)->nullable();

            /*
             | La teinte, par un NOM et non un code hexadécimal : le design
             | system du prototype fait foi, et une couleur libre saisie dans un
             | formulaire finit toujours par sortir de la palette.
             */
            $table->string('color', 20)->default('primary');

            /*
             | `popup` s'impose par-dessus la page, `banner` s'insère dans le
             | flux. Ce n'est pas une préférence esthétique : une fenêtre
             | interrompt, un bandeau accompagne.
             */
            $table->string('type', 20)->default('popup');

            // Un interrupteur, pas une suppression : une campagne éteinte garde
            // son texte, prête à repartir.
            $table->boolean('is_active')->default(false);

            /*
             | Une fenêtre qui s'ouvre à la seconde où la page s'affiche est
             | écartée sans avoir été lue. Le délai laisse voir la page d'abord.
             */
            $table->unsignedSmallInteger('delay_seconds')->default(8);

            /*
             | Fenêtre de diffusion, les deux bornes facultatives. Une campagne
             | sans date de fin tourne jusqu'à ce qu'on l'éteigne — ce qui
             | convient à une chaîne WhatsApp permanente, pas à une échéance.
             */
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'type', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
