<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Espinas / Piel Seca: devuelve 1/8 del daño de los ataques físicos.
 */
class EfectoEspinas implements InterfazEfecto, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccion = 0.125,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirDanio(
        Combatiente $portador,
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        if ($accion->attacker->tieneEfecto('remoto')) {
            return;
        }

        if (! $accion->move->esFisico() || ! $resultado->huboDano() || ! $accion->attacker->estaVivo()) {
            return;
        }

        $cantidad = $resultado->danoAplicado() * $this->fraccion;
        if ($cantidad <= 0.0) {
            return;
        }

        $accion->attacker->recibirDaño($cantidad, false, 1.0);
        $battle->agregarLog("{$portador->nombre()} devuelve ".round($cantidad)." de daño a {$accion->attacker->nombre()}");
    }
}
