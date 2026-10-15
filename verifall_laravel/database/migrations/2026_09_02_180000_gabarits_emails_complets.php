<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les gabarits d'emails deviennent des gabarits, et pas seulement des objets.
 *
 * ## Ce que la table portait
 *
 * `key` et `subject`. Rien d'autre. On pouvait donc changer la ligne que la
 * personne lit dans sa boîte, et **rien du message qu'elle ouvre** : le corps
 * vivait dans un gabarit Blade, modifiable par un développeur seulement. Un
 * écran nommé « Gabarits d'emails » qui ne laisse pas toucher au corps promet
 * plus qu'il ne tient.
 *
 * ## `body` en Markdown, et pas en HTML libre
 *
 * Trois raisons, dans l'ordre de leur importance :
 *
 *   1. **Un courriel part hors de l'application.** Aucun filtre d'affichage ne
 *      le rattrapera, et les clients de messagerie sont d'une tolérance
 *      redoutable au HTML cassé. Le Markdown ferme la porte à la balise mal
 *      fermée qui décale toute la mise en page chez la moitié des destinataires.
 *   2. **Le rendu reste celui de la charte.** Le corps décrit ce qu'on dit ;
 *      l'habillage — en-tête, couleurs, pied, mention légale — reste au gabarit
 *      de mise en page, donc identique partout et modifiable en un endroit.
 *   3. Le HTML libre finit toujours par contenir un `<style>` que Gmail retire,
 *      et personne ne comprend pourquoi le message est laid chez l'un et pas
 *      chez l'autre.
 *
 * ## `variables` est DÉCLARATIF, pas décoratif
 *
 * La colonne garde la liste des marqueurs que l'envoi fournit réellement. Elle
 * sert à deux choses : les afficher à l'écran (on n'écrit pas `{{ prenom }}` de
 * mémoire), et **refuser à l'enregistrement** un marqueur qui n'existe pas. Un
 * `{{ prénom }}` accentué, ou un `{{ nom }}` qu'aucun envoi ne remplit,
 * partirait tel quel dans trois mille boîtes.
 *
 * ## `is_active` — pourquoi un interrupteur
 *
 * La liste des courriels s'étend à des événements qui n'étaient jusqu'ici que
 * des notifications internes : badge décroché, unités créditées, réaction
 * reçue. Recevoir un courriel à chaque réaction sur un message de forum est le
 * genre d'attention qui fait cliquer sur « désabonner de tout ». L'interrupteur
 * rend la décision réversible en un clic, et les envois les plus bavards sont
 * livrés ÉTEINTS — voir `CatalogueDesCourriels`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            // Titre affiché EN TÊTE du message, distinct de l'objet : la ligne
            // de la boîte de réception et le titre de la lettre n'ont pas le
            // même travail à faire.
            $table->string('heading', 200)->nullable()->after('subject');

            $table->text('body')->nullable()->after('heading');

            // Libellé et adresse du bouton d'action, quand le message en porte un.
            $table->string('cta_label', 80)->nullable()->after('body');

            /*
             | Marqueurs réellement fournis par l'envoi. Alimentée depuis le
             | catalogue à chaque semis, jamais saisie à la main : c'est le code
             | qui sait ce qu'il passe au gabarit, pas l'écran.
             */
            $table->json('variables')->nullable()->after('cta_label');

            $table->boolean('is_active')->default(true)->after('variables');

            // Qui a touché en dernier, et quand. `updated_at` seul dirait quand
            // sans dire qui — insuffisant sur un texte qui part au public.
            $table->foreignId('updated_by')->nullable()->after('is_active')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['heading', 'body', 'cta_label', 'variables', 'is_active']);
        });
    }
};
