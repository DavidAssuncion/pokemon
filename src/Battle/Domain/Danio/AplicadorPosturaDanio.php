<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Danio;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\PosturaCombate;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Reparte el daño entre el objetivo y sus cubridores (40/60 o 40/40/20) y
 * delega el daño final en AplicadorDanioObjetivo.
 */
final class AplicadorPosturaDanio
{
    public function __construct(
        private readonly AplicadorDanioObjetivo $aplicadorObjetivo = new AplicadorDanioObjetivo(),
    ) {
    }

    public function aplicar(
        AccionBatalla $accion,
        MovimientoBatalla $movimiento,
        float $daño,
        float $directPct,
        PerfilMovimiento $perfil,
        ?AgregadoBatalla $battle,
    ): ResultadoDanio {
        $defensor = $accion->defender;

        if ($battle === null) {
            return $this->aplicadorObjetivo->aplicar(
                $defensor,
                $movimiento,
                $daño,
                $directPct,
                $perfil,
                null,
                $accion,
            );
        }

        $equipo = $battle->equipoDe($defensor);
        $cubridores = [];
        if ($equipo !== null) {
            foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
                if ($aliado !== $defensor
                    && $aliado->postura() === PosturaCombate::CUBRIR
                    && $aliado->objetivoCubiertoId() === $defensor->id()) {
                    $cubridores[] = $aliado;
                }
            }
        }
        $cubridores = array_slice($cubridores, 0, 2);

        $reparto = [[$defensor, 1.0]];
        if (count($cubridores) === 1) {
            $reparto = [[$cubridores[0], 0.4], [$defensor, 0.6]];
        } elseif (count($cubridores) === 2) {
            $reparto = [[$cubridores[0], 0.4], [$cubridores[1], 0.4], [$defensor, 0.2]];
        }

        $total = new ResultadoDanio();
        foreach ($reparto as [$objetivo, $cuota]) {
            $resultado = $this->aplicadorObjetivo->aplicar(
                $objetivo,
                $movimiento,
                $daño * $cuota,
                $directPct,
                $perfil,
                $battle,
                $accion,
            );

            $total = new ResultadoDanio(
                danoHp: $total->danoHp + $resultado->danoHp,
                danoEscudo: $total->danoEscudo + $resultado->danoEscudo,
                excedente: $total->excedente + $resultado->excedente,
                rompioEscudo: $total->rompioEscudo || $resultado->rompioEscudo,
            );

            if ($cuota < 1.0) {
                $battle->agregarLog("{$objetivo->nombre()} recibe ".round($cuota * 100).'% del ataque');
            }
        }

        return $total;
    }
}
