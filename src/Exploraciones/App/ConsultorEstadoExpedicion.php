<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Carbon\CarbonInterface;
use Src\Exploraciones\Domain\CalculadorEstadoExpedicion;
use Src\Exploraciones\Domain\CalculadorFinExploracion;
use Src\Exploraciones\Domain\CalculadorProgresoExpedicion;
use Src\Exploraciones\Domain\CalculadorVueltaExploracion;
use Src\Exploraciones\Domain\EstadoExpedicion;

/**
 * Punto único de consulta del estado, fechas y progreso de una expedición.
 *
 * Traduce la fila de `exploraciones_activas` (marcas temporales, `archived_at`
 * y `eventos.cancelada`) a los cálculos puros de dominio. No depende de
 * `auth()`: el usuario lo decide siempre el llamador.
 */
final class ConsultorEstadoExpedicion
{
    public function estado(ExploracionActiva $exp, ?CarbonInterface $ahora = null): EstadoExpedicion
    {
        return CalculadorEstadoExpedicion::calcular(
            archivada: $exp->archived_at !== null,
            regresoMarcado: $exp->regreso !== null,
            cancelada: $this->estaCancelada($exp),
            inicioVuelta: $this->inicioVuelta($exp),
            fin: $this->fin($exp),
            ahora: $ahora ?? now(),
        );
    }

    /**
     * Inicio real de la expedición: `inicio_exploracion` con fallback a
     * `created_at` y, en último término, al momento actual.
     */
    public function inicio(ExploracionActiva $exp): CarbonInterface
    {
        $inicio = $exp->inicio_exploracion ?? $exp->created_at;

        return $inicio?->copy() ?? now();
    }

    /**
     * Fin calculado de la expedición (null = indefinida).
     */
    public function fin(ExploracionActiva $exp): ?CarbonInterface
    {
        $horaLimite = $exp->hora_limite;

        return CalculadorFinExploracion::calcular(
            is_string($horaLimite) ? $horaLimite : null,
            $exp->duracion_horas,
            $this->inicio($exp),
        );
    }

    /**
     * Momento en que el explorador inicia la vuelta (null si no hay fin).
     */
    public function inicioVuelta(ExploracionActiva $exp): ?CarbonInterface
    {
        return CalculadorVueltaExploracion::inicioVuelta($this->inicio($exp), $this->fin($exp));
    }

    /**
     * Una expedición cancelada conserva el evento raíz `cancelada` pese a
     * tener `regreso` marcado, por lo que no debe mostrarse como resultado.
     */
    public function estaCancelada(ExploracionActiva $exp): bool
    {
        return $exp->eventos?->get('cancelada') !== null;
    }

    public function progreso(ExploracionActiva $exp, ?CarbonInterface $ahora = null): int
    {
        return CalculadorProgresoExpedicion::calcular(
            $this->inicio($exp),
            $this->fin($exp),
            $ahora ?? now(),
        );
    }
}
