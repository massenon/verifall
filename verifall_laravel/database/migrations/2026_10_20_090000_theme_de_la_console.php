<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Le thème choisi dans la console (clair | sombre), gardé avec le compte pour
| le retrouver d'un appareil à l'autre. Le navigateur reste prioritaire sur
| l'appareil en cours : il répond avant le premier rendu, sans flash.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('console_theme', 8)->nullable()->after('notification_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('console_theme');
        });
    }
};
