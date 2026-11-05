<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Shared\Domain\Items\ClaveBonguri;

/**
 * Bonguri asignado al hábitat, con el fallback blanco de F1 cuando la
 * provincia no tiene color configurado.
 */
final readonly class BonguriGranja
{
    public function __construct(
        public string $clave,
        public string $nombre,
        public string $icono,
    ) {
    }

    public static function desde(ClaveBonguri $bonguri): self
    {
        return new self(
            clave: $bonguri->clave(),
            nombre: $bonguri->nombre(),
            icono: $bonguri->archivoIcono(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'icono' => $this->icono,
        ];
    }
}
