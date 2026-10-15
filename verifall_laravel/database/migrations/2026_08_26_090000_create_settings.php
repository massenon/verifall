<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les réglages de la plateforme, modifiables sans déploiement.
 *
 * ## Ce qui reste dans `.env`, et ce qui vient ici
 *
 * La frontière n'est pas arbitraire.
 *
 * **Dans `.env`** : ce dont l'application a besoin pour DÉMARRER, et ce qui
 * diffère par environnement — la clé d'application, la connexion à la base, le
 * pilote de file. Les mettre en base créerait une dépendance circulaire : il
 * faut la base pour lire les réglages, et les réglages pour joindre la base.
 *
 * **Ici** : ce qui relève d'une décision de PRODUIT et change sans redéployer —
 * le nom du site, le bonus de bienvenue, les identifiants SMTP, les clés d'API
 * externes. Aujourd'hui, changer l'une de ces valeurs demande un développeur.
 *
 * ## Pourquoi `is_secret` est une colonne et non une convention de nommage
 *
 * Parce que trois comportements en dépendent, et qu'aucun ne doit reposer sur
 * la mémoire de celui qui ajoute un réglage :
 *
 *   1. **Le chiffrement au repos.** Un mot de passe SMTP en clair dans une
 *      table, c'est un mot de passe SMTP dans chaque sauvegarde, chaque export,
 *      chaque copie de base envoyée à un prestataire.
 *   2. **Le non-renvoi au navigateur.** Un formulaire qui pré-remplit le mot de
 *      passe SMTP l'expédie au client à chaque affichage de page. Le champ est
 *      masqué à l'écran ; la valeur, elle, est dans la source.
 *   3. **La purge dans les journaux d'audit.** Le journal enregistre l'avant et
 *      l'après d'une modification. Sans marquage, il archiverait les secrets
 *      qu'on vient de protéger ailleurs.
 *
 * Une convention de nommage (« tout ce qui finit par `_key` ») aurait raté
 * `mail_password` et `stripe_secret`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();

            // La clé technique, telle qu'on l'appelle dans le code.
            $table->string('key', 80)->unique();

            /*
             | `text` et non `string` : une description SEO, une question de
             | quiz d'accueil ou une liste de mots-clés dépassent 255 caractères
             | plus souvent qu'on ne le croit. Et une valeur chiffrée pèse
             | nettement plus lourd que sa forme claire.
             */
            $table->text('value')->nullable();

            // L'onglet où le réglage s'affiche.
            $table->string('group', 40)->index();

            /*
             | Chiffré au repos, jamais renvoyé au navigateur, purgé du journal
             | d'audit. Voir l'en-tête pour le raisonnement.
             */
            $table->boolean('is_secret')->default(false);

            /*
             | Qui a changé quoi. Le journal d'audit porte le détail ; cette
             | colonne porte la réponse immédiate à « qui a touché à ça ? »
             | sans avoir à fouiller.
             */
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
