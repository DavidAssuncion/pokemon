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

/** Coraza Ira: al bajar del 50 % de HP sube ataque/velocidad y baja defensas. */
class EfectoCorazaIra implements InterfazEfecto, ReaccionaAlDanioRecibido
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
        foreach ([
            [StatClave::ATAQUE, 1],
            [StatClave::ATAQUE_ESPECIAL, 1],
            [StatClave::VELOCIDAD, 1],
            [StatClave::DEFENSA, -1],
            [StatClave::DEFENSA_ESPECIAL, -1],
        ] as [$stat, $etapas]) {
            $portador->setMultiplicadores(CambioStat::desdeEtapas($stat, $etapas)->aplicadoTemporalEn($portador->multiplicadores()));
        }

        $battle->agregarLog("¡{$portador->nombre()} activa Coraza Ira!");
    }
}
