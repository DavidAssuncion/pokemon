<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte\DTO;

/**
 * Mapa inmutable `refId => SnapshotCombatiente` del estado de la batalla.
 */
final readonly class SnapshotBatalla
{
    /**
     * @param  array<string, SnapshotCombatiente>  $combatientes
     */
    public function __construct(
        public array $combatientes,
    ) {
    }

    public static function vacia(): self
    {
        return new self([]);
    }

    /**
     * @return array<string, SnapshotCombatiente>
     */
    public function todos(): array
    {
        return $this->combatientes;
    }

    public function obtener(string $refId): ?SnapshotCombatiente
    {
        return $this->combatientes[$refId] ?? null;
    }

    /**
     * Serializa al contrato de eventos que consume el cliente.
     *
     * @return array<string, array{hp: float, defHp: float, spDefHp: float, alive: bool, status: string, statusTurns: int}>
     */
    public function toArray(): array
    {
        $estado = [];

        foreach ($this->combatientes as $refId => $combatiente) {
            $estado[$refId] = $combatiente->toArray();
        }

        return $estado;
    }
}
