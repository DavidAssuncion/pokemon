<?php

declare(strict_types=1);

namespace Src\Equipos\Infra\Repositorios;

use Illuminate\Support\Facades\DB;
use Src\Equipos\Domain\Ports\PiezasEquipadas;
use Src\Shared\Collections\StringCollection;

/**
 * Adaptador de lectura del equipamiento de piezas.
 *
 * Una sola consulta sobre `recruit_gear` con join a `reclutados` y
 * `user_id` explícito: no depende del scope global del modelo (inactivo
 * fuera de HTTP).
 */
final class PiezasEquipadasEloquent implements PiezasEquipadas
{
    public function clavesDe(int $userId): StringCollection
    {
        $claves = DB::table('recruit_gear')
            ->join('reclutados', 'reclutados.id', '=', 'recruit_gear.reclutado_id')
            ->where('reclutados.user_id', $userId)
            ->distinct()
            ->pluck('recruit_gear.item_key');

        return new StringCollection(
            $claves->map(static fn (mixed $clave): string => (string) $clave)->all(),
        );
    }
}
