<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\Team;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\Domain\ClasificadorPosicion;

/**
 * Construye el equipo de batalla del jugador a partir de un Team de la BD,
 * aplicando la formación (vanguardia/retaguardia) elegida en el popup.
 */
class ConstruirEquipoJugador
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly ClasificadorPosicion $clasificador,
    ) {
    }

    /**
     * @param  array<int, string>  $formacion  posición por slot: [slot => 'vanguardia'|'retaguardia']
     * @return list<DatosPokemonBatalla>
     */
    public function desdeEquipo(Team $equipo, array $formacion): array
    {
        $combatientes = [];

        $miembros = $equipo->members->sortBy('slot')->values();

        foreach ($miembros as $miembro) {
            $reclutado = $miembro->reclutado;
            $pokemon = $reclutado?->pokemon;

            if ($reclutado === null || $pokemon === null) {
                continue;
            }

            $slot = (int) $miembro->slot;
            $posicion = $this->posicionPara($slot, $formacion, $pokemon);

            $combatientes[] = $this->mapeador->desdePokemon(
                pokemon: $pokemon,
                id: "jugador_{$reclutado->id}",
                nombre: $reclutado->nombre ?: $pokemon->name,
                posicion: $posicion,
                shiny: (bool) $reclutado->es_shiny,
            );
        }

        return $combatientes;
    }

    /**
     * Posición para un slot: la elegida por el usuario si existe; si no, la
     * clasificación por stats (defensivo → vanguardia, ofensivo → retaguardia).
     */
    private function posicionPara(int $slot, array $formacion, mixed $pokemon): Posicion
    {
        $elegida = $formacion[$slot] ?? null;

        if ($elegida !== null && in_array($elegida, ['vanguardia', 'retaguardia'], true)) {
            return Posicion::from($elegida);
        }

        return $this->clasificador->esDefensivo($this->mapeador->statsDe($pokemon))
            ? Posicion::VANGUARDIA
            : Posicion::RETAGUARDIA;
    }
}
