<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;

/**
 * Premonición / Deseo Oculto: el ataque no hace daño inmediato. Guarda una
 * instantánea del atacante y se resuelve al final de la ronda indicada.
 */
class EjecutorDanoDiferido extends EjecutorMovimientoAbstracto
{
    public function clave(): ClaveEfectoMovimiento
    {
        return ClaveEfectoMovimiento::DANO_DIFERIDO;
    }

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void
    {
        $perfil = $accion->move->perfil;
        $turnos = max(1, $perfil->turnosDanoDiferido);
        $battle->agregarDanioDiferido([
            'objetivoId' => $accion->defender->id(),
            'atacanteId' => $accion->attacker->id(),
            'movimiento' => $accion->move->nombre,
            'potencia' => $accion->move->potencia,
            'tipo' => $accion->move->tipo->value,
            'nivel' => $accion->attacker->pokemon()->battleStats()->nivel ?? 50,
            'atk' => $accion->attacker->obtenerStatEfectivo(
                $accion->move->esEspecial()
                    ? \Src\Battle\Domain\Enums\StatClave::ATAQUE_ESPECIAL
                    : \Src\Battle\Domain\Enums\StatClave::ATAQUE,
            ),
            'stab' => $accion->attacker->tiposDefensivos() !== []
                && in_array($accion->move->tipo, $accion->attacker->tiposDefensivos(), true) ? 1.5 : 1.0,
            'ronda' => $battle->turnManager()->round() + $turnos,
            'fraccion' => 0.0,
        ]);

        $battle->agregarLog("{$accion->attacker->nombre()} deja {$accion->move->nombre} flotando sobre {$accion->defender->nombre()}");
    }
}
