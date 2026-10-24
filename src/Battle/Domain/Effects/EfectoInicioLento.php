<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ModificaEstadistica;
use Src\Battle\Domain\Enums\StatClave;

/** Inicio Lento: Ataque y Velocidad a la mitad durante cinco rondas. */
class EfectoInicioLento implements InterfazEfecto, ModificaEstadistica
{
    use ComportamientosPorDefecto;

    private int $turnos = 5;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if ($this->turnos > 0) {
            $this->turnos--;
        }
    }

    public function multiplicadorEstadistica(Combatiente $portador, StatClave $estadistica): float
    {
        if ($this->turnos <= 0) {
            return 1.0;
        }

        return in_array($estadistica, [StatClave::ATAQUE, StatClave::VELOCIDAD], true) ? 0.5 : 1.0;
    }
}
