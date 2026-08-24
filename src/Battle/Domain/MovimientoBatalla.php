<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Shared\Tipos\TipoPokemon;

class MovimientoBatalla
{
    public function __construct(
        public readonly string $nombre,
        public readonly int $potencia,
        public readonly TipoPokemon $tipo,
        public readonly CategoriaMovimiento $categoria,
        public readonly EstadoPokemon $statusEffect = EstadoPokemon::NONE,
        public readonly int $priority = 0,
        /** @var array<array{stat: string, stages: int}> */
        public readonly array $selfStatChanges = [],
        /** @var array<array{stat: string, stages: int}> */
        public readonly array $targetStatChanges = [],
    ) {
    }

    public function esEspecial(): bool
    {
        return $this->categoria === CategoriaMovimiento::ESPECIAL;
    }

    public function esFisico(): bool
    {
        return $this->categoria === CategoriaMovimiento::FISICO;
    }

    public function esEstado(): bool
    {
        return $this->categoria === CategoriaMovimiento::ESTADO;
    }

    public function tieneStatus(): bool
    {
        return $this->statusEffect !== EstadoPokemon::NONE;
    }

    public function tieneSelfStatChanges(): bool
    {
        return ! empty($this->selfStatChanges);
    }

    public function tieneTargetStatChanges(): bool
    {
        return ! empty($this->targetStatChanges);
    }
}
