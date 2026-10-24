<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Habilidad que reacciona cuando el ataque de su portador rompe un escudo
 * (Bromista) o cuando el escudo del portador se rompe (Demoledor).
 */
interface ReaccionaAlEscudoRoto
{
    public function alRomperEscudo(
        Combatiente $portador,
        Combatiente $atacante,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void;
}
