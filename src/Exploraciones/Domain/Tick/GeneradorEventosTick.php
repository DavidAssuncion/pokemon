<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Tick;

use Carbon\CarbonInterface;
use Src\Exploraciones\Domain\CadenciaEventos;
use Src\Exploraciones\Domain\PerfilExploracion;
use Src\Exploraciones\Domain\SimuladorEncuentros;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Exploraciones\Domain\ValueObjects\EventoExploracion;
use Src\Exploraciones\Domain\ValueObjects\PoolHabitat;

/**
 * Generación pura de los eventos de un tick.
 *
 * Incluye el backfill del contador de exploraciones antiguas, el cálculo de
 * eventos pendientes y la generación desde el pool, además del evento extra
 * que compensa una emboscada evitada. No toca la bitácora ni persiste.
 */
final class GeneradorEventosTick
{
    /**
     * Valor inicial del contador `eventos_generados` para exploraciones que no
     * lo tienen: cada evento ya registrado se asume consumido, acotado por los
     * `esperados` del reloj.
     *
     * @param  list<array<string, mixed>>  $bitacora
     */
    public static function backfill(
        array $bitacora,
        CarbonInterface $inicio,
        CarbonInterface $hasta,
        int $tiempoPerdido,
        int $intervaloBase,
        float $reduccionIntervalo,
        int $bonusEventos,
    ): int {
        return min(
            CadenciaEventos::esperados(
                $inicio,
                $hasta,
                $tiempoPerdido,
                $intervaloBase,
                $reduccionIntervalo,
                $bonusEventos,
            ),
            count($bitacora),
        );
    }

    /**
     * Eventos que faltan por programar en la ventana del tick.
     */
    public static function pendientes(
        CarbonInterface $inicio,
        CarbonInterface $hasta,
        int $eventosGenerados,
        int $tiempoPerdido,
        int $intervaloBase,
        float $reduccionIntervalo,
        int $bonusEventos,
    ): int {
        return CadenciaEventos::eventosPendientes(
            $inicio,
            $hasta,
            $eventosGenerados,
            $tiempoPerdido,
            $intervaloBase,
            $reduccionIntervalo,
            $bonusEventos,
        );
    }

    /**
     * Genera los eventos pendientes desde el pool del hábitat.
     *
     * @param  callable():float  $aleatorio
     */
    public static function generar(
        PoolHabitat $pool,
        int $pendientes,
        CarbonInterface $desde,
        CarbonInterface $hasta,
        callable $aleatorio,
        bool $permitirEmboscadas,
        bool $permitirExcepcionales,
        PerfilExploracion $perfil,
    ): ColeccionEventosExploracion {
        return SimuladorEncuentros::generarEventosDesdePool(
            $pool,
            $pendientes,
            $desde,
            $hasta,
            $aleatorio,
            $permitirEmboscadas,
            $permitirExcepcionales,
            perfil: $perfil,
        );
    }

    /**
     * Genera EXACTAMENTE un evento extra (hallazgo/neutral) para compensar una
     * emboscada evitada. Se genera sin emboscadas permitidas: el extra nunca
     * puede ser una emboscada y, por tanto, no añade más eventos.
     *
     * @param  callable():float  $aleatorio
     */
    public static function extra(
        PoolHabitat $pool,
        CarbonInterface $desde,
        CarbonInterface $hasta,
        callable $aleatorio,
        PerfilExploracion $perfil,
    ): EventoExploracion {
        $extras = SimuladorEncuentros::generarEventosDesdePool(
            $pool,
            1,
            $desde,
            $hasta,
            $aleatorio,
            false,
            perfil: $perfil,
        );

        return $extras->item(0) ?? new EventoExploracion(
            tipo: 'neutral',
            detalle: 'evento neutral',
        );
    }
}
