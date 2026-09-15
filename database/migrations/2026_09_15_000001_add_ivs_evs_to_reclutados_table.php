<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * IVs y EVs del reclutado (StatsValue serializado, claves cortas
     * {hp, atk, def, spAtk, spDef, speed}). Nullable: legacy rows quedan
     * en ceros vía cast StatsReclutado.
     */
    public function up(): void
    {
        Schema::table('reclutados', function (Blueprint $table) {
            $table->json('evs')->nullable()->after('es_shiny');
            $table->json('ivs')->nullable()->after('evs');
        });
    }

    public function down(): void
    {
        Schema::table('reclutados', function (Blueprint $table) {
            $table->dropColumn(['evs', 'ivs']);
        });
    }
};
