<?php

declare(strict_types=1);

use App\Support\LegacyUserMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pokedex', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        $legacyUserId = LegacyUserMigrator::ensureLegacyUserId();
        if ($legacyUserId !== null) {
            DB::table('pokedex')->whereNull('user_id')->update(['user_id' => $legacyUserId]);
        }

        Schema::table('pokedex', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            // La pokédex pasa a ser por jugador: unique(user_id, pokemon_id).
            $table->dropUnique(['pokemon_id']);
            $table->unique(['user_id', 'pokemon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pokedex', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'pokemon_id']);
            $table->unique('pokemon_id');
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
