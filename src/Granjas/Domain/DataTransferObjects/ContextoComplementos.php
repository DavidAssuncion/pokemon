<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

/**
 * Contexto para calcular las mejoras de complementos de una familia.
 */
final readonly class ContextoComplementos
{
    public function __construct(
        public int $refugioNivel,
        public bool $insalubres,
        public InventarioCaramelosGranja $inventario,
        public int $saldoFamilia,
    ) {
    }
}
