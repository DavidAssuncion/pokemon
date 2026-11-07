<?php

declare(strict_types=1);

namespace Src\Battle\Domain\ValueObjects;

use Src\Battle\Domain\Enums\TipoEfectoAliado;

/**
 * Efecto de apoyo declarado por un movimiento cuando se usa sobre un aliado.
 *
 * `turnos` solo aplica a efectos con duración propia (Acua Aro); los buffs
 * temporales usan la duración de `MultiplicadoresStats`.
 */
final readonly class EfectoAliado
{
    public function __construct(
        public TipoEfectoAliado $tipo,
        public int $turnos = 0,
    ) {
    }
}
