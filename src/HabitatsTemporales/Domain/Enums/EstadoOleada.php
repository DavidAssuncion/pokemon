<?php

declare(strict_types=1);

namespace Src\HabitatsTemporales\Domain\Enums;

/**
 * Estado de una oleada completa dentro de un hábitat temporal.
 */
enum EstadoOleada: string
{
    case Superada = 'superada';
    case Actual = 'actual';
    case Bloqueada = 'bloqueada';
}
