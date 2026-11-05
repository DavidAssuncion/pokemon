<?php

declare(strict_types=1);

namespace Src\Granjas\Infra\Plantaciones;

use Illuminate\Support\Facades\DB;
use Src\Exploraciones\Domain\Ports\ConsultorPlantaciones;
use Src\Granjas\Domain\ReglasPlantacion;
use Src\Shared\Domain\ValueObjects\MapaFactores;

/**
 * Adaptador Eloquent del puerto `ConsultorPlantaciones`.
 *
 * Cuenta las plantas existentes por baya en las granjas del jugador para el
 * hábitat indicado y aplica `ReglasPlantacion::factorPlantacion()`. Solo
 * cuentan las plantas, no el stock pendiente; otras granjas u hábitats no
 * influyen.
 */
final class ConsultorPlantacionesEloquent implements ConsultorPlantaciones
{
    public function factoresPara(int $userId, int $habitatId): MapaFactores
    {
        $conteos = DB::table('nest_plantings')
            ->join('nests', 'nests.id', '=', 'nest_plantings.nest_id')
            ->join('farms', 'farms.id', '=', 'nests.farm_id')
            ->where('farms.user_id', $userId)
            ->where('farms.habitat_id', $habitatId)
            ->select('nest_plantings.berry_key', DB::raw('COUNT(*) as total'))
            ->groupBy('nest_plantings.berry_key')
            ->pluck('total', 'berry_key');

        $factores = [];

        foreach ($conteos as $clave => $unidades) {
            $factores[(string) $clave] = ReglasPlantacion::factorPlantacion((int) $unidades);
        }

        return MapaFactores::desdeArray($factores);
    }
}
