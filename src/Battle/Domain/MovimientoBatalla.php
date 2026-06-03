<?php

namespace Src\Battle\Domain;

use Src\Shared\Tipos\TipoPokemon;

class MovimientoBatalla
{
    public function __construct(
        public readonly string $nombre,
        public readonly int $potencia,
        public readonly TipoPokemon $tipo,
        public readonly string $categoria,
        public readonly string $statusEffect = '',
        public readonly int $priority = 0,
        /** @var array<array{stat: string, stages: int}> */
        public readonly array $selfStatChanges = [],
        /** @var array<array{stat: string, stages: int}> */
        public readonly array $targetStatChanges = [],
    ) {}

    public function esEspecial(): bool
    {
        return $this->categoria === 'especial';
    }

    public function esFisico(): bool
    {
        return $this->categoria === 'fisico';
    }

    public function esEstado(): bool
    {
        return $this->categoria === 'estado';
    }

    public function tieneStatus(): bool
    {
        return $this->statusEffect !== '';
    }

    public function tieneSelfStatChanges(): bool
    {
        return !empty($this->selfStatChanges);
    }

    public function tieneTargetStatChanges(): bool
    {
        return !empty($this->targetStatChanges);
    }
}
