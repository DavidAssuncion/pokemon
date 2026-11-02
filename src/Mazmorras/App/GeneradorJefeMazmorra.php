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
 * Construye el jefe de un piso de mazmorra: UN solo combatiente (3v1) cuyo
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
            atk: $stats->atk * ReglasMazmorra::MULTIPLICADOR_STATS_JEFE,
            def: $stats->def * ReglasMazmorra::MULTIPLICADOR_STATS_JEFE,
            spAtk: $stats->spAtk * ReglasMazmorra::MULTIPLICADOR_STATS_JEFE,
            spDef: $stats->spDef * ReglasMazmorra::MULTIPLICADOR_STATS_JEFE,
            speed: $stats->speed * ReglasMazmorra::MULTIPLICADOR_STATS_JEFE,
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
            bonusInicialStats: ReglasMazmorra::BONUS_INICIAL_STATS_JEFE,
            fasesJefe: [
                ['umbral' => 0.75, 'nombre' => 'Fase 2', 'bonificador_dano' => 1.15, 'bonificador_velocidad' => 1.10],
                ['umbral' => 0.50, 'nombre' => 'Fase 3', 'bonificador_dano' => 1.30, 'bonificador_velocidad' => 1.20],
                ['umbral' => 0.25, 'nombre' => 'Enrage', 'bonificador_dano' => 1.50, 'bonificador_velocidad' => 1.30, 'enrage' => true],
            ],
            puntosDebiles: [
                ['id' => 'nucleo', 'nombre' => 'Núcleo', 'fraccion_hp' => 0.18],
                ['id' => 'coraza', 'nombre' => 'Coraza', 'fraccion_hp' => 0.22],
            ],
        );
    }
}
