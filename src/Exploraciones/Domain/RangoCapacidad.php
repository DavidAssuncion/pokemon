<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

/**
 * Rango de una capacidad según los umbrales multiplicativos de dificultad.
 */
enum RangoCapacidad: string
{
    case NOVATO = 'novato';
    case COMPETENTE = 'competente';
    case EXPERTO = 'experto';
    case MAESTRO = 'maestro';

    /**
     * Grado numérico del rango (0..3), usado como bonus directo.
     */
    public function nivel(): int
    {
        return match ($this) {
            self::NOVATO => 0,
            self::COMPETENTE => 1,
            self::EXPERTO => 2,
            self::MAESTRO => 3,
        };
    }
}
