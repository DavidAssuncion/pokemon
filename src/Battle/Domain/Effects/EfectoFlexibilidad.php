<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ModificaEstadistica;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Flexibilidad / Depredador Marino: convierte un 15 % de la Velocidad en
 * Ataque y Ataque Especial.
 */
class EfectoFlexibilidad implements InterfazEfecto, ModificaEstadistica
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccion = 0.15,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorEstadistica(Combatiente $portador, StatClave $estadistica): float
    {
        if ($estadistica !== StatClave::ATAQUE && $estadistica !== StatClave::ATAQUE_ESPECIAL) {
            return 1.0;
        }

        $base = $estadistica === StatClave::ATAQUE
            ? $portador->pokemon()->battleStats()->attack
            : $portador->pokemon()->battleStats()->spAtk;

        if ($base <= 0) {
            return 1.0;
        }

        $bonus = $portador->pokemon()->battleStats()->speed * $this->fraccion;

        return 1.0 + ($bonus / $base);
    }
}
