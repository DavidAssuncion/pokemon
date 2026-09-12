<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use App\Models\Pokemon;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;

/**
 * Construye el jefe de un piso de mazmorra: UN solo combatiente (5v1) cuyo
 * pokémon tiene los stats base multiplicados por 10 (jefe ×10) al nivel del
 * piso (por defecto el del jugador).
 */
final class GeneradorJefeMazmorra
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
    ) {
    }

    public function generar(int $speciesId, int $nivel): ?DatosPokemonBatalla
    {
        $pokemon = Pokemon::query()
            ->with('stats', 'types')
            ->where('species_id', $speciesId)
            ->first();

        if ($pokemon === null) {
            return null;
        }

        $stats = $this->mapeador->statsDe($pokemon);

        $datos = $this->mapeador->desdePokemon(
            pokemon: $pokemon,
            id: 'mazmorra_jefe',
            nombre: $pokemon->name,
            posicion: Posicion::VANGUARDIA,
            shiny: false,
            nivel: $nivel,
        );

        return new DatosPokemonBatalla(
            id: $datos->id,
            nombre: $datos->nombre,
            hp: $stats->hp * 10,
            atk: $stats->atk * 10,
            def: $stats->def * 10,
            spAtk: $stats->spAtk * 10,
            spDef: $stats->spDef * 10,
            speed: $stats->speed * 10,
            tipos: $datos->tipos,
            posicion: $datos->posicion,
            moves: $datos->moves,
            shiny: false,
            iconName: '',
            effectKeys: [],
            item: null,
            speciesId: $pokemon->id,
            formSuffix: '',
            nivel: $datos->nivel,
            evs: $datos->evs,
        );
    }
}
