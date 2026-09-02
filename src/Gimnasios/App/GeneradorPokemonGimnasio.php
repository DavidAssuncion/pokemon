<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use App\Models\Pokemon;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Gimnasios\Domain\EvsRangoEntrenador;

/**
 * Construye el equipo rival de un gimnasio a partir del EquipoEtapaGimnasio
 * (vanguardia | retaguardia) definido en el catálogo. Pasa el nivel rival a
 * DatosPokemonBatalla para que BattleStats calcule los stats de combate (HP,
 * ataque, etc.) al nivel correcto.
 *
 * Las posiciones se respetan literalmente del DTO: vanguardia → VANGUARDIA,
 * retaguardia → RETAGUARDIA (no se reclasifica por stats).
 *
 * Los EVs se reparten según los stats base del pokémon: los 2 mejores stats
 * reciben $evPrincipal y los 4 restantes $evResto.
 */
final class GeneradorPokemonGimnasio
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly GeneradorMovimientosTipo $generadorMovimientos,
    ) {
    }

    /**
     * @return list<DatosPokemonBatalla>
     */
    public function generar(EquipoEtapaGimnasio $equipo, int $nivelRival, int $evPrincipal = 0, int $evResto = 0): array
    {
        $equipoRival = [];
        $indice = 0;

        foreach ($this->porPosicion($equipo) as [$posicion, $pokemon]) {
            $statsBase = $this->mapeador->statsDe($pokemon);
            $evs = EvsRangoEntrenador::distribuir($evPrincipal, $evResto, $statsBase);

            $equipoRival[] = new DatosPokemonBatalla(
                id: "gimnasio_rival_{$indice}",
                nombre: $pokemon->name,
                hp: $statsBase['hp'],
                atk: $statsBase['atk'],
                def: $statsBase['def'],
                spAtk: $statsBase['spAtk'],
                spDef: $statsBase['spDef'],
                speed: $statsBase['speed'],
                tipos: $this->mapeador->tiposDe($pokemon),
                posicion: $posicion,
                moves: $this->movimientosDe($this->mapeador->tiposDe($pokemon)),
                shiny: false,
                iconName: '',
                effectKeys: [],
                item: null,
                speciesId: $pokemon->id,
                formSuffix: '',
                nivel: $nivelRival,
                evs: $evs,
            );

            $indice++;
        }

        return $equipoRival;
    }

    /**
     * Recorre vanguardia y retaguardia en orden, cargando cada species_id con
     * su posición. Permite duplicados (cada id del catálogo genera un
     * combatiente); si una especie no existe en BD, se omite.
     *
     * @return list<array{Posicion, Pokemon}>
     */
    private function porPosicion(EquipoEtapaGimnasio $equipo): array
    {
        $resultado = [];

        foreach ([
            [Posicion::VANGUARDIA, $equipo->vanguardia->all()],
            [Posicion::RETAGUARDIA, $equipo->retaguardia->all()],
        ] as [$posicion, $speciesIds]) {
            foreach ($this->cargarPokemons($speciesIds) as $pokemon) {
                $resultado[] = [$posicion, $pokemon];
            }
        }

        return $resultado;
    }

    /**
     * Carga los pokémon de la BD respetando el orden exacto de $speciesIds.
     *
     * @param  list<int>  $speciesIds
     * @return list<Pokemon>
     */
    private function cargarPokemons(array $speciesIds): array
    {
        $ids = array_values(array_filter($speciesIds, static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, Pokemon> $mapa */
        $mapa = Pokemon::query()
            ->with('stats', 'types')
            ->whereIn('species_id', $ids)
            ->get()
            ->keyBy('species_id')
            ->all();

        $resultado = [];
        foreach ($ids as $speciesId) {
            if (isset($mapa[$speciesId])) {
                $resultado[] = $mapa[$speciesId];
            }
        }

        return $resultado;
    }

    /**
     * @param  \Src\Shared\Tipos\TipoPokemon[]  $tipos
     * @return list<MovimientoBatalla>
     */
    private function movimientosDe(array $tipos): array
    {
        $movimientos = [];
        foreach ($this->generadorMovimientos->generar($tipos) as $m) {
            $movimientos[] = new MovimientoBatalla(
                nombre: $m['nombre'],
                potencia: $m['potencia'],
                tipo: $m['tipo'],
                categoria: CategoriaMovimiento::from($m['categoria']),
            );
        }

        return $movimientos;
    }
}
