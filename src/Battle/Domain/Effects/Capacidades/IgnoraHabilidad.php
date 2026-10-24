<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

/**
 * Habilidad que hace que los ataques de su portador ignoren las habilidades
 * defensivas del rival (Rompemoldes y similares).
 */
interface IgnoraHabilidad
{
    public function ignoraHabilidadRival(): bool;
}
