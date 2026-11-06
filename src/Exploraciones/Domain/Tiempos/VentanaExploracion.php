<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Tiempos;

use Carbon\CarbonInterface;

/**
 * Ventana temporal de una expedición: instante de inicio, fin calculado
 * (null si la expedición es indefinida) e instante de referencia.
 *
 * El cálculo del fin queda fuera del VO para mantenerlo como dato puro.
 */
final readonly class VentanaExploracion
{
    public function __construct(
        public CarbonInterface $inicio,
        public ?CarbonInterface $fin,
        public CarbonInterface $ahora,
    ) {
    }
}
