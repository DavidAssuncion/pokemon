<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Shared\Domain\Ports\GeneradorAleatorio;

/**
 * Resuelve la precisión y la esquiva de un movimiento usando el generador
 * aleatorio inyectado, sin añadir ni alterar tiradas.
 */
final class ResolutorPrecision
{
    public function __construct(
        private readonly GeneradorAleatorio $aleatorio,
    ) {
    }

    public function esquiva(AccionBatalla $accion): bool
    {
        $perfil = $accion->move->perfil;
        $precision = $perfil->precisionPorClima[$accion->weather->value] ?? $perfil->precision;

        if ($accion->move->esEstado()) {
            $precision *= $accion->defender->multiplicadorPrecisionEstado();

            return $precision < 1.0 && $this->aleatorio->flotante() >= $precision;
        }

        $precision *= $accion->attacker->multiplicadorPrecisionAtaque();
        if ($precision < 1.0 && $this->aleatorio->flotante() >= $precision) {
            return true;
        }

        if ($accion->attacker->tieneEfecto('indefenso')
            || $accion->defender->tieneEfecto('indefenso')
            || $accion->attacker->tieneEfecto('ojo_mental')) {
            return false;
        }

        $esquiva = $accion->defender->probabilidadEsquiva();

        return $esquiva > 0.0 && $this->aleatorio->flotante() < $esquiva;
    }
}
