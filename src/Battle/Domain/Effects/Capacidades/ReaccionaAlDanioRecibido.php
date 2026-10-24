<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Habilidad que reacciona al daño recibido con información rica: quién atacó,
 * cuánto fue a HP, cuánto al escudo y si el escudo se rompió.
 */
interface ReaccionaAlDanioRecibido
{
    public function alRecibirDanio(
        Combatiente $portador,
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void;
}
