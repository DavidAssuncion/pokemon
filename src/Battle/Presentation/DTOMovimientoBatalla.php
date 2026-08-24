<?php

declare(strict_types=1);

namespace Src\Battle\Presentation;

use Livewire\Wireable;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Shared\Tipos\TipoPokemon;

/**
 * DTO de presentación para MovimientoBatalla.
 * Implementa Wireable para Livewire sin acoplar la entidad de dominio.
 */
class DTOMovimientoBatalla implements Wireable
{
    public function __construct(
        public readonly string $nombre,
        public readonly int $potencia,
        public readonly string $tipo,
        public readonly string $categoria,
        public readonly string $statusEffect = '',
        public readonly int $priority = 0,
        /** @var array<array{stat: string, stages: int}> */
        public readonly array $selfStatChanges = [],
        /** @var array<array{stat: string, stages: int}> */
        public readonly array $targetStatChanges = [],
    ) {
    }

    public static function desdeDominio(MovimientoBatalla $move): self
    {
        return new self(
            nombre: $move->nombre,
            potencia: $move->potencia,
            tipo: $move->tipo->value,
            categoria: $move->categoria->value,
            statusEffect: $move->statusEffect->value,
            priority: $move->priority,
            selfStatChanges: $move->selfStatChanges,
            targetStatChanges: $move->targetStatChanges,
        );
    }

    public function toLivewire(): array
    {
        return [
            'nombre' => $this->nombre,
            'potencia' => $this->potencia,
            'tipo' => $this->tipo,
            'categoria' => $this->categoria,
            'statusEffect' => $this->statusEffect,
            'priority' => $this->priority,
            'selfStatChanges' => $this->selfStatChanges,
            'targetStatChanges' => $this->targetStatChanges,
        ];
    }

    public static function fromLivewire($value): self
    {
        return new self(
            nombre: $value['nombre'],
            potencia: $value['potencia'],
            tipo: $value['tipo'],
            categoria: $value['categoria'],
            statusEffect: $value['statusEffect'] ?? '',
            priority: $value['priority'] ?? 0,
            selfStatChanges: $value['selfStatChanges'] ?? [],
            targetStatChanges: $value['targetStatChanges'] ?? [],
        );
    }

    public function toDomain(): MovimientoBatalla
    {
        return new MovimientoBatalla(
            nombre: $this->nombre,
            potencia: $this->potencia,
            tipo: TipoPokemon::from($this->tipo),
            categoria: CategoriaMovimiento::from($this->categoria),
            statusEffect: $this->statusEffect !== '' ? EstadoPokemon::from($this->statusEffect) : EstadoPokemon::NONE,
            priority: $this->priority,
            selfStatChanges: $this->selfStatChanges,
            targetStatChanges: $this->targetStatChanges,
        );
    }
}
