<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

/**
 * Marcador pasivo de Pantallista: la lógica de duplicar Reflejo/Pantalla de Luz
 * vive en `EjecutorPantalla`, que consulta este efecto por clave.
 */
class EfectoPantallista implements InterfazEfecto
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
