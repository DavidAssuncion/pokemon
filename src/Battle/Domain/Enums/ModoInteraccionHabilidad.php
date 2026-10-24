<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Enums;

/**
 * Modos de interacción con la habilidad del objetivo.
 *
 * `IGNORAR` se aplica al portador del movimiento (Rompemoldes/Ojo Perspicaz):
 * durante N turnos sus ataques ignoran las habilidades defensivas del rival.
 */
enum ModoInteraccionHabilidad: string
{
    case ANULAR = 'anular';
    case COPIAR = 'copiar';
    case INTERCAMBIAR = 'intercambiar';
    case IGNORAR = 'ignorar';

    public function label(): string
    {
        return match ($this) {
            self::ANULAR => 'anular la habilidad',
            self::COPIAR => 'copiar la habilidad',
            self::INTERCAMBIAR => 'intercambiar habilidades',
            self::IGNORAR => 'ignorar la habilidad rival',
        };
    }
}
