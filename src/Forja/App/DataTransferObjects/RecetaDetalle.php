<?php

declare(strict_types=1);

namespace Src\Forja\App\DataTransferObjects;

use Src\Forja\App\Collections\IngredientesDetalleCollection;

/**
 * Receta con el resultado resuelto y el estado de fabricación del jugador.
 */
final readonly class RecetaDetalle
{
    public function __construct(
        public string $clave,
        public string $resultadoClave,
        public string $resultadoNombre,
        public string $resultadoIcono,
        public int $resultadoCantidad,
        public IngredientesDetalleCollection $ingredientes,
        public bool $puedeFabricar,
        public ?string $motivo,
        public string $etiqueta,
    ) {
    }

    /**
     * Frontera — shape consumido por la API/UI.
     *
     * @return array{
     *     clave: string,
     *     resultado_clave: string,
     *     resultado_nombre: string,
     *     resultado_icono: string,
     *     resultado_cantidad: int,
     *     etiqueta: string,
     *     ingredientes: list<array{clave: string, nombre: string, icono: string, requerido: int, disponible: int}>,
     *     puede_fabricar: bool,
     *     motivo: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'clave' => $this->clave,
            'resultado_clave' => $this->resultadoClave,
            'resultado_nombre' => $this->resultadoNombre,
            'resultado_icono' => $this->resultadoIcono,
            'resultado_cantidad' => $this->resultadoCantidad,
            'etiqueta' => $this->etiqueta,
            'ingredientes' => $this->ingredientes->map(
                static fn (IngredienteDetalle $ingrediente): array => $ingrediente->toArray(),
            ),
            'puede_fabricar' => $this->puedeFabricar,
            'motivo' => $this->motivo,
        ];
    }
}
