<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\Bando;

/**
 * DTO que agrega toda la información necesaria para una decisión de IA.
 */
final readonly class ContextoDecisionIA
{
    public function __construct(
        public AgregadoBatalla $battle,
        public Combatiente $actor,
        public NivelDificultad $dificultad,
        public CombatientesCollection $aliados,
        public CombatientesCollection $enemigos,
        public int $turno,
        public ?MemoriaCombateIA $memoria = null,
        public Bando $equipoActor = Bando::UNO,
    ) {
    }
}
