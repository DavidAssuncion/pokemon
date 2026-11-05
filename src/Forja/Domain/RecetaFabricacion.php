<?php

declare(strict_types=1);

namespace Src\Forja\Domain;

use InvalidArgumentException;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Items\CategoriaItem;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Receta estática de fabricación. `clave` es el id de la receta y puede
 * diferir de `resultadoClave` cuando varias recetas producen el mismo item.
 */
final readonly class RecetaFabricacion
{
    /**
     * @throws ItemDesconocido|InvalidArgumentException
     */
    public function __construct(
        public string $clave,
        public string $resultadoClave,
        public int $resultadoCantidad,
        public MapaConteo $ingredientes,
        public CategoriaItem $categoriaResultado,
        public string $etiqueta,
    ) {
        if ($resultadoCantidad !== 1) {
            throw new InvalidArgumentException('La cantidad de resultado debe ser 1 en esta fase.');
        }

        if (! CatalogoItems::existe($resultadoClave)) {
            throw ItemDesconocido::paraClave($resultadoClave);
        }

        foreach ($ingredientes->toArray() as $ingrediente => $cantidad) {
            if (! CatalogoItems::existe($ingrediente)) {
                throw ItemDesconocido::paraClave($ingrediente);
            }

            if ($cantidad <= 0) {
                throw new InvalidArgumentException("Cantidad inválida para el ingrediente {$ingrediente}.");
            }
        }
    }
}
