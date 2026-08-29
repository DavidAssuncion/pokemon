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
        // Columna nullable primero para poder hacer el backfill condicional.
        Schema::table('reclutados', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        // Backfill condicional: solo si hay filas que migrar se crea el usuario legacy.
        $legacyUserId = LegacyUserMigrator::ensureLegacyUserId();
        if ($legacyUserId !== null) {
            DB::table('reclutados')->whereNull('user_id')->update(['user_id' => $legacyUserId]);
        }

        Schema::table('reclutados', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });

        // exp pasa de json a jsonb (shape documentado {"total": int, "tipos": {...}}).
        Schema::table('reclutados', function (Blueprint $table) {
            $table->jsonb('exp')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reclutados', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('reclutados', function (Blueprint $table) {
            $table->json('exp')->nullable()->change();
        });
    }
};
