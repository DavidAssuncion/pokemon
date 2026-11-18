<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\DataTransferObjects;

use Src\Shared\Domain\Items\ClavePieza;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Receta generativa de una pieza para un nido concreto.
 *
 * Varios nidos de la misma familia producen recetas distintas con el mismo
 * resultado; la clave incluye el hábitat para distinguirlas.
 */
final readonly class RecetaPieza
{
    public function __construct(
        public string $clave,
        public DefinicionPieza $definicion,
        public NidoConstruido $nido,
        public ?ClavePieza $piezaAnterior,
        public ?string $piezaAnteriorNombre,
        public MapaConteo $ingredientes,
        public string $etiqueta,
    ) {
    }

    public function resultado(): ClavePieza
    {
        return $this->definicion->clavePieza();
    }
}
