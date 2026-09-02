<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;

/**
 * DTO que agrega toda la información necesaria para una decisión de IA.
 */
final readonly class ContextoDecisionIA
{
    /**
     * @param Combatiente[] $aliados   Combatientes vivos del equipo del actor
     * @param Combatiente[] $enemigos  Combatientes vivos del equipo enemigo
     */
    public function __construct(
        public AgregadoBatalla $battle,
        public Combatiente $actor,
        public NivelDificultad $dificultad,
        public array $aliados,
        public array $enemigos,
        public int $turno,
        public ?MemoriaCombateIA $memoria = null,
        public string $equipoActor = 'team1',
    ) {
    }
}
