<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

use InvalidArgumentException;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Items\CatalogoItems;

/**
 * Cantidad de un item del catálogo obtenida como botín.
 *
 * La clave se valida contra `CatalogoItems` para impedir que claves
 * desconocidas lleguen al inventario; la cantidad debe ser positiva.
 */
final readonly class RecompensaItem
{
    /**
     * @throws ItemDesconocido si la clave no existe en el catálogo
     * @throws InvalidArgumentException si la cantidad no es positiva
     */
    public function __construct(
        public string $clave,
        public int $cantidad,
    ) {
        if (! CatalogoItems::existe($this->clave)) {
            throw ItemDesconocido::paraClave($this->clave);
        }

        if ($this->cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad de un item de botín debe ser mayor que cero.');
        }
    }
}
