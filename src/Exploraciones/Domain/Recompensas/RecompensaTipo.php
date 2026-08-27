<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

use Src\Shared\Domain\SlugTipo;

/**
 * Caramelos de tipo obtenidos al derrotar pokémon.
 */
final class RecompensaTipo
{
    public function __construct(
        public readonly string $tipo,
        public readonly int $cantidad,
    ) {
    }

    /**
     * Nombre de archivo (sin acentos, minúsculas) para la imagen del caramelo:
     * 'Eléctrico' → 'electrico', 'Dragón' → 'dragon'.
     */
    public function slug(): string
    {
        return SlugTipo::de($this->tipo);
    }
}
