<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlSerObjetivo;
use Src\Battle\Domain\Enums\EstadoPokemon;

/**
 * Hypnotizador: al ser objetivo de un movimiento rival tiene 1/8 de dormir
 * al atacante durante un turno.
 */
class EfectoHipnotizador implements InterfazEfecto, ReaccionaAlSerObjetivo
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $probabilidad = 0.125,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alSerObjetivo(Combatiente $portador, AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        if (mt_rand() / mt_getrandmax() >= $this->probabilidad) {
            return;
        }

        $atacante = $accion->attacker;
        if (! $atacante->estaVivo() || $atacante->estado() !== EstadoPokemon::NONE) {
            return;
        }

        $atacante->setEstado(EstadoPokemon::SLEEP);
        $atacante->setTurnosEstado(1);
        $battle->agregarLog("{$atacante->nombre()} se duerme por Hypnotizador");
    }
}
