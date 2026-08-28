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
        Schema::table('pokemon_stats', function (Blueprint $table) {
            $table->unique(['pokemon_id', 'stat']);
        });

        Schema::table('pokemon_types', function (Blueprint $table) {
            $table->unique(['pokemon_id', 'slot']);
        });

        Schema::table('pokemon_evolution', function (Blueprint $table) {
            $table->unique('evolved_species_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pokemon_evolution', function (Blueprint $table) {
            $table->dropUnique(['evolved_species_id']);
        });

        Schema::table('pokemon_types', function (Blueprint $table) {
            $table->dropUnique(['pokemon_id', 'slot']);
        });

        Schema::table('pokemon_stats', function (Blueprint $table) {
            $table->dropUnique(['pokemon_id', 'stat']);
        });
    }
};
