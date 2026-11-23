<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Collections;

use Src\HabitatsTemporales\Domain\DataTransferObjects\CombateVista;
use Src\Shared\Domain\Collection;

/**
 * Colección de combates visibles de la oleada actual.
 */
final class CombateVistaCollection extends Collection
{
    public string $type = CombateVista::class;

    /**
     * @return list<array{numero: int, nombre: string, entrenador: string, descripcion: string, sprite_url: string, es_jefe: bool, estado: string, motivo_bloqueo: string|null}>
     */
    public function toArray(): array
    {
        return $this->map(static fn (CombateVista $combate): array => $combate->toArray());
    }
}
