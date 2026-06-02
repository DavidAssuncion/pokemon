<?php

namespace Src\Battle\Domain;

use Livewire\Wireable;
use Src\Shared\Tipos\TipoPokemon;

class BattleMove implements Wireable
{
    public function __construct(
        public readonly string $nombre,
        public readonly int $potencia,
        public readonly TipoPokemon $tipo,
        public readonly string $categoria,
    ) {}

    public function esEspecial(): bool
    {
        return $this->categoria === 'especial';
    }

    public function esFisico(): bool
    {
        return $this->categoria === 'fisico';
    }

    public function toLivewire(): array
    {
        return [
            'nombre' => $this->nombre,
            'potencia' => $this->potencia,
            'tipo' => $this->tipo->value,
            'categoria' => $this->categoria,
        ];
    }

    public static function fromLivewire($value): self
    {
        return new self(
            nombre: $value['nombre'],
            potencia: $value['potencia'],
            tipo: TipoPokemon::from($value['tipo']),
            categoria: $value['categoria'],
        );
    }
}
