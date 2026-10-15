<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 10 — deux journaux qui rendent visible ce qui échoue en silence.
 *
 * ## Le problème que ces tables résolvent
 *
 * `CLAUDE.md` porte un encadré entier sur le sujet, et il est sans ambiguïté :
 * sans `queue:work`, le code OTP d'inscription, l'export RGPD, la carte de
 * démenti et l'infolettre **s'arrêtent net sans qu'aucune erreur ne le
 * signale**. Le job attend en table `jobs`, la page répond 200, la suite de
 * tests reste verte.
 *
 * Le symptôme observé — « l'inscription s'arrête à la deuxième étape » — a
 * coûté un lot entier à diagnostiquer, parce que rien n'était observable :
 * personne ne pouvait répondre à « le courriel est-il parti ? ».
 *
 * Ces deux tables répondent, chacune pour son mécanisme.
 *
 * ## `mail_logs` — ce qui est parti, et ce qui a échoué
 *
 * Alimentée par les événements `MessageSending` / `MessageSent` de Laravel, via
 * un écouteur. Aucun Mailable n'a à s'en soucier : ce qui passe par le
 * transport est journalisé, y compris ce qu'on ajoutera demain.
 *
 * **Le CORPS n'est PAS stocké.** Un code à usage unique en base annulerait la
 * précaution que `OtpCodeMail` documente en toutes lettres : « il ne doit
 * apparaître NULLE PART ailleurs — ni en journal, ni en base, ni dans une
 * exception ». On garde le destinataire, l'objet, l'état, l'erreur. Assez pour
 * répondre à la question ; pas assez pour créer une fuite.
 *
 * ## `scheduled_task_runs` — le planificateur tourne-t-il vraiment ?
 *
 * `verifall:diagnostic` compte les tâches déclarées et rappelle qu'aucune ne
 * s'exécute sans une entrée cron. Il ne peut pas dire si le cron EXISTE : rien
 * ne l'atteste.
 *
 * Un écouteur sur `ScheduledTaskFinished` / `ScheduledTaskFailed` l'atteste
 * désormais. Une tâche déclarée sans aucune exécution enregistrée, c'est un
 * cron absent — et c'est enfin visible à l'écran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            /*
             | Le destinataire, en clair. C'est une donnée personnelle, et sa
             | conservation est bornée : la purge RGPD (`PurgerDonneesExpirees`)
             | efface les lignes au-delà de la durée retenue. Sans destinataire,
             | le journal ne répondrait pas à « le courriel de CETTE personne
             | est-il parti ? », qui est sa seule raison d'être.
             */
            $table->string('recipient', 255);

            $table->string('subject', 255);

            /*
             | La classe du Mailable, quand elle est connue. Elle permet de
             | filtrer « tous les codes OTP » sans se fier au libellé du sujet,
             | qui est désormais surchargeable depuis l'administration (lot 9).
             */
            $table->string('mailable', 190)->nullable();

            // 'sent' | 'failed'
            $table->string('status', 12)->default('sent');

            /*
             | Le message d'erreur du transport. Tronqué à 1 000 caractères :
             | une trace SMTP complète peut faire des dizaines de lignes, et ce
             | qui sert au diagnostic tient dans les premières.
             */
            $table->string('error', 1000)->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('recipient');
        });

        Schema::create('scheduled_task_runs', function (Blueprint $table): void {
            $table->id();

            /*
             | La description de la tâche telle que Laravel la nomme — la
             | commande, ou un résumé de la fermeture. C'est la seule clé
             | stable dont on dispose : les tâches n'ont pas d'identifiant.
             */
            $table->string('task', 255);

            // 'success' | 'failed'
            $table->string('status', 12);

            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();

            /*
             | Index sur (tâche, date) : l'écran demande « la dernière
             | exécution de chaque tâche », ce qui est exactement ce parcours.
             */
            $table->index(['task', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
        Schema::dropIfExists('mail_logs');
    }
};
