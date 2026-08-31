<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\Habitat;
use App\Models\Pokemon;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\Domain\ClasificadorPosicion;
use Src\CombateEntrenadores\Domain\GeneradorFormacion;

/**
 * Genera el equipo de un entrenador a partir del pool de un hábitat y nivel.
 *
 * - Elige aleatoriamente hasta 3 especies del pool (con semilla determinista:
 *   misma fecha + hábitat + nivel + entrenador → mismo equipo durante el día).
 * - Clasifica cada pokémon en defensivo (vanguardia) u ofensivo (retaguardia).
 * - Aplica una formación aleatoria 1/2 o 2/1 respetando la clasificación.
 */
class GeneradorEquipoEntrenador
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly ClasificadorPosicion $clasificador,
        private readonly GeneradorFormacion $generadorFormacion,
    ) {
    }

    /**
     * @return list<DatosPokemonBatalla>
     */
    public function generar(int $habitatId, int $nivel, int $entrenadorIndex, string $fecha): array
    {
        $pool = $this->poolDelHabitat($habitatId, $nivel);
        if ($pool === []) {
            return [];
        }

        $semilla = crc32("{$habitatId}|{$nivel}|{$entrenadorIndex}|{$fecha}");
        $elegidos = $this->elegirEspecies($pool, $semilla);

        $esDefensivo = array_map(
            fn (Pokemon $pokemon): bool => $this->clasificador->esDefensivo($this->mapeador->statsDe($pokemon)),
            $elegidos
        );
        $posiciones = $this->generadorFormacion->generar($esDefensivo);

        $equipo = [];
        foreach ($elegidos as $i => $pokemon) {
            $equipo[] = $this->mapeador->desdePokemon(
                pokemon: $pokemon,
                id: "entrenador_{$habitatId}_{$nivel}_{$entrenadorIndex}_{$i}",
                nombre: $pokemon->name,
                posicion: Posicion::from($posiciones[$i]),
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
     * @param  list<Pokemon>  $pool
     * @return list<Pokemon>
     */
    private function elegirEspecies(array $pool, int $semilla): array
    {
        $randomizer = new Randomizer(new Mt19937($semilla));
        $ids = array_values(array_unique(array_map(
            fn (Pokemon $pokemon): int => (int) $pokemon->id,
            $pool
        )));

        $elegidosIds = $randomizer->shuffleArray($ids);
        $elegidosIds = array_slice($elegidosIds, 0, 3);

        $porId = [];
        foreach ($pool as $pokemon) {
            $porId[(int) $pokemon->id] = $pokemon;
        }

        $elegidos = [];
        foreach ($elegidosIds as $id) {
            $elegidos[] = $porId[$id];
        }

        return $elegidos;
    }
}
