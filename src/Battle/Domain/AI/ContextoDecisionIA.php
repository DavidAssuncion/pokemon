<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Illuminate\Support\Collection;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * DTO que agrega toda la información necesaria para una decisión de IA.
 */
final readonly class ContextoDecisionIA
{
    /**
     * @param Collection<int, Combatiente> $aliados   Combatientes vivos del equipo del actor
     * @param Collection<int, Combatiente> $enemigos  Combatientes vivos del equipo enemigo
     */
    public function __construct(
        public AgregadoBatalla $battle,
        public Combatiente $actor,
        public NivelDificultad $dificultad,
        public Collection $aliados,
        public Collection $enemigos,
        public int $turno,
    ) {
    }
}
