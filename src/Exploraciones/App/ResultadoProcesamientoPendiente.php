<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

/**
 * Resumen del procesamiento bajo demanda de exploraciones pendientes.
 *
 * `omitidas` agrupa las exploraciones saltadas por throttle o por lock
 * ocupado; no son errores. `fallidas` cuenta las que lanzaron una excepción
 * durante el despacho (aislada por exploración). `agotadoPorTiempo` indica
 * que se alcanzó el presupuesto y quedaron pendientes sin intentar.
 */
final readonly class ResultadoProcesamientoPendiente
{
    public function __construct(
        public int $procesadas = 0,
        public int $omitidas = 0,
        public int $fallidas = 0,
        public bool $agotadoPorTiempo = false,
    ) {
    }
}
