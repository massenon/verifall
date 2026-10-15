<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le suivi des visites sur les contenus publics.
 *
 * ## Ce qui existait, et pourquoi ça ne comptait rien
 *
 * Un `increment('views')` au montage du composant. Il comptait donc : chaque
 * rafraîchissement de page, chaque passage de robot d'indexation, chaque
 * aperçu de lien généré par WhatsApp ou Facebook au moment du partage. Sur un
 * article partagé cent fois, l'aperçu seul suffisait à gonfler le compteur.
 *
 * Un chiffre faux est pire qu'une absence de chiffre : il se lit comme vrai,
 * et il oriente des décisions éditoriales.
 *
 * ## Ce que cette table stocke — et surtout ce qu'elle ne stocke PAS
 *
 * Pas d'adresse IP. Pas d'agent utilisateur. Pas d'identifiant de compte.
 *
 * À la place, `visitor_hash` : une empreinte de l'IP et de l'agent, salée avec
 * la clé de l'application ET la date du jour. Deux propriétés en découlent :
 *
 *  - elle permet de reconnaître deux visites de la même personne le même jour,
 *    ce qui est exactement ce qu'il faut pour dédoublonner ;
 *  - elle cesse de la reconnaître le lendemain, le sel ayant changé. Le suivi
 *    ne peut donc pas servir à retracer un parcours dans le temps, même par
 *    quelqu'un qui aurait accès à la base.
 *
 * C'est la minimisation exigée par le RGPD, obtenue par construction plutôt
 * que par une promesse d'effacement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_visits', function (Blueprint $table): void {
            $table->id();

            // Polymorphe : un article de blog, un fait vérifié, une brève du Hub.
            $table->morphs('visitable');

            /*
             | L'empreinte du visiteur, salée au jour. 64 caractères = SHA-256
             | en hexadécimal. Jamais l'IP, jamais l'agent.
             */
            $table->char('visitor_hash', 64);

            /*
             | Le nom d'hôte du référent, et lui seul — pas l'URL complète.
             | Savoir qu'une visite vient de `facebook.com` sert à l'éditorial ;
             | conserver l'URL exacte du fil d'où l'on vient ne sert à rien et
             | peut désigner une personne.
             */
            $table->string('referrer_host', 255)->nullable();

            /*
             | Un robot reconnu n'est pas supprimé mais MARQUÉ. Le distinguer
             | permet de mesurer aussi l'indexation — utile au référencement —
             | sans jamais le mêler à l'audience humaine.
             */
            $table->boolean('is_bot')->default(false);

            $table->timestamp('visited_at')->useCurrent();

            /*
             | L'index qui porte le dédoublonnage : « cette personne a-t-elle
             | déjà vu ce contenu aujourd'hui ? ». Il sert aussi aux comptages
             | par contenu, qui sont la lecture la plus fréquente.
             */
            $table->index(['visitable_type', 'visitable_id', 'visitor_hash', 'visited_at'], 'visites_dedoublonnage');
            $table->index(['visited_at', 'is_bot'], 'visites_par_periode');
        });

        /*
         | `verified_facts` n'avait aucun compteur, alors que ce sont les
         | contenus dont l'audience compte le plus sur un site de fact-checking.
         */
        Schema::table('verified_facts', function (Blueprint $table): void {
            $table->unsignedInteger('views')->default(0)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_visits');

        Schema::table('verified_facts', function (Blueprint $table): void {
            $table->dropColumn('views');
        });
    }
};
