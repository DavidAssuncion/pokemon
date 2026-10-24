<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;

/** Intercambio de Poder/Defensa/Velocidad. */
class EjecutorIntercambioStats extends EjecutorMovimientoAbstracto
{
    public function __construct(
        private readonly ClaveEfectoMovimiento $claveEfecto,
        private readonly StatClave $stat,
    ) {
    }

    public function clave(): ClaveEfectoMovimiento
    {
        return $this->claveEfecto;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $a = $accion->attacker->multiplicadores()->toArray();
        $d = $accion->defender->multiplicadores()->toArray();
        $clave = $this->stat->value;
        [$a['base'][$clave], $d['base'][$clave]] = [$d['base'][$clave] ?? 1.0, $a['base'][$clave] ?? 1.0];
        [$a['temporales'][$clave], $d['temporales'][$clave]] = [$d['temporales'][$clave] ?? 1.0, $a['temporales'][$clave] ?? 1.0];
        $accion->attacker->setMultiplicadores(MultiplicadoresStats::desdeSerializado($a));
        $accion->defender->setMultiplicadores(MultiplicadoresStats::desdeSerializado($d));
        $battle->agregarLog("Se intercambian los cambios de {$this->stat->label()}");
    }
}
