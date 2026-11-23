<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Refuerza dos invariantes que solo se comprobaban en aplicación:
     *
     * - Un reclutado no puede tener dos exploraciones activas (regreso NULL).
     * - Un usuario no puede duplicar un favorito global (habitat_id NULL); el
     *   unique previo no lo impedía porque en PostgreSQL los NULL son distintos.
     *
     * Antes de crear los índices se limpian duplicados históricos: se conserva
     * la exploración más reciente y el favorito más antiguo de cada clave.
     */
    public function up(): void
    {
        $this->eliminarExploracionesActivasDuplicadas();
        $this->eliminarFavoritosDuplicados();

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS exploraciones_activas_reclutado_activa_unique
             ON exploraciones_activas (reclutado_id)
             WHERE regreso IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS favoritos_global_unique
             ON favoritos (user_id, reclutado_id)
             WHERE habitat_id IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS favoritos_habitat_unique
             ON favoritos (user_id, reclutado_id, habitat_id)
             WHERE habitat_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS exploraciones_activas_reclutado_activa_unique');
        DB::statement('DROP INDEX IF EXISTS favoritos_global_unique');
        DB::statement('DROP INDEX IF EXISTS favoritos_habitat_unique');
    }

    private function eliminarExploracionesActivasDuplicadas(): void
    {
        $duplicadas = DB::table('exploraciones_activas')
            ->select('reclutado_id', DB::raw('MAX(id) as conservar_id'))
            ->whereNull('regreso')
            ->groupBy('reclutado_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicadas as $duplicada) {
            DB::table('exploraciones_activas')
                ->where('reclutado_id', $duplicada->reclutado_id)
                ->whereNull('regreso')
                ->where('id', '!=', $duplicada->conservar_id)
                ->delete();
        }
    }

    private function eliminarFavoritosDuplicados(): void
    {
        $duplicados = DB::table('favoritos')
            ->select('user_id', 'reclutado_id', 'habitat_id', DB::raw('MIN(id) as conservar_id'))
            ->groupBy('user_id', 'reclutado_id', 'habitat_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicados as $duplicado) {
            $query = DB::table('favoritos')
                ->where('user_id', $duplicado->user_id)
                ->where('reclutado_id', $duplicado->reclutado_id)
                ->where('id', '!=', $duplicado->conservar_id);

            if ($duplicado->habitat_id === null) {
                $query->whereNull('habitat_id');
            } else {
                $query->where('habitat_id', $duplicado->habitat_id);
            }

            $query->delete();
        }
    }
};
