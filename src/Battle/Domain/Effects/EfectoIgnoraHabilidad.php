<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Effects\Capacidades\IgnoraHabilidad;

/**
 * Habilidad pasiva tipo Rompemoldes: el portador ignora las habilidades
 * defensivas del defensor al calcular daño.
 */
class EfectoIgnoraHabilidad implements InterfazEfecto, IgnoraHabilidad
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function ignoraHabilidadRival(): bool
    {
        return true;
    }
}
