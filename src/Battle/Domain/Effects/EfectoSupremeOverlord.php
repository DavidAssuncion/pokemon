<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioInfligido;

/** General Supremo: más daño por cada aliado debilitado. */
class EfectoSupremeOverlord implements InterfazEfecto, ModificaDanioInfligido
{
    use ComportamientosPorDefecto;

    private int $caidos = 0;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->caidos = 0;
        foreach ($battle->equipoDe($portador)?->combatientesCollection()->all() ?? [] as $aliado) {
            if ($aliado !== $portador && ! $aliado->estaVivo()) {
                $this->caidos++;
            }
        }
    }

    public function multiplicadorDanioInfligido(AccionBatalla $accion): float
    {
        return min(1.5, 1.0 + 0.10 * $this->caidos);
    }
}
