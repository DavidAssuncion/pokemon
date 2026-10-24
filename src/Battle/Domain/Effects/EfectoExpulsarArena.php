<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/** Expulsarena: activa tormenta de arena al recibir daño. */
class EfectoExpulsarArena implements InterfazEfecto, ReaccionaAlDanioRecibido
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
        if (! $resultado->huboDano()) {
            return;
        }

        $battle->setWeather(TipoClima::TORMENTA_ARENA);
        $battle->agregarLog("¡{$portador->nombre()} levanta una tormenta de arena!");
    }
}
