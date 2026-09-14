<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\Pokemon;
use App\Models\Team;
use Src\Battle\Domain\DatosPokemonBatalla;
use Src\Battle\Domain\Posicion;
use Src\Shared\Domain\ClasificadorOfensivaDefensiva;

/**
 * Construye el equipo de batalla del jugador a partir de un Team de la BD,
 * aplicando la formación de combate (vanguardia/retaguardia).
 *
 * Prioridad de posición por slot: popup del modal > formación persistida en
 * el Team (columna formacion) > clasificación automática por stats con
 * ClasificadorOfensivaDefensiva (ofensiva = atk + speed > defensiva =
 * def + spDef + hp → retaguardia; empate → vanguardia).
 */
class ConstruirEquipoJugador
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly ClasificadorOfensivaDefensiva $clasificador,
    ) {
    }

    /**
     * @param  array<int, string>  $formacion  posición por slot del popup: [slot => 'vanguardia'|'retaguardia']
     * @param  int|null  $nivel  nivel del jugador (para escalar stats en gimnasios); null = stats base (entrenadores hábitat)
     * @return list<DatosPokemonBatalla>
     */
    public function desdeEquipo(Team $equipo, array $formacion, ?int $nivel = null): array
    {
        $combatientes = [];

        $miembros = $equipo->members->sortBy('slot')->values();

        /** @var array<int, string> $formacionPersistida */
        $formacionPersistida = $equipo->getAttribute('formacion') ?? [];

        foreach ($miembros as $miembro) {
            $reclutado = $miembro->reclutado;
            $pokemon = $reclutado?->pokemon;

            if ($reclutado === null || $pokemon === null) {
                continue;
            }

            $slot = (int) $miembro->slot;
            $posicion = $this->posicionPara($slot, $formacion, $formacionPersistida, $pokemon);

            $combatientes[] = $this->mapeador->desdePokemon(
                pokemon: $pokemon,
                id: "jugador_{$reclutado->id}",
                nombre: $reclutado->nombre ?: $pokemon->name,
                posicion: $posicion,
                shiny: (bool) $reclutado->es_shiny,
                nivel: $nivel,
            );
        }

        return $combatientes;
    }

    /**
     * Posición de un slot: la del popup si existe; si no, la persistida en el
     * Team; si no, la clasificación por stats (se usan stats base: el ratio
     * ofensiva/defensiva es el mismo a cualquier nivel).
     *
     * @param  array<int, string>  $formacion
     * @param  array<int, string>  $formacionPersistida
     */
    private function posicionPara(int $slot, array $formacion, array $formacionPersistida, Pokemon $pokemon): Posicion
    {
        $elegida = $formacion[$slot] ?? $formacionPersistida[$slot] ?? null;

        if ($elegida !== null && in_array($elegida, ['vanguardia', 'retaguardia'], true)) {
            return Posicion::from($elegida);
        }

        $stats = $this->mapeador->statsDe($pokemon);

        return $this->clasificador->esOfensivo($stats)
            ? Posicion::RETAGUARDIA
            : Posicion::VANGUARDIA;
    }
}
