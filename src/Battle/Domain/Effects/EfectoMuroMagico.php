<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\InmuneADanioIndirecto;

/**
 * Muro Mágico: el equipo no recibe daño indirecto (trampas, drenadoras,
 * clima). El motor consulta esta capacidad antes de aplicar daño indirecto.
 */
class EfectoMuroMagico implements InterfazEfecto, InmuneADanioIndirecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function bloqueaDanioIndirecto(): bool
    {
        return true;
    }
}
