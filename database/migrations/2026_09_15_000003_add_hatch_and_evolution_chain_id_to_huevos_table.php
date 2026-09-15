<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Snapshot adicionales del huevo (O1/O2): evolution_chain_id permite el
     * bloqueo de "máx. 1 huevo activo por familia" y hatch permite calcular la
     * eclosión con ceil(hatch / 15) sin depender del catálogo.
     *
     * Migración aditiva (patrón bug 23503) para que aplique también sobre BDs
     * que ya tienen la tabla huevos creada.
     */
    public function up(): void
    {
        Schema::table('huevos', function (Blueprint $table) {
            $table->unsignedBigInteger('evolution_chain_id')->nullable()->after('pokemon_id');
            $table->integer('hatch')->nullable()->after('capture_rate');
            $table->index('evolution_chain_id');
        });
    }

    public function down(): void
    {
        Schema::table('huevos', function (Blueprint $table) {
            $table->dropIndex(['evolution_chain_id']);
            $table->dropColumn(['evolution_chain_id', 'hatch']);
        });
    }
};
