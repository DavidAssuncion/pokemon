<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pokemon_habitat', function (Blueprint $table) {
            // Nivel mínimo de jugador para que este spawn esté disponible.
            $table->unsignedInteger('min_level')->nullable()->after('level');
            // true = el nivel lo fijó el admin; el recálculo automático lo respeta.
            $table->boolean('manual')->default(false)->after('min_level');

            $table->index(['habitat_id', 'level', 'min_level'], 'pokemon_habitat_disponibilidad_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pokemon_habitat', function (Blueprint $table) {
            $table->dropIndex('pokemon_habitat_disponibilidad_index');
            $table->dropColumn(['min_level', 'manual']);
        });
    }
};
