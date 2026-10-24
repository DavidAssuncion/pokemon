<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use App\Models\Pokemon;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;

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
            effectKeys: $datos->effectKeys,
            item: $datos->item,
            speciesId: $pokemon->id,
            formSuffix: '',
            nivel: $datos->nivel,
            evs: $datos->evs,
            peso: $datos->peso,
            esJefe: true,
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
