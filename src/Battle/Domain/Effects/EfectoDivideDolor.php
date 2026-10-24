<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

/**
 * Divide Dolor: la aplicación real del reparto vive en
 * Combatiente::recibirDañoDividido(). Este efecto solo marca la habilidad.
 */
class EfectoDivideDolor implements InterfazEfecto
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
