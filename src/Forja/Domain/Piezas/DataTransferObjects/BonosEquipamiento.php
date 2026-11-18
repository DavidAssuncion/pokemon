<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\DataTransferObjects;

use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Collections\StringCollection;

/**
 * Bonos agregados del equipamiento de piezas de un reclutado.
 *
 * `bonos` es la suma de los stats de las piezas encontradas (sin mutar las
 * definiciones) y `habilidades` las claves de efecto de las piezas que la
 * otorgan. `toArray()` es la única frontera de serialización.
 */
final readonly class BonosEquipamiento
{
    public function __construct(
        public StatsValue $bonos,
        public StringCollection $habilidades,
    ) {
    }

    /**
     * @return array{
     *     stats: array{hp: int, atk: int, def: int, spAtk: int, spDef: int, speed: int},
     *     habilidades: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'stats' => $this->bonos->toArray(),
            'habilidades' => $this->habilidades->all(),
        ];
    }
}
