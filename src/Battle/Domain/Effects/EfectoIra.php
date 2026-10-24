<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Ira: cada golpe recibido sube 1/16 el Ataque y el Ataque Especial. Al ser
 * multi-golpe, cada impacto aplica su propia subida (2 golpes = 1/8).
 */
class EfectoIra implements InterfazEfecto, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $fraccion = 0.0625,
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
        if (! $resultado->huboDano() || ! $portador->estaVivo()) {
            return;
        }

        $multiplicadores = $portador->multiplicadores()
            ->aplicarTemporal(StatClave::ATAQUE, 1 + $this->fraccion)
            ->aplicarTemporal(StatClave::ATAQUE_ESPECIAL, 1 + $this->fraccion);
        $portador->setMultiplicadores($multiplicadores);
    }
}
