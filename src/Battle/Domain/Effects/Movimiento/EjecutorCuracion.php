<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;

/** Curación dirigida a un aliado o a todo el equipo, con cura de estado opcional. */
class EjecutorCuracion extends EjecutorMovimientoAbstracto
{
    public function __construct(
        private readonly ClaveEfectoMovimiento $claveEfecto,
        private readonly bool $equipo = false,
        private readonly bool $curaEstado = false,
    ) {
    }

    public function clave(): ClaveEfectoMovimiento
    {
        return $this->claveEfecto;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $fraccion = (float) (($accion->move->perfil->toArray()['curacionFraccion'] ?? 0.0));
        if ($fraccion <= 0.0) {
            $fraccion = max(0.0, $accion->move->perfil->curacionFraccion);
        }
        if ($fraccion <= 0.0) {
            $fraccion = 0.5;
        }

        $objetivos = [];
        if ($this->equipo) {
            $equipo = $battle->equipoDe($accion->attacker);
            $objetivos = $equipo?->combatientesCollection()->vivos()->all() ?? [];
        } else {
            $objetivos = [$accion->move->objetivo->value === 'self' ? $accion->attacker : $accion->defender];
        }

        foreach ($objetivos as $objetivo) {
            if (! $objetivo->estaVivo()) {
                continue;
            }

            $cantidad = $objetivo->pokemon()->battleStats()->hp * $fraccion;
            $objetivo->curarConExcedente($cantidad);
            if ($this->curaEstado) {
                $objetivo->setEstado(EstadoPokemon::NONE);
                $objetivo->setTurnosEstado(0);
                $objetivo->setContadorVenenoGrave(0);
            }
            $battle->agregarLog("{$objetivo->nombre()} recupera ".round($cantidad).' PS');
        }
    }
}
