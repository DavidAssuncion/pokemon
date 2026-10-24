<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;

/**
 * Habilidad que cambia el número de golpes de un movimiento y la fracción de
 * potencia de cada golpe (Doble/Triple/Cuádruple Ataque).
 */
interface AjustaGolpes
{
    /**
     * @return array{golpes: int, fraccionPotencia: float}|null
     */
    public function ajustarGolpes(AccionBatalla $accion): ?array;
}
