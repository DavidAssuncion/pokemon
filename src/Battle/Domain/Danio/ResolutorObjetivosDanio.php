<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Danio;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Posicion;

/**
 * Resuelve los objetivos de un movimiento de daño:
 * - null: un solo objetivo (flujo normal).
 * - lista: fila o reparto entre varios combatientes.
 */
final class ResolutorObjetivosDanio
{
    /**
     * @return list<Combatiente>|null
     */
    public static function resolver(AccionBatalla $accion, AgregadoBatalla $battle): ?array
    {
        $objetivo = $accion->move->objetivo;
        $rival = $battle->equipoRivalDe($accion->attacker);

        // Los movimientos de estado también pueden afectar a varios rivales
        // (Gruñido, Malicioso, Agitacola…). Los autodirigidos conservan el
        // flujo de un solo objetivo.
        if ($accion->move->esEstado()) {
            if ($objetivo === ObjetivoMovimiento::ALL_ADJACENT_FOES) {
                return $rival?->combatientesCollection()->vivos()->all() ?? [];
            }

            if ($objetivo === ObjetivoMovimiento::ALL_ADJACENT || $objetivo === ObjetivoMovimiento::ALL) {
                $defensores = [];
                foreach ([$battle->equipoDe($accion->attacker), $rival] as $equipo) {
                    if ($equipo === null) {
                        continue;
                    }

                    foreach ($equipo->combatientesCollection()->vivos() as $combatiente) {
                        if ($combatiente !== $accion->attacker) {
                            $defensores[$combatiente->id()] = $combatiente;
                        }
                    }
                }

                return array_values($defensores);
            }

            return null;
        }

        if ($objetivo->esFila()) {
            if ($rival === null) {
                return [];
            }

            $posicion = match ($objetivo) {
                ObjetivoMovimiento::FILA_VANGUARDIA => Posicion::VANGUARDIA,
                ObjetivoMovimiento::FILA_RETAGUARDIA => Posicion::RETAGUARDIA,
                default => $accion->defender->posicion(),
            };

            return $rival->combatientesCollection()->porPosicion($posicion)->vivos()->all();
        }

        if ($objetivo === ObjetivoMovimiento::ALL_ADJACENT_FOES) {
            return $rival?->combatientesCollection()->vivos()->all() ?? [];
        }

        if ($objetivo === ObjetivoMovimiento::ALL_ADJACENT || $objetivo === ObjetivoMovimiento::ALL) {
            $defensores = [];
            foreach ([$battle->equipoDe($accion->attacker), $rival] as $equipo) {
                if ($equipo === null) {
                    continue;
                }

                foreach ($equipo->combatientesCollection()->vivos() as $combatiente) {
                    if ($combatiente !== $accion->attacker) {
                        $defensores[$combatiente->id()] = $combatiente;
                    }
                }
            }

            return array_values($defensores);
        }

        return null;
    }
}
