<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Enums;

/**
 * Estado de un combate dentro de una oleada de hábitat temporal.
 */
enum EstadoCombate: string
{
    case Superado = 'superado';
    case Disponible = 'disponible';
    case Bloqueado = 'bloqueado';
}
