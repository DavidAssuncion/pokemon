<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * D7/RF-12: enum team_members.behavior = VANGUARDIA, COMBATIENTE, RECOLECTOR,
     * RASTREADOR (quitar SOPORTE, añadir RASTREADOR); migrar datos SOPORTE → RASTREADOR.
     *
     * La constraint check generada por Laravel (`team_members_behavior_check`) impide
     * escribir 'RASTREADOR' ANTES de alterarla, y un `change()` en pgsql no puede
     * re-añadir la misma constraint (ya existe). Por eso el orden es:
     *
     *   pgsql:  drop constraint → UPDATE SOPORTE→RASTREADOR → add constraint nueva.
     *   sqlite: PRAGMA ignore_check_constraints (la check vieja bloquearía el UPDATE)
     *           → UPDATE → `change()` (Laravel 12 rebuilds la tabla con la nueva
     *           definición, copiando los datos ya normalizados).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE team_members DROP CONSTRAINT IF EXISTS team_members_behavior_check');
            DB::table('team_members')->where('behavior', 'SOPORTE')->update(['behavior' => 'RASTREADOR']);
            DB::statement("ALTER TABLE team_members ADD CONSTRAINT team_members_behavior_check CHECK (behavior IN ('VANGUARDIA','COMBATIENTE','RECOLECTOR','RASTREADOR'))");
        } else {
            DB::statement('PRAGMA ignore_check_constraints = ON');
            try {
                DB::table('team_members')->where('behavior', 'SOPORTE')->update(['behavior' => 'RASTREADOR']);
            } finally {
                DB::statement('PRAGMA ignore_check_constraints = OFF');
            }

            Schema::table('team_members', function (Blueprint $table) {
                $table->enum('behavior', ['VANGUARDIA', 'COMBATIENTE', 'RECOLECTOR', 'RASTREADOR'])->default('COMBATIENTE')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE team_members DROP CONSTRAINT IF EXISTS team_members_behavior_check');
            DB::table('team_members')->where('behavior', 'RASTREADOR')->update(['behavior' => 'SOPORTE']);
            DB::statement("ALTER TABLE team_members ADD CONSTRAINT team_members_behavior_check CHECK (behavior IN ('VANGUARDIA','COMBATIENTE','RECOLECTOR','SOPORTE'))");
        } else {
            DB::statement('PRAGMA ignore_check_constraints = ON');
            try {
                DB::table('team_members')->where('behavior', 'RASTREADOR')->update(['behavior' => 'SOPORTE']);
            } finally {
                DB::statement('PRAGMA ignore_check_constraints = OFF');
            }

            Schema::table('team_members', function (Blueprint $table) {
                $table->enum('behavior', ['VANGUARDIA', 'COMBATIENTE', 'RECOLECTOR', 'SOPORTE'])->default('COMBATIENTE')->change();
            });
        }
    }
};
