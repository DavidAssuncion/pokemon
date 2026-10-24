<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\Pokemon;
use App\Models\Team;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\Domain\ValueObjects\FormacionEquipo;
use Src\Shared\Domain\ClasificadorOfensivaDefensiva;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

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
     * Construye los datos de batalla del equipo y valida que aporte
     * exactamente 3 combatientes (regla estándar de combate).
     *
     * @param  FormacionEquipo  $formacion  posición por slot elegida en el popup
     * @param  int|null  $nivel  nivel del jugador (para escalar stats en gimnasios); null = stats base (entrenadores hábitat)
     *
     * @throws ViolacionReglaNegocio si el equipo no aporta exactamente 3 combatientes
     */
    public function desdeEquipo(Team $equipo, FormacionEquipo $formacion, ?int $nivel = null): DatosPokemonBatallaCollection
    {
        $combatientes = [];

        $miembros = $equipo->members->sortBy('slot')->values();

        /** @var array<int, string> $formacionPersistida */
        $formacionPersistida = $equipo->getAttribute('formacion') ?? [];
        $formacionPersistidaVo = FormacionEquipo::desde($formacionPersistida);

        foreach ($miembros as $miembro) {
            $reclutado = $miembro->reclutado;
            $pokemon = $reclutado?->pokemon;

            if ($reclutado === null || $pokemon === null) {
                continue;
            }

            $slot = (int) $miembro->slot;
            $posicion = $this->posicionPara($slot, $formacion, $formacionPersistidaVo, $pokemon);

            /** @var array<int, string> $posturasPersistidas */
            $posturasPersistidas = $equipo->getAttribute('posturas') ?? [];
            $postura = PosturaCombate::tryFrom($posturasPersistidas[$slot] ?? '')
                ?? PosturaCombate::ATACAR;

            $items = $reclutado->obj_equipados;
            $item = is_array($items) ? (array_values($items)[0] ?? null) : null;

            $combatientes[] = $this->mapeador->desdePokemon(
                pokemon: $pokemon,
                id: "jugador_{$reclutado->id}",
                nombre: $reclutado->nombre ?: $pokemon->name,
                posicion: $posicion,
                shiny: (bool) $reclutado->es_shiny,
                item: is_string($item) ? $item : null,
                nivel: $nivel,
                evs: $reclutado->evs,
                ivs: $reclutado->ivs,
                postura: $postura,
                movimientosSeleccionados: is_array($reclutado->movimientos) ? $reclutado->movimientos : null,
                habilidadActivaId: $reclutado->habilidad_activa_id,
            );
        }

        if (count($combatientes) !== 3) {
            throw new ViolacionReglaNegocio('El equipo debe tener exactamente 3 miembros para combatir.');
        }

        return new DatosPokemonBatallaCollection($combatientes);
    }

    /**
     * Posición de un slot: la del popup si existe; si no, la persistida en el
     * Team; si no, la clasificación por stats (se usan stats base: el ratio
     * ofensiva/defensiva es el mismo a cualquier nivel).
     */
    private function posicionPara(int $slot, FormacionEquipo $formacion, FormacionEquipo $formacionPersistida, Pokemon $pokemon): Posicion
    {
        $elegida = $formacion->para($slot) ?? $formacionPersistida->para($slot);

        if ($elegida !== null) {
            return $elegida;
        }

        $stats = $this->mapeador->statsDe($pokemon);

        return $this->clasificador->esOfensivo($stats)
            ? Posicion::RETAGUARDIA
            : Posicion::VANGUARDIA;
    }
}
