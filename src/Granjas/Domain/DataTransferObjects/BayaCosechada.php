<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Shared\Domain\Items\CatalogoItems;

/**
 * Baya obtenida en una cosecha global, agregada por clave de inventario.
 */
final readonly class BayaCosechada
{
    public function __construct(
        public string $clave,
        public string $nombre,
        public string $icono,
        public int $cantidad,
    ) {
    }

    /**
     * Construye el DTO resolviendo nombre e icono desde el catálogo compartido.
     */
    public static function desde(string $clave, int $cantidad): self
    {
        $definicion = CatalogoItems::definicion($clave);

        return new self(
            clave: $clave,
            nombre: $definicion->nombre,
            icono: $definicion->icono,
            cantidad: $cantidad,
        );
    }

    /**
     * @return array{clave:string, nombre:string, icono:string, cantidad:int}
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'icono' => $this->icono,
            'cantidad' => $this->cantidad,
        ];
    }
}
