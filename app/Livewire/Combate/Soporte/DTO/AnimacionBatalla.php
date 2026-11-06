<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte\DTO;

/**
 * Campos de animación efímeros que consume el cliente para reproducir un evento.
 */
final readonly class AnimacionBatalla
{
    public function __construct(
        public int $tick,
        public string $attackerId,
        public string $attackerNombre,
        public string $defenderId,
        public string $defenderNombre,
        public string $moveNombre,
    ) {
    }
}
