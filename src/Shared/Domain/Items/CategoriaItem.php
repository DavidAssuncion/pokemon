<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

/**
 * Categorías del catálogo de items. Las categorías posteriores a bayas y
 * bonguris quedan preparadas para fases futuras; no implican implementación.
 */
enum CategoriaItem: string
{
    case BAYA = 'baya';
    case BONGURI = 'bonguri';
    case POCION = 'pocion';
    case MATERIAL = 'material';
    case MEJORA = 'mejora';
    case REDUCTOR = 'reductor';
    case RESISTENCIA = 'resistencia';

    /**
     * Prefijo canónico de las claves de inventario de esta categoría.
     */
    public function prefijo(): string
    {
        return $this->value.':';
    }
}
