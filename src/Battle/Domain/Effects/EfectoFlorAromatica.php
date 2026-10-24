<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Flor Aromática: cada turno reduce 1/16 el Ataque y el Ataque Especial de
 * todos los rivales.
 */
class EfectoFlorAromatica implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccion = 0.0625,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $rival = $this->equipoRivalDe($battle, $portador);
        if ($rival === null) {
            return;
        }

        foreach ($rival->combatientesCollection()->vivos() as $objetivo) {
            $objetivo->setMultiplicadores(
                $objetivo->multiplicadores()
                    ->aplicarTemporal(StatClave::ATAQUE, 1 - $this->fraccion)
                    ->aplicarTemporal(StatClave::ATAQUE_ESPECIAL, 1 - $this->fraccion),
            );
        }

        $battle->agregarLog("El aroma de {$portador->nombre()} reduce el ataque rival");
    }
}
