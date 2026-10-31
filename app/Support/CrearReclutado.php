<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Casts\ExpReclutado;
use App\Models\Reclutado;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Reclutamiento\Domain\DataTransferObjects\DatosNuevoReclutado;

/**
 * Crea un reclutado con IVs/EVs. Compartido por: reclutamiento (recruit),
 * captura shiny de ruta, y eclosión de huevo.
 */
final class CrearReclutado
{
    public function crear(DatosNuevoReclutado $datos, ?StatsValue $evs = null): Reclutado
    {
        $reclutado = new Reclutado();
        $reclutado->user_id = $datos->userId;
        $reclutado->pokemon_id = $datos->pokemonId;
        $reclutado->nombre = null;
        $reclutado->exp = new ExpReclutado(total: 1500);
        $reclutado->es_shiny = $datos->esShiny;
        $reclutado->obj_equipados = null;
        $reclutado->movimientos = null;
        $reclutado->ivs = $datos->ivs ?? GeneradorIvs::aleatorios();
        $reclutado->evs = $evs ?? StatsValue::ceros();
        $reclutado->save();

        ActualizarPokedexJob::dispatch($datos->userId, $datos->pokemonId, 'RECLUTADO');

        return $reclutado;
    }
}
