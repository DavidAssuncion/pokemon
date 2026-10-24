<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\ValueObjects\AjusteGolpes;

/**
 * Habilidad que cambia el número de golpes de un movimiento y la fracción de
 * potencia de cada golpe (Doble/Triple/Cuádruple Ataque).
 */
interface AjustaGolpes
{
    public function ajustarGolpes(AccionBatalla $accion): ?AjusteGolpes;
}
