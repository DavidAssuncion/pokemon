<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use App\Models\Team;
use App\Support\CargarMapaSendaJugador;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\Domain\ValueObjects\FormacionEquipo;
use Src\Equipos\App\Piezas\ObtenerPiezas;
use Src\Equipos\Domain\Collections\EquipamientosPorReclutado;
use Src\Forja\Domain\Piezas\AgregadorBonosPiezas;
use Src\Forja\Domain\Piezas\Collections\DefinicionesPiezaCollection;
use Src\Forja\Domain\Ports\PiezasPorEspecie;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\ClasificadorOfensivaDefensiva;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\ReglasEquipo;
use Src\Shared\Domain\Sendas\MapaSendaJugador;

/**
 * Construye el equipo de batalla del jugador a partir de un Team de la BD,
 * aplicando la formación de combate (vanguardia/retaguardia).
 *
 * Prioridad de posición por slot: popup del modal > formación persistida en
 * el Team (columna formacion) > clasificación automática por stats con
 * ClasificadorOfensivaDefensiva (ofensiva = atk + speed > defensiva =
 * def + spDef + hp → retaguardia; empate → vanguardia).
 *
 * Las piezas forjadas equipadas (hasta 3 por reclutado) se colapsan en el
 * mapeo: sus bonos se suman a los stats base y sus pasivas se añaden a
 * `effectKeys`. La clasificación de formación sigue usando stats base.
 */
class ConstruirEquipoJugador
{
    public function __construct(
        private readonly MapeadorPokemonBatalla $mapeador,
        private readonly ClasificadorOfensivaDefensiva $clasificador,
        private readonly ?ObtenerPiezas $obtenerPiezas = null,
        private readonly ?PiezasPorEspecie $piezasPorEspecie = null,
        private readonly AgregadorBonosPiezas $agregadorBonos = new AgregadorBonosPiezas(),
    ) {
    }

    /**
     * Construye los datos de batalla del equipo y valida que aporte
     * exactamente 3 combatientes (regla estándar de combate).
     *
     * Antes de construir los combatientes comprueba que ningún miembro tenga
     * una exploración activa (regreso IS NULL): el equipo sigue siendo
     * editable, pero no puede entrar en combate hasta que el miembro vuelva.
     *
     * @param  FormacionEquipo  $formacion  posición por slot elegida en el popup
     * @param  int|null  $nivel  nivel del jugador (para escalar stats en gimnasios); null = stats base (entrenadores hábitat)
     * @param  MapaSendaJugador|null  $senda  mapa de Sendas; null = se carga el del dueño del equipo
     *
     * @throws ViolacionReglaNegocio si algún miembro está en exploración activa
     *                               o si el equipo no aporta exactamente 3 combatientes
     */
    public function desdeEquipo(
        Team $equipo,
        FormacionEquipo $formacion,
        ?int $nivel = null,
        ?MapaSendaJugador $senda = null,
    ): DatosPokemonBatallaCollection {
        $this->asegurarSinExploracionActiva($equipo);

        $senda ??= app(CargarMapaSendaJugador::class)->para($equipo->user_id);

        $combatientes = [];

        $miembros = $equipo->members->sortBy('slot')->values();

        $reclutadoIds = new IntCollection();
        foreach ($miembros as $miembro) {
            $reclutadoId = $miembro->reclutado?->id;

            if ($reclutadoId !== null) {
                $reclutadoIds->add($reclutadoId);
            }
        }

        $equipamientos = $this->obtenerPiezas?->paraReclutados($equipo->user_id, $reclutadoIds)
            ?? EquipamientosPorReclutado::vacio();
        $definiciones = $this->definicionesDe($equipamientos, $reclutadoIds);

        /** @var array<int, string> $formacionPersistida */
        $formacionPersistida = $equipo->getAttribute('formacion') ?? [];
        $formacionPersistidaVo = FormacionEquipo::desde($formacionPersistida);

        foreach ($miembros as $miembro) {
            $reclutado = $miembro->reclutado;
            $pokemon = $reclutado?->pokemon;

            if ($reclutado === null || $pokemon === null) {
                continue;
            }

            $slot = $miembro->slot;
            $posicion = $this->posicionPara($slot, $formacion, $formacionPersistidaVo, $pokemon);

            /** @var array<int, string> $posturasPersistidas */
            $posturasPersistidas = $equipo->getAttribute('posturas') ?? [];
            $postura = PosturaCombate::tryFrom($posturasPersistidas[$slot] ?? '')
                ?? PosturaCombate::ATACAR;

            $items = $reclutado->obj_equipados;
            $item = is_array($items) ? (array_values($items)[0] ?? null) : null;

            $bonos = $this->agregadorBonos->paraClaves(
                $definiciones,
                $equipamientos->obtener($reclutado->id)->todas(),
            );

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
                senda: $senda,
                bonosPiezas: $bonos->bonos,
                effectKeysPiezas: $bonos->habilidades,
            );
        }

        if (count($combatientes) !== ReglasEquipo::MIEMBROS) {
            throw new ViolacionReglaNegocio(
                'El equipo debe tener exactamente '.ReglasEquipo::MIEMBROS.' miembros para combatir.'
            );
        }

        return new DatosPokemonBatallaCollection($combatientes);
    }

    /**
     * Definiciones de las piezas equipadas por el equipo, resueltas UNA vez.
     *
     * Solo se consulta el puerto si hay claves equipadas.
     */
    private function definicionesDe(
        EquipamientosPorReclutado $equipamientos,
        IntCollection $reclutadoIds,
    ): DefinicionesPiezaCollection {
        if ($this->piezasPorEspecie === null) {
            return new DefinicionesPiezaCollection();
        }

        $claves = new StringCollection();
        foreach ($reclutadoIds as $reclutadoId) {
            foreach ($equipamientos->obtener($reclutadoId)->todas() as $clave) {
                $claves->add($clave);
            }
        }

        if ($claves->isEmpty()) {
            return new DefinicionesPiezaCollection();
        }

        return $this->agregadorBonos->definicionesDe($this->piezasPorEspecie, $claves);
    }

    /**
     * Impide el combate si algún miembro del equipo está en exploración
     * activa (sin regreso registrado).
     *
     * @throws ViolacionReglaNegocio
     */
    private function asegurarSinExploracionActiva(Team $equipo): void
    {
        /** @var array<int, int> $reclutadoIds */
        $reclutadoIds = $equipo->members
            ->pluck('pokemon_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($reclutadoIds === []) {
            return;
        }

        $consulta = (new ExploracionActiva())->newQuery()
            ->where('regreso', null);

        $consulta->getQuery()->whereIn('reclutado_id', $reclutadoIds);

        $hayExploracionActiva = $consulta->toBase()->exists();

        if ($hayExploracionActiva) {
            throw new ViolacionReglaNegocio(
                'El equipo tiene miembros en exploración; espera a que regresen para combatir.'
            );
        }
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
