<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use App\Models\Pokemon;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\Mazmorras\Domain\ReglasMazmorra;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Construye el jefe de un piso de mazmorra: UN solo combatiente (3v1) con
 * vida ×10 sobre la vida base de especie, estadísticas base ×1 y un efecto
 * permanente ×3 (+200 %) no-etapa sobre ataque, defensa, ataque especial,
 * defensa especial y velocidad al nivel del piso (por defecto el del jugador).
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
            ->with('stats', 'types', 'abilities')
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
            ivs: StatsValue::ceros(),
        );

        return new DatosPokemonBatalla(
            id: $datos->id,
            nombre: $datos->nombre,
            hp: $stats->hp * ReglasMazmorra::MULTIPLICADOR_HP_JEFE,
            atk: $stats->atk,
            def: $stats->def,
            spAtk: $stats->spAtk,
            spDef: $stats->spDef,
            speed: $stats->speed,
            tipos: $datos->tipos,
            posicion: $datos->posicion,
            moves: $datos->moves,
            shiny: false,
            iconName: '',
            effectKeys: $datos->effectKeys,
            item: $datos->item,
            speciesId: $pokemon->id,
            formSuffix: '',
            nivel: $datos->nivel,
            evs: $datos->evs,
            ivs: $datos->ivs,
            peso: $datos->peso,
            esJefe: true,
            multiplicadorStatsJefe: ReglasMazmorra::MULTIPLICADOR_STATS_EFECTIVO_JEFE,
            fasesJefe: [
                ['umbral' => 0.75, 'nombre' => 'Fase 2', 'bonificador_dano' => 1.15, 'bonificador_velocidad' => 1.10],
                ['umbral' => 0.50, 'nombre' => 'Fase 3', 'bonificador_dano' => 1.30, 'bonificador_velocidad' => 1.20],
                ['umbral' => 0.25, 'nombre' => 'Enrage', 'bonificador_dano' => 1.50, 'bonificador_velocidad' => 1.30, 'enrage' => true],
            ],
        );
    }
}
