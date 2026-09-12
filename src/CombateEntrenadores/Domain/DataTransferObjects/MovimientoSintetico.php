<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\DataTransferObjects;

use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Movimiento generado temporalmente a partir de los tipos del pokémon
 * (no hay datos reales de ataques en BD).
 *
 * Sustituye el array {nombre, potencia, tipo, categoria} que devolvía
 * GeneradorMovimientosTipo::generar() (deuda F6).
 */
final readonly class MovimientoSintetico
{
    public function __construct(
        public readonly string $nombre,
        public readonly int $potencia,
        public readonly TipoPokemon $tipo,
        public readonly CategoriaMovimiento $categoria,
    ) {
    }
}
