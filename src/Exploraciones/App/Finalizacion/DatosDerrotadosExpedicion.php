<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Finalizacion;

use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;

/**
 * Datos de los pokémon derrotados en una expedición, ya cargados, expandidos
 * y normalizados para el cálculo de recompensas.
 */
final readonly class DatosDerrotadosExpedicion
{
    /**
     * @param  list<int>  $idsDerrotados
     * @param  Collection<int, Pokemon>  $pokemons
     * @param  array<int, Collection<int, Pokemon>>  $miembrosPorCadena
     * @param  array<int, array<int, int>>  $etapasPorCadena
     * @param  BaseCollection<int, PokemonDerrotado>  $derrotados
     */
    public function __construct(
        public array $idsDerrotados,
        public Collection $pokemons,
        public array $miembrosPorCadena,
        public array $etapasPorCadena,
        public BaseCollection $derrotados,
    ) {
    }
}
