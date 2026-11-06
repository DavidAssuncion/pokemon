<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Tick;

use Src\Exploraciones\Domain\ReglasExploracion;
use Src\Exploraciones\Domain\ValueObjects\EstadoExplorador;

/**
 * Cálculo puro del descanso del explorador: 1 % de HP por minuto real,
 * modulado por el multiplicador de recuperación.
 *
 * No conoce el reloj, la bitácora ni la persistencia: devuelve el nuevo estado
 * y los minutos que el orquestador debe acumular y anotar.
 */
final class GestorDescansoExplorador
{
    /**
     * Aplica el descanso si el explorador está por debajo del 50 % de HP.
     * Devuelve null cuando no procede descansar.
     */
    public static function calcular(EstadoExplorador $estado, float $multiplicadorRecuperacion): ?DescansoAplicado
    {
        if (! $estado->requiereDescanso()) {
            return null;
        }

        $pctFaltante = 100 - $estado->pctHp();
        $duracionMinutos = (int) ceil(
            $pctFaltante / (ReglasExploracion::HP_POR_MINUTO_DESCANSO * $multiplicadorRecuperacion)
        );
        $hpRecuperado = $estado->hpMax - $estado->hp;

        return new DescansoAplicado(
            estado: $estado->conHp($estado->hpMax),
            duracionMinutos: $duracionMinutos,
            hpRecuperado: $hpRecuperado,
        );
    }
}
