<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Resultado interno de cosechar un único nido: unidades por baya y número de
 * plantaciones que aportaron stock.
 */
final readonly class CosechaNido
{
    public function __construct(
        public MapaConteo $bayas,
        public int $plantaciones,
    ) {
    }

    public static function vacia(): self
    {
        return new self(MapaConteo::vacio(), 0);
    }
}
