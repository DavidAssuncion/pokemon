<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain\Collections;

use Src\Mazmorras\Domain\DataTransferObjects\PisoConfiguracion;
use Src\Shared\Domain\Collection;

/**
 * Colección ordenada por número de piso de la configuración de una mazmorra.
 */
final class PisoConfiguracionCollection extends Collection
{
    public string $type = PisoConfiguracion::class;

    /**
     * @param  list<PisoConfiguracion>  $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct($items);
    }

    /** @return list<PisoConfiguracion> */
    public function all(): array
    {
        return $this->items;
    }

    public function porNumero(int $piso): ?PisoConfiguracion
    {
        foreach ($this->items as $configuracion) {
            if ($configuracion->piso === $piso) {
                return $configuracion;
            }
        }

        return null;
    }
}
