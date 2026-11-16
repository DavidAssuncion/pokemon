<?php

declare(strict_types=1);

namespace App\Support\Pc;

use Src\Shared\Domain\Pc\ComparacionPc;

/**
 * Respuesta de preview del rival: comparación de PC, leyenda IV/EV y el
 * identificador del rival fijado (solo en ruta).
 */
final readonly class VistaPreviaRival
{
    public function __construct(
        public ComparacionPc $comparacion,
        public LeyendaIvsEvs $leyenda,
        public ?string $previewId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->comparacion->toArray(), [
            'preview_id' => $this->previewId,
            'perfil_ivs_evs' => $this->leyenda->toArray(),
        ]);
    }
}
