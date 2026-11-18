<?php

declare(strict_types=1);

namespace Src\Forja\App\DataTransferObjects;

use Src\Forja\App\Collections\IngredientesDetalleCollection;
use Src\Forja\Domain\Piezas\DataTransferObjects\DefinicionPieza;
use Src\Forja\Domain\Piezas\DataTransferObjects\NidoConstruido;
use Src\Shared\Domain\Items\ClavePieza;

/**
 * Receta con el resultado resuelto y el estado de fabricación del jugador.
 *
 * Las recetas estáticas solo exponen los campos históricos más `categoria` y
 * `motivo_texto`; los campos de pieza se añaden únicamente cuando la receta es
 * generativa. `categoria` es la clave de sección de la Forja (`piezas`,
 * `medicinas`, `mejoras`, `reductores`, `resistencias`), no la `CategoriaItem`
 * del resultado.
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
        public string $categoria,
        public ?string $motivoTexto = null,
        public ?DefinicionPieza $definicionPieza = null,
        public ?NidoConstruido $nido = null,
        public ?ClavePieza $piezaAnterior = null,
        public ?string $piezaAnteriorNombre = null,
        public ?bool $desbloqueada = null,
    ) {
    }

    /**
     * Frontera — shape consumido por la API/UI.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $ingredientes = [];

        foreach ($this->ingredientes->toList() as $ingrediente) {
            if ($ingrediente instanceof IngredienteDetalle) {
                $ingredientes[] = $ingrediente->toArray();
            }
        }

        $datos = [
            'clave' => $this->clave,
            'resultado_clave' => $this->resultadoClave,
            'resultado_nombre' => $this->resultadoNombre,
            'resultado_icono' => $this->resultadoIcono,
            'resultado_cantidad' => $this->resultadoCantidad,
            'etiqueta' => $this->etiqueta,
            'ingredientes' => $ingredientes,
            'puede_fabricar' => $this->puedeFabricar,
            'motivo' => $this->motivo,
            'motivo_texto' => $this->motivoTexto,
            'categoria' => $this->categoria,
        ];

        if ($this->definicionPieza !== null && $this->nido !== null) {
            $datos = array_merge($datos, $this->datosPieza());
        }

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPieza(): array
    {
        $pieza = $this->definicionPieza;
        $nido = $this->nido;

        if ($pieza === null || $nido === null) {
            return [];
        }

        return [
            'tipo_pieza' => $pieza->tipo->value,
            'familia' => [
                'evolution_chain_id' => $pieza->evolutionChainId,
                'nombre' => $pieza->familiaNombre,
                'primer_integrante_id' => $pieza->familiaPrimerIntegranteId,
            ],
            'especie' => [
                'id' => $pieza->speciesId,
                'nombre' => $pieza->speciesNombre,
                'etapa' => $pieza->etapa,
                'profundidad_familia' => $pieza->profundidadFamilia,
            ],
            'nivel_nido_requerido' => $pieza->nivelNidoRequerido,
            'nivel_nido_actual' => $nido->nivelNido,
            'desbloqueada' => $this->desbloqueada ?? false,
            'habitat' => [
                'id' => $nido->habitatId,
                'nombre' => $nido->habitatNombre,
            ],
            'bonguri' => [
                'clave' => $nido->bonguri->clave(),
                'nombre' => $nido->bonguri->nombre(),
                'icono' => $nido->bonguri->archivoIcono(),
                'origen' => $nido->bonguri->origen(),
            ],
            'stats' => $pieza->bonos->toArray(),
            'habilidad' => $pieza->habilidadClave === null
                ? null
                : [
                    'clave' => $pieza->habilidadClave,
                    'nombre' => $pieza->habilidadNombre,
                    'descripcion' => $pieza->habilidadDescripcion,
                ],
            'pieza_anterior' => $this->piezaAnterior === null
                ? null
                : [
                    'clave' => $this->piezaAnterior->clave(),
                    'nombre' => $this->piezaAnteriorNombre,
                ],
        ];
    }
}
