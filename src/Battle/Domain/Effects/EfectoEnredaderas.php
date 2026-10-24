<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Enredaderas: los ataques tienen un 20 % de reducir 1/16 la Velocidad del
 * objetivo.
 */
class EfectoEnredaderas implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $probabilidad = 0.20,
        private readonly float $fraccion = 0.0625,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onDamageDealt(Combatiente $portador, Combatiente $target, float $daño, AgregadoBatalla $battle): void
    {
        if ($daño <= 0.0 || ! $target->estaVivo() || mt_rand() / mt_getrandmax() >= $this->probabilidad) {
            return;
        }

        $target->setMultiplicadores(
            $target->multiplicadores()->aplicarTemporal(StatClave::VELOCIDAD, 1 - $this->fraccion),
        );
        $battle->agregarLog("{$target->nombre()} baja su Velocidad por Enredaderas");
    }
}
