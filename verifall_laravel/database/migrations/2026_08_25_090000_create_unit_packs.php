<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les packs d'unités sortent de la configuration et entrent en base.
 *
 * C'est la réserve n° 15 des arbitrages : le prototype et la spécification de
 * paiement divergent d'un facteur deux à deux et demi sur la valeur vendue, et
 * ce n'est pas une décision technique — c'est une décision commerciale. Tant
 * qu'elle vivait dans `config/economy.php`, la changer demandait un
 * développeur, un commit et un déploiement. Elle demande maintenant un écran.
 *
 * `price_fcfa` en entier : le franc CFA n'a pas de subdivision en usage, et un
 * flottant sur un montant est une erreur d'arrondi qui attend son tour.
 *
 * Le CODE est la clé stable — c'est lui que le client envoie, et lui seul. Le
 * prix et la quantité restent lus côté serveur : un client modifié qui
 * enverrait « 10 000 unités » n'obtiendrait rien de plus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_packs', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            /** Envoyé par le client, jamais deviné : « p10 », « p25 »… */
            $table->string('code', 32)->unique();
            $table->string('label');
            $table->unsignedInteger('units');
            $table->unsignedInteger('price_fcfa');
            /** Le pack mis en avant sur l'écran d'achat. Un seul à la fois. */
            $table->boolean('is_featured')->default(false);
            /*
             | Désactivé plutôt que supprimé : un pack effacé rendrait
             | illisibles les paiements qui le référencent. On le retire de la
             | vente, on ne le retire pas de l'histoire.
             */
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_packs');
    }
};
