<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte\DTO;

/**
 * Instantánea inmutable de los vitales y el estado alterado de un combatiente.
 */
final readonly class SnapshotCombatiente
{
    public function __construct(
        public float $hp,
        public float $defHp,
        public float $spDefHp,
        public bool $alive,
        public string $status,
        public int $statusTurns,
    ) {
    }

    /**
     * @return array{hp: float, defHp: float, spDefHp: float, alive: bool, status: string, statusTurns: int}
     */
    public function toArray(): array
    {
        return [
            'hp' => $this->hp,
            'defHp' => $this->defHp,
            'spDefHp' => $this->spDefHp,
            'alive' => $this->alive,
            'status' => $this->status,
            'statusTurns' => $this->statusTurns,
        ];
    }
}
