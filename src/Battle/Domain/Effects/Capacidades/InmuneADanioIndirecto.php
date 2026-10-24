<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Capacidades;

/**
 * Habilidad que protege al equipo del daño indirecto (Muro Mágico).
 */
interface InmuneADanioIndirecto
{
    public function bloqueaDanioIndirecto(): bool;
}
