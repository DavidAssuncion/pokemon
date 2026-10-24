<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

/**
 * Meditación: los movimientos de estado aplican sus cambios de estadísticas
 * dos veces. La lógica de duplicación vive en ServicioEjecucionBatalla, que
 * consulta esta clave en el atacante.
 */
class EfectoMeditacion implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }
}
