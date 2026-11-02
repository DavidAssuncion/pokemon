<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\DataTransferObjects;

use Src\Shared\Collections\StringCollection;

/**
 * Matchup de tipos de un miembro contra un tipo del pool (semáforo de tipos).
 * Sustituye el array {miembro_tipos, pool_tipo, defensa, ataque, clasificacion}.
 */
final readonly class Matchup
{
    public function __construct(
        public StringCollection $miembroTipos,
        public string $poolTipo,
        public float $defensa,
        public float $ataque,
        public string $clasificacion,
    ) {
    }

    /**
     * @return array{miembro_tipos: list<string>, pool_tipo: string, defensa: float, ataque: float, clasificacion: string}
     */
    public function toArray(): array
    {
        return [
            'miembro_tipos' => $this->miembroTipos->toList(),
            'pool_tipo' => $this->poolTipo,
            'defensa' => $this->defensa,
            'ataque' => $this->ataque,
            'clasificacion' => $this->clasificacion,
        ];
    }
}
