<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/** Cólera: sube At. Especial una vez al bajar del 50 % de HP. */
class EfectoBerserk implements InterfazEfecto, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;

    private bool $usado = false;

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
        if ($this->usado || ! $resultado->huboDano() || ! $portador->estaVivo()) {
            return;
        }

        if ($portador->hpActual() > $portador->pokemon()->battleStats()->hp * 0.5) {
            return;
        }

        $this->usado = true;
        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE_ESPECIAL, 1);
        $portador->setMultiplicadores($cambio->aplicadoTemporalEn($portador->multiplicadores()));
        $battle->agregarLog("¡{$portador->nombre()} se enfurece y sube su At. Especial!");
    }
}
