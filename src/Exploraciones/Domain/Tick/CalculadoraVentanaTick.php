<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Tick;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Cálculos puros de la ventana de un tick de exploración.
 *
 * Extraído de ProcesarExploracionHandler sin cambios de comportamiento: el
 * límite es el menor entre ahora, fin e inicio de vuelta, y el último procesado
 * se interpreta desde el valor persistido.
 */
final class CalculadoraVentanaTick
{
    /**
     * Límite del tick: el menor instante entre ahora, fin e inicio de vuelta.
     */
    public static function limite(
        CarbonInterface $ahora,
        ?CarbonInterface $fin,
        ?CarbonInterface $inicioVuelta,
    ): CarbonInterface {
        $limite = $ahora;

        if ($fin !== null && $fin->lessThan($limite)) {
            $limite = $fin;
        }

        if ($inicioVuelta !== null && $inicioVuelta->lessThan($limite)) {
            $limite = $inicioVuelta;
        }

        return $limite;
    }

    /**
     * Último instante procesado persistido en eventos['ultimo_procesado'].
     */
    public static function ultimoProcesado(mixed $ultimo): ?CarbonInterface
    {
        return is_string($ultimo) ? Carbon::parse($ultimo) : null;
    }
}
