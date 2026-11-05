<?php

declare(strict_types=1);

namespace Src\Battle\Domain\DataTransferObjects;

use Src\Battle\Domain\Enums\ObjetivosItem;

/**
 * Opción del panel de objetos de combate.
 *
 * `aplicable` resume si hay stock y al menos un objetivo válido. El motivo
 * queda listo para mostrarse en la UI sin lógica adicional.
 */
final readonly class OpcionObjeto
{
    public function __construct(
        public string $clave,
        public string $nombre,
        public string $categoria,
        public string $icono,
        public string $descripcion,
        public int $cantidad,
        public bool $aplicable,
        public string $motivo,
        public ObjetivosItem $objetivos,
    ) {
    }

    /**
     * @return array{
     *     clave: string,
     *     nombre: string,
     *     categoria: string,
     *     icono: string,
     *     descripcion: string,
     *     cantidad: int,
     *     aplicable: bool,
     *     motivo: string,
     *     objetivos: string,
     *     objetivos_label: string
     * }
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'categoria' => $this->categoria,
            'icono' => $this->icono,
            'descripcion' => $this->descripcion,
            'cantidad' => $this->cantidad,
            'aplicable' => $this->aplicable,
            'motivo' => $this->motivo,
            'objetivos' => $this->objetivos->value,
            'objetivos_label' => $this->objetivos->label(),
        ];
    }
}
