<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\Habitat;
use App\Models\Pokemon;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\CombateRuta\Domain\ClasificadorOfensivaDefensiva;
use Src\Exploraciones\Domain\ValueObjects\ColeccionPoolPonderado;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;
use Src\Pokemon\Domain\Stats\DatosStats;

/**
 * Genera el equipo de un entrenador a partir del pool de un hábitat y nivel.
 *
 * - Con 5 especies únicas o más en el pool, elige 5 únicas (semilla
 *   determinista: misma fecha + hábitat + nivel + entrenador → mismo equipo
 *   durante el día).
 * - Con menos de 5 especies únicas, repite CON reemplazo ponderado por
 *   capture_rate/hatch (fórmula de PoolHabitat::ponderado) hasta completar 5;
 *   el aleatorio es inyectable para tests y determinista por semilla por defecto.
 * - Clasifica cada pokémon en defensivo (vanguardia) u ofensivo (retaguardia)
 *   con ClasificadorOfensivaDefensiva (fórmula determinista, sin random).
 */
class GeneradorEquipoEntrenador
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly ClasificadorOfensivaDefensiva $clasificador,
    ) {
    }

    /**
     * @param  callable(): float  $aleatorio  Fuente aleatoria inyectable para la
     *                                         repetición ponderada (tests lo fijan).
     * @return list<DatosPokemonBatalla>
     */
    public function generar(int $habitatId, int $nivel, int $entrenadorIndex, string $fecha, ?int $nivelRival = null, ?callable $aleatorio = null): array
    {
        $pool = $this->poolDelHabitat($habitatId, $nivel);
        if ($pool === []) {
            return [];
        }

        $semilla = crc32("{$habitatId}|{$nivel}|{$entrenadorIndex}|{$fecha}");
        $elegidos = $this->elegirEspecies($pool, $semilla, $aleatorio);

        $stats = array_map(
            fn (Pokemon $pokemon): DatosStats => $this->mapeador->statsDe($pokemon),
            $elegidos
        );
        $posiciones = $this->clasificador->generarFormacion($stats);

        $equipo = [];
        foreach ($elegidos as $i => $pokemon) {
            $equipo[] = $this->mapeador->desdePokemon(
                pokemon: $pokemon,
                id: "entrenador_{$habitatId}_{$nivel}_{$entrenadorIndex}_{$i}",
                nombre: $pokemon->name,
                posicion: $posiciones[$i],
                nivel: $nivelRival,
            );
        }

        return $equipo;
    }

    /**
     * @return list<Pokemon>
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

        return $habitat->pokemon
            ->filter(fn (Pokemon $pokemon): bool => $pokemon->stats->isNotEmpty() && $pokemon->types->isNotEmpty())
            ->values()
            ->all();
    }

    /**
     * Elige 5 especies: únicas si el pool las tiene (en orden de la semilla);
     * si no, repite CON reemplazo ponderado por capture_rate/hatch hasta 5.
     *
     * @param  list<Pokemon>  $pool
     * @param  callable(): float  $aleatorio
     * @return list<Pokemon>
     */
    private function elegirEspecies(array $pool, int $semilla, ?callable $aleatorio = null): array
    {
        $ids = array_values(array_unique(array_map(
            fn (Pokemon $pokemon): int => $pokemon->id,
            $pool
        )));
        $porId = $this->indexarPorId($pool);

        if (count($ids) >= 5) {
            $randomizer = new Randomizer(new Mt19937($semilla));
            $idsOrdenados = $randomizer->shuffleArray($ids);

            $elegidos = [];
            for ($i = 0; $i < 5; $i++) {
                $elegidos[] = $porId[$idsOrdenados[$i]];
            }

            return $elegidos;
        }

        // Pool con menos de 5 especies únicas: repetición ponderada hasta 5.
        $aleatorio ??= $this->aleatorioDeterminista($semilla);
        $ponderado = $this->ponderadoDelPool($porId);

        $elegidos = [];
        for ($i = 0; $i < 5; $i++) {
            $seleccion = $ponderado->elegirConAleatorio($aleatorio);
            if ($seleccion === null) {
                return [];
            }
            $elegidos[] = $porId[$seleccion->id];
        }

        return $elegidos;
    }

    /**
     * Aleatorio determinista por semilla (producción): la repetición ponderada
     * del rival de entrenador sigue siendo estable durante el mismo día.
     *
     * @return callable(): float
     */
    private function aleatorioDeterminista(int $semilla): callable
    {
        $randomizer = new Randomizer(new Mt19937($semilla));

        return static fn (): float => $randomizer->getFloat(0.0, 1.0);
    }

    /**
     * Pool ponderado (peso = capture_rate/hatch) del conjunto de especies del
     * hábitat para el nivel. Reutiliza la fórmula exacta de PoolHabitat::ponderado
     * (hatch nulo/cero → divisor 1; capture_rate <= 0 → excluido).
     *
     * @param  array<int, Pokemon>  $porId
     */
    private function ponderadoDelPool(array $porId): ColeccionPoolPonderado
    {
        $contratos = [];
        foreach ($porId as $id => $pokemon) {
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
     * @param  list<Pokemon>  $pool
     * @return array<int, Pokemon>
     */
    private function indexarPorId(array $pool): array
    {
        $porId = [];
        foreach ($pool as $pokemon) {
            $porId[$pokemon->id] = $pokemon;
        }

        return $porId;
    }
}
