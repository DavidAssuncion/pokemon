<?php

declare(strict_types=1);

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
        // 1. Add nullable reclutado_id
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->unsignedBigInteger('reclutado_id')->nullable()->after('equipo_id');
        });

        // 2. Backfill: take the first team_member.pokemon_id for each team
        DB::statement(/** @lang PostgreSQL */ '
            UPDATE exploraciones_activas e
            SET reclutado_id = (
                SELECT tm.pokemon_id
                FROM team_members tm
                WHERE tm.team_id = e.equipo_id
                ORDER BY tm.id
                LIMIT 1
            )
            WHERE e.reclutado_id IS NULL
        ');

        // 3. Delete rows that couldn't be backfilled (orphans without team members)
        DB::table('exploraciones_activas')->whereNull('reclutado_id')->delete();

        // 4. Make NOT NULL, add FK and index
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->unsignedBigInteger('reclutado_id')->nullable(false)->change();
            $table->foreign('reclutado_id')->references('id')->on('reclutados')->onDelete('cascade');
            $table->index('reclutado_id');
        });

        // 5. Drop FK and column equipo_id
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->dropForeign(['equipo_id']);
            $table->dropColumn('equipo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Restore equipo_id as nullable FK
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->unsignedBigInteger('equipo_id')->nullable()->after('id');
        });

        // 2. Backfill: find the team_id of the reclutado's team_member
        DB::statement(/** @lang PostgreSQL */ '
            UPDATE exploraciones_activas e
            SET equipo_id = (
                SELECT tm.team_id
                FROM team_members tm
                WHERE tm.pokemon_id = e.reclutado_id
                LIMIT 1
            )
            WHERE e.equipo_id IS NULL
        ');

        // 3. Make NOT NULL (or delete orphans — but we keep nullable for safety)
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->foreign('equipo_id')->references('id')->on('teams')->onDelete('cascade');
            $table->index('equipo_id');
        });

        // 4. Drop FK, index and column reclutado_id
        Schema::table('exploraciones_activas', function (Blueprint $table) {
            $table->dropForeign(['reclutado_id']);
            $table->dropIndex(['reclutado_id']);
            $table->dropColumn('reclutado_id');
        });
    }
};
