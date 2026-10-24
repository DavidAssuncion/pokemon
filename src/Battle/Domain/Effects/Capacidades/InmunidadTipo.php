<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Habilidad que anula por completo un tipo de movimiento y reacciona al
 * absorberlo (curar, subir una estadística, registrar log…).
 */
interface InmunidadTipo
{
    public function esInmuneA(TipoPokemon $tipo): bool;

    public function alAbsorber(Combatiente $portador, TipoPokemon $tipo, AgregadoBatalla $battle): void;
}
