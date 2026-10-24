<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Bálsamo Osado: cura el estado y sube todas las estadísticas. */
class EjecutorTomarAliento extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::TOMAR_ALIENTO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $accion->attacker->setEstado(EstadoPokemon::NONE);
        $accion->attacker->setTurnosEstado(0);
        $accion->attacker->setContadorVenenoGrave(0);
        foreach ([StatClave::ATAQUE, StatClave::DEFENSA, StatClave::ATAQUE_ESPECIAL, StatClave::DEFENSA_ESPECIAL, StatClave::VELOCIDAD] as $stat) {
            $accion->attacker->setMultiplicadores(CambioStat::desdeEtapas($stat, 1)->aplicadoTemporalEn($accion->attacker->multiplicadores()));
        }
        $battle->agregarLog("{$accion->attacker->nombre()} toma aliento y se fortalece");
    }
}
