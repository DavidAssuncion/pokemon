<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte\DTO;

/**
 * Posición de un combatiente dentro de las vistas serializadas de equipo.
 */
final readonly class PosicionVistaBatalla
{
    public function __construct(
        public int $team,
        public int $index,
    ) {
    }

    /**
     * @return array{team: int, index: int}
     */
    public function aArray(): array
    {
        return ['team' => $this->team, 'index' => $this->index];
    }
}
