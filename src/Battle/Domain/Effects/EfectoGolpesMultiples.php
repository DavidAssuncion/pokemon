<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Capacidades\AjustaGolpes;

/**
 * Doble/Triple/Cuádruple Ataque: convierte el movimiento en una secuencia de
 * golpes con potencia fraccionada.
 */
class EfectoGolpesMultiples implements InterfazEfecto, AjustaGolpes
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly int $golpes,
        private readonly float $fraccionPotencia,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    /**
     * @return array{golpes: int, fraccionPotencia: float}
     */
    public function ajustarGolpes(AccionBatalla $accion): array
    {
        return [
            'golpes' => max(1, $this->golpes),
            'fraccionPotencia' => max(0.01, min(1.0, $this->fraccionPotencia)),
        ];
    }
}
