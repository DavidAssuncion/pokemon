<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Pc;

/**
 * Veredicto cualitativo de la comparación de PC (rival / equipo propio).
 */
enum VeredictoPc: string
{
    case FAVORABLE = 'favorable';
    case PAREJO = 'parejo';
    case DESFAVORABLE = 'desfavorable';
}
