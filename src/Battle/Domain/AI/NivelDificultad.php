<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

/**
 * Niveles de dificultad de la IA de combate.
 * Controla la profundidad del análisis y la tolerancia a errores.
 */
enum NivelDificultad: string
{
    case NORMAL = 'normal';
    case DIFICIL = 'dificil';
    case PERFECTA = 'perfecta';
}
