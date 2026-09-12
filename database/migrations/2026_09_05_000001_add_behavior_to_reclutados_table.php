<?php

declare(strict_types=1);

use App\Models\Reclutado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Exploraciones\App\FabricaCapacidadesStats;
use Src\Exploraciones\Domain\RolExploracion;

return new class () extends Migration {
    /**
     * RFC: añade el rol de exploración individual al reclutado (`behavior`),
     * desacoplándolo de `team_members.behavior`.
     *
     * Backfill:
     * 1. Si el reclutado tiene team_members.behavior → se copia.
     * 2. Si no tiene equipo → se calcula con RolExploracion::sugeridoPara()
     *    sobre FabricaCapacidadesStats::desdeReclutado(). Sin datos → COMBATIENTE.
     * Finalmente el column pasa a ser NOT NULL con default 'COMBATIENTE'.
     *
     * Compatible con PostgreSQL y SQLite (el enum se define como check constraint
     * en pgsql y como text+check en sqlite, patrón de team_members.behavior).
     */
    public function up(): void
    {
        Schema::table('reclutados', function (Blueprint $table) {
            $table->string('behavior')->nullable()->after('es_shiny');
        });

        // Backfill 1: copiar de team_members.behavior (si el rol es válido).
        $this->copiarDeTeamMember();

        // Backfill 2: sugerido para reclutados SIN rol aún (sin equipo o equipo sin behavior).
        $this->calcularSugerido();

        // Completar cualquier nulo restante con COMBATIENTE (fallback).
        Reclutado::withoutUserScope()->whereNull('behavior')->update(['behavior' => 'COMBATIENTE']);

        // NOT NULL con default.
        $this->definirColumnaNotnull();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('reclutados', 'behavior')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reclutados DROP CONSTRAINT IF EXISTS reclutados_behavior_check');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        Schema::table('reclutados', function (Blueprint $table) {
            $table->dropColumn('behavior');
        });
    }

    private function copiarDeTeamMember(): void
    {
        $filas = DB::table('team_members')
            ->join('reclutados', 'reclutados.id', '=', 'team_members.pokemon_id')
            ->whereIn('team_members.behavior', ['VANGUARDIA', 'COMBATIENTE', 'RECOLECTOR', 'RASTREADOR'])
            ->select('reclutados.id as reclutado_id', 'team_members.behavior')
            ->get();

        foreach ($filas as $fila) {
            DB::table('reclutados')->where('id', $fila->reclutado_id)->update(['behavior' => $fila->behavior]);
        }
    }

    private function calcularSugerido(): void
    {
        $sinRol = Reclutado::withoutUserScope()
            ->whereNull('behavior')
            ->with('pokemon')
            ->get();

        foreach ($sinRol as $reclutado) {
            $comportamiento = RolExploracion::COMBATIENTE->value;

            if ($reclutado->pokemon !== null && $reclutado->user !== null) {
                $stats = FabricaCapacidadesStats::desdeReclutado($reclutado, $reclutado->user);
                $comportamiento = RolExploracion::sugeridoPara($stats)->value;
            }

            DB::table('reclutados')->where('id', $reclutado->id)->update(['behavior' => $comportamiento]);
        }
    }

    private function definirColumnaNotnull(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE reclutados ALTER COLUMN behavior SET DEFAULT 'COMBATIENTE'");
            DB::statement('ALTER TABLE reclutados ALTER COLUMN behavior SET NOT NULL');
            DB::statement("ALTER TABLE reclutados ADD CONSTRAINT reclutados_behavior_check CHECK (behavior IN ('VANGUARDIA','COMBATIENTE','RECOLECTOR','RASTREADOR'))");
            return;
        }

        // SQLite: rebuild de la tabla con la columna NOT NULL y default.
        Schema::table('reclutados', function (Blueprint $table) {
            $table->string('behavior')->default('COMBATIENTE')->nullable(false)->change();
        });
    }
};
