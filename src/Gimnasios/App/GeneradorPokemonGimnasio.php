<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use App\Models\Pokemon;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\ClasificadorPosicion;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;

/**
 * Construye el equipo rival de un gimnasio a partir de species_id definidos en
 * el catálogo. Pasa el nivel rival a DatosPokemonBatalla para que BattleStats
 * calcule los stats de combate (HP, ataque, etc.) al nivel correcto.
 */
final class GeneradorPokemonGimnasio
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly ClasificadorPosicion $clasificador,
        private readonly GeneradorMovimientosTipo $generadorMovimientos,
    ) {
    }

    /**
     * @param  list<int>  $speciesIds
     * @return list<DatosPokemonBatalla>
     */
    public function generar(array $speciesIds, int $nivelRival): array
    {
        $pokemons = $this->cargarPokemons($speciesIds);

        $equipo = [];
        foreach ($pokemons as $i => $pokemon) {
            $statsBase = $this->mapeador->statsDe($pokemon);

            $equipo[] = new DatosPokemonBatalla(
                id: "gimnasio_rival_{$i}",
                nombre: $pokemon->name,
                hp: $statsBase['hp'],
                atk: $statsBase['atk'],
                def: $statsBase['def'],
                spAtk: $statsBase['spAtk'],
                spDef: $statsBase['spDef'],
                speed: $statsBase['speed'],
                tipos: $this->mapeador->tiposDe($pokemon),
                posicion: $this->clasificador->esDefensivo($statsBase)
                    ? Posicion::VANGUARDIA
                    : Posicion::RETAGUARDIA,
                moves: $this->movimientosDe($this->mapeador->tiposDe($pokemon)),
                shiny: false,
                iconName: '',
                effectKeys: [],
                item: null,
                speciesId: $pokemon->id,
                formSuffix: '',
                nivel: $nivelRival,
            );
        }

        return $equipo;
    }

    /**
     * @param  list<int>  $speciesIds
     * @return list<Pokemon>
     */
    private function cargarPokemons(array $speciesIds): array
    {
        $ids = array_values(array_unique(array_filter($speciesIds, static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        return Pokemon::query()
            ->with('stats', 'types')
            ->whereIn('species_id', $ids)
            ->get()
            ->sortBy(fn (Pokemon $pokemon): int => array_search($pokemon->species_id, $ids, true))
            ->values()
            ->all();
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
