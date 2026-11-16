<?php

declare(strict_types=1);

namespace Src\CombateRuta\App;

use App\Support\CargarMovimientosDesbloqueados;
use App\Support\CombateRuta\AlmacenRivalRutaFijado;
use App\Support\Pc\CalculadoraPcRival;
use App\Support\Pc\ConstruirLeyendaIvsEvs;
use App\Support\Pc\ObtenerPcEquipoJugador;
use App\Support\Pc\VistaPreviaRival;
use Src\Battle\Domain\Collections\DatosPokemonBatallaCollection;
use Src\Shared\Domain\Pc\ComparacionPc;
use Src\Shared\Domain\Pc\ComparadorPc;
use Src\Shared\Domain\Pc\MetodoPcRival;
use Src\Shared\Domain\PoliticaIvsEvs;

/**
 * Previsualiza el rival de ruta 3v3 que se combatirá.
 *
 * - Sin `preview_id` (o con uno inválido) genera el rival con el mismo
 *   generador y movimientos desbloqueados que usa el combate, y lo fija en
 *   sesión para reutilizarlo.
 * - Con un `preview_id` válido reutiliza el snapshot; si el jugador cambió de
 *   nivel, conserva la composición y recalcula stats con `conNivel`.
 * - El PC rival es exacto: suma del equipo fijado, no una media del pool.
 * - La leyenda IV/EV se construye siempre, aunque el equipo esté incompleto.
 */
final class CalcularComparacionPcRuta
{
    public function __construct(
        private readonly GeneradorEquipoRuta $generadorEquipo,
        private readonly AlmacenRivalRutaFijado $almacen,
        private readonly CargarMovimientosDesbloqueados $cargarMovimientos,
        private readonly ObtenerPcEquipoJugador $obtenerPcEquipo,
        private readonly CalculadoraPcRival $calculadoraPcRival,
        private readonly ComparadorPc $comparador,
        private readonly ConstruirLeyendaIvsEvs $constructorLeyenda,
    ) {
    }

    public function calcular(
        int $habitatId,
        int $nivel,
        int $teamId,
        int $userId,
        int $nivelJugador,
        ?string $previewId = null,
    ): VistaPreviaRival {
        [$equipoRival, $previewId] = $this->resolverRival(
            $habitatId,
            $nivel,
            $userId,
            $nivelJugador,
            $previewId,
        );

        $perfil = PoliticaIvsEvs::perfilRuta($nivel);
        $leyenda = $this->constructorLeyenda->construir($perfil, $nivelJugador, $equipoRival);

        $equipo = $this->obtenerPcEquipo->obtener($teamId, $userId, $nivelJugador);

        if (! $equipo->completo || $equipo->pc === null) {
            return new VistaPreviaRival(
                ComparacionPc::equipoIncompleto(
                    $equipo->miembros,
                    MetodoPcRival::RIVAL_FIJADO,
                    false,
                ),
                $leyenda,
                $previewId,
            );
        }

        $pcRival = $this->calculadoraPcRival->desdeDatosBatalla($equipoRival, $nivelJugador);

        if ($pcRival === null) {
            return new VistaPreviaRival(
                ComparacionPc::sinRival(
                    $equipo->pc,
                    $equipo->miembros,
                    MetodoPcRival::RIVAL_FIJADO,
                    false,
                ),
                $leyenda,
                $previewId,
            );
        }

        return new VistaPreviaRival(
            ComparacionPc::ok(
                miembros: $equipo->miembros,
                pcEquipo: $equipo->pc,
                pcRival: $pcRival,
                metodo: MetodoPcRival::RIVAL_FIJADO,
                aproximado: false,
                ratio: $this->comparador->ratio($equipo->pc, $pcRival),
                veredicto: $this->comparador->comparar($equipo->pc, $pcRival),
            ),
            $leyenda,
            $previewId,
        );
    }

    /**
     * Reutiliza el rival fijado si sigue siendo válido o genera y fija uno nuevo.
     *
     * @return array{DatosPokemonBatallaCollection, string}
     */
    private function resolverRival(
        int $habitatId,
        int $nivel,
        int $userId,
        int $nivelJugador,
        ?string $previewId,
    ): array {
        if ($previewId !== null) {
            $fijado = $this->almacen->obtener($userId, $previewId, $habitatId, $nivel);

            if ($fijado !== null) {
                return [
                    $fijado->nivelJugador === $nivelJugador
                        ? $fijado->equipo
                        : $fijado->equipo->conNivel($nivelJugador),
                    $fijado->previewId,
                ];
            }
        }

        $equipo = $this->generadorEquipo->generar(
            $habitatId,
            $nivel,
            $nivelJugador,
            movimientosDesbloqueados: $this->cargarMovimientos->para($userId),
        );

        $fijado = $this->almacen->fijar($userId, $habitatId, $nivel, $nivelJugador, $equipo);

        return [$equipo, $fijado->previewId];
    }
}
