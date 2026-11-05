<?php

declare(strict_types=1);

namespace Src\Forja\App\DataTransferObjects;

use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Resultado de fabricar una receta y saldos actualizados del jugador.
 */
final readonly class ResultadoFabricacion
{
    public function __construct(
        public string $recetaClave,
        public string $resultadoClave,
        public int $cantidad,
        public MapaConteo $saldos,
    ) {
    }

    /**
     * Frontera — shape consumido por la API.
     *
     * @return array{
     *     receta: string,
     *     resultado: string,
     *     cantidad: int,
     *     saldos: array<string, int>
     * }
     */
    public function toArray(): array
    {
        return [
            'receta' => $this->recetaClave,
            'resultado' => $this->resultadoClave,
            'cantidad' => $this->cantidad,
            'saldos' => $this->saldos->toArray(),
        ];
    }
}
