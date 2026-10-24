<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

/**
 * Habilidad que anula el daño de recoil (Cabeza Roca).
 */
interface InmuneARecoil
{
    public function esInmuneARecoil(): bool;
}
