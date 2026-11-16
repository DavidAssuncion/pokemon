<?php

declare(strict_types=1);

namespace App\Support\Pc;

/**
 * PC agregado del equipo del jugador.
 *
 * `pc` solo se rellena cuando `completo` es true; un equipo incompleto no
 * expone números para no comparar totales parciales.
 */
final readonly class PcEquipoJugador
{
    public function __construct(
        public int $miembros,
        public ?int $pc,
        public bool $completo,
    ) {
    }
}
