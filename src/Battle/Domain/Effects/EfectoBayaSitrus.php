<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Baya Sitrus: cuando el portador baja del 50 % de HP, consume la baya y
 * recupera un 25 % de su HP máximo. Una carga por batalla.
 */
class EfectoBayaSitrus implements InterfazEfecto, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
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
        if (! $portador->itemActivo() || ! $portador->estaVivo()) {
            return;
        }

        $maxHp = $portador->pokemon()->battleStats()->hp;
        if ($maxHp <= 0.0 || $portador->hpActual() > $maxHp * 0.5) {
            return;
        }

        $curado = $portador->curarHpCantidad($maxHp * 0.25);
        if ($curado <= 0.0) {
            return;
        }

        $portador->consumirCargaObjeto();
        $battle->agregarLog("{$portador->nombre()} consume Baya Sitrus y recupera ".round($curado).' PS');
    }
}
