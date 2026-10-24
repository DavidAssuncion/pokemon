<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

/** Marcador para habilidades cuya lógica se consulta desde el flujo de combate. */
class EfectoMarcador implements InterfazEfecto
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
