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
        // Postgres NO indexa las columnas de FK automáticamente (verificado: ninguno
        // de estos índices existe hoy; team_members.pokemon_id ya es unique → no se toca).
        Schema::table('pokemon', function (Blueprint $table) {
            $table->index('evolution_chain_id');
        });

        Schema::table('pokemon_habitat', function (Blueprint $table) {
            $table->index(['habitat_id', 'level']);
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->index('team_id');
        });

        Schema::table('habitats', function (Blueprint $table) {
            $table->index('province_id');
        });

        Schema::table('pokemon_evolution', function (Blueprint $table) {
            $table->index('evolves_from_species_id');
        });

        Schema::table('reclutados', function (Blueprint $table) {
            $table->index('pokemon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pokemon', function (Blueprint $table) {
            $table->dropIndex(['evolution_chain_id']);
        });

        Schema::table('pokemon_habitat', function (Blueprint $table) {
            $table->dropIndex(['habitat_id', 'level']);
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropIndex(['team_id']);
        });

        Schema::table('habitats', function (Blueprint $table) {
            $table->dropIndex(['province_id']);
        });

        Schema::table('pokemon_evolution', function (Blueprint $table) {
            $table->dropIndex(['evolves_from_species_id']);
        });

        Schema::table('reclutados', function (Blueprint $table) {
            $table->dropIndex(['pokemon_id']);
        });
    }
};
