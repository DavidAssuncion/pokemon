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
        Schema::table('pokemon_moves', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')
                ->nullable()
                ->after('source');

            $table->index(['pokemon_id', 'source', 'level'], 'pokemon_moves_source_level_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pokemon_moves', function (Blueprint $table) {
            $table->dropIndex('pokemon_moves_source_level_index');
            $table->dropColumn('level');
        });
    }
};
