<?php

declare(strict_types=1);

namespace Src\Battle\Presentation;

use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * DTO que encapsula el resultado de calcular y aplicar daño.
 *
 * `resultado` contiene el desglose HP/escudo/rotura del último golpe.
 * `golpes` refleja cuántos impactos se resolvieron (multi-golpe).
 */
class DTOResultadoDanio
{
    public function __construct(
        public readonly float $dano,
        public readonly float $directPct,
        public readonly ?ResultadoDanio $resultado = null,
        public readonly int $golpes = 1,
        public readonly float $curado = 0.0,
        public readonly float $recoil = 0.0,
        public readonly CombatientesCollection $objetivos = new CombatientesCollection(),
    ) {
    }
}
