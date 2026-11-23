<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\DataTransferObjects;

use Src\HabitatsTemporales\Domain\Enums\EstadoCombate;

/**
 * Combate visible de una oleada, con estado ya calculado para la vista.
 */
final readonly class CombateVista
{
    public function __construct(
        public int $numero,
        public string $nombre,
        public string $entrenador,
        public string $descripcion,
        public string $spriteUrl,
        public bool $esJefe,
        public EstadoCombate $estado,
        public ?string $motivoBloqueo,
    ) {
    }

    /**
     * @return array{numero: int, nombre: string, entrenador: string, descripcion: string, sprite_url: string, es_jefe: bool, estado: string, motivo_bloqueo: string|null}
     */
    public function toArray(): array
    {
        return [
            'numero' => $this->numero,
            'nombre' => $this->nombre,
            'entrenador' => $this->entrenador,
            'descripcion' => $this->descripcion,
            'sprite_url' => $this->spriteUrl,
            'es_jefe' => $this->esJefe,
            'estado' => $this->estado->value,
            'motivo_bloqueo' => $this->motivoBloqueo,
        ];
    }
}
