<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Reclutado;
use Src\Pokemon\Domain\Stats\GeneradorIvs;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Crea un reclutado con IVs/EVs. Compartido por: reclutamiento (recruit),
 * captura shiny de ruta, y eclosión de huevo.
 */
final class CrearReclutado
{
    /**
     * @param  int                                     $userId
     * @param  int                                     $pokemonId
     * @param  StatsValue|null                         $ivs      null = IVs aleatorios
     * @param  bool                                    $esShiny
     * @param  array{total: int, tipos: array<string, int>}  $exp      Experiencia inicial
     */
    public function crear(
        int $userId,
        int $pokemonId,
        ?StatsValue $ivs = null,
        bool $esShiny = false,
        array $exp = ['total' => 1500, 'tipos' => []],
    ): Reclutado {
        $ivs ??= GeneradorIvs::aleatorios();

        $reclutado = Reclutado::create([
            'user_id' => $userId,
            'pokemon_id' => $pokemonId,
            'nombre' => null,
            'exp' => $exp,
            'es_shiny' => $esShiny,
            'obj_equipados' => null,
            'movimientos' => null,
            'ivs' => $ivs,
            'evs' => new StatsValue(0, 0, 0, 0, 0, 0),
        ]);

        ActualizarPokedexJob::dispatch($userId, $pokemonId, 'RECLUTADO');

        return $reclutado;
    }
}
