<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlContacto;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Aplica una condición volátil al atacante cuando entra en contacto. */
class EfectoContactoCondicion implements InterfazEfecto, ReaccionaAlContacto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly ClaveCondicion $condicion,
        private readonly float $probabilidad = 0.10,
        private readonly int $turnos = 1,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirContacto(Combatiente $portador, Combatiente $atacante, AgregadoBatalla $battle): void
    {
        if ($atacante->tieneEfecto('remoto')) {
            return;
        }

        if (! $atacante->estaVivo() || mt_rand() / mt_getrandmax() >= $this->probabilidad) {
            return;
        }

        $atacante->agregarCondicion(new CondicionVolatil($this->condicion, $this->turnos, $portador->id()));
        $battle->agregarLog("{$atacante->nombre()} sufre {$this->condicion->label()} por contacto");
    }
}
