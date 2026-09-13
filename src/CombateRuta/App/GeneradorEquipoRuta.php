<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use App\Models\Habitat;
use App\Models\Pokemon;
use Random\Randomizer;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateRuta\Domain\ClasificadorOfensivaDefensiva;
use Src\Exploraciones\Domain\ValueObjects\ColeccionPoolPonderado;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;
use Src\Pokemon\Domain\Stats\DatosStats;

/**
 * Genera el equipo rival de un combate de ruta (5 pokémon salvajes) a partir
 * del pool del hábitat y nivel.
 *
 * - Selección ponderada CON reemplazo por slot (peso = capture_rate/hatch),
 *   reutilizando la fórmula exacta de PoolHabitat::ponderado: cada tirada es
 *   independiente → el rival varía en cada combate (sin semilla).
 * - El aleatorio es inyectable para tests; por defecto usa azar real.
 * - Pool sin especies con peso (capture_rate <= 0) → retorna [].
 * - Clasifica cada pokémon con ClasificadorOfensivaDefensiva (determinista).
 */
final class GeneradorEquipoRuta
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly ClasificadorOfensivaDefensiva $clasificador,
    ) {
    }

    /**
     * @param  int  $nivel  nivel de ruta del hábitat (1-3)
     * @param  int|null  $nivelRival  nivel de stats del rival (nivel del jugador)
     * @param  callable(): float|null  $aleatorio  fuente [0,1) por slot
     * @return list<DatosPokemonBatalla>
     */
    public function generar(int $habitatId, int $nivel, ?int $nivelRival = null, ?callable $aleatorio = null): array
    {
        $pool = $this->poolDelHabitat($habitatId, $nivel);
        if ($pool === []) {
            return [];
        }

        $ponderado = $this->ponderar($pool);
        if ($ponderado->pesoTotal() <= 0) {
            return [];
        }

        $aleatorio ??= $this->aleatorioAzar();

        $seleccionados = [];
        for ($i = 0; $i < 5; $i++) {
            $seleccion = $ponderado->elegirConAleatorio($aleatorio);
            if ($seleccion === null) {
                return [];
            }
            $seleccionados[] = $pool[$seleccion->id];
        }

        $stats = array_map(
            fn (Pokemon $pokemon): DatosStats => $this->mapeador->statsDe($pokemon),
            $seleccionados,
        );
        $posiciones = $this->clasificador->generarFormacion($stats);

        $equipo = [];
        foreach ($seleccionados as $i => $pokemon) {
            $equipo[] = $this->mapeador->desdePokemon(
                pokemon: $pokemon,
                id: "ruta_{$habitatId}_{$nivel}_{$i}",
                nombre: $pokemon->name,
                posicion: $posiciones[$i],
                nivel: $nivelRival,
            );
        }

        return $equipo;
    }

    /**
     * @return array<int, Pokemon>  keyed por id
     */
    private function poolDelHabitat(int $habitatId, int $nivel): array
    {
        $habitat = Habitat::with('pokemon')
            ->with([
                'pokemon' => fn ($q) => $q->wherePivot('level', $nivel)->with('stats', 'types'),
            ])
            ->find($habitatId);

        if ($habitat === null) {
            return [];
        }

        $pool = [];
        foreach ($habitat->pokemon as $pokemon) {
            if ($pokemon->stats->isNotEmpty() && $pokemon->types->isNotEmpty()) {
                $pool[$pokemon->id] = $pokemon;
            }
        }

        return $pool;
    }

    /**
     * Pool ponderado (peso = capture_rate/hatch) de las especies del pool.
     * Reutiliza PoolHabitat::ponderado (hatch nulo/cero → divisor 1;
     * capture_rate <= 0 → excluido).
     *
     * @param  array<int, Pokemon>  $pool
     */
    private function ponderar(array $pool): ColeccionPoolPonderado
    {
        $contratos = [];
        foreach ($pool as $id => $pokemon) {
            $contratos[] = [
                'id' => $id,
                'capture_rate' => $pokemon->capture_rate,
                'hatch' => $pokemon->hatch,
                'tipos' => [],
                'stats' => [],
            ];
        }

        return PoolHabitat::desdeArray($contratos)->ponderado();
    }

    /**
     * Azar real no determinista (mismo hábitat+nivel → rivales distintos).
     *
     * @return callable(): float
     */
    private function aleatorioAzar(): callable
    {
        $randomizer = new Randomizer();

        return static fn (): float => $randomizer->getFloat(0.0, 1.0);
    }
}
