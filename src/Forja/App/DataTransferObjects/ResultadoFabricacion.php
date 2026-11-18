<?php

declare(strict_types=1);

namespace Src\Forja\App\DataTransferObjects;

use Src\Forja\Domain\Piezas\DataTransferObjects\DefinicionPieza;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Resultado de fabricar una receta y saldos actualizados del jugador.
 *
 * `pieza` solo se rellena al fabricar una receta de pieza; las recetas
 * estáticas mantienen la respuesta histórica sin la clave.
 */
final readonly class ResultadoFabricacion
{
    public function __construct(
        public string $recetaClave,
        public string $resultadoClave,
        public int $cantidad,
        public MapaConteo $saldos,
        public ?DefinicionPieza $pieza = null,
    ) {
    }

    /**
     * Frontera — shape consumido por la API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $datos = [
            'receta' => $this->recetaClave,
            'resultado' => $this->resultadoClave,
            'cantidad' => $this->cantidad,
            'saldos' => $this->saldos->toArray(),
        ];

        if ($this->pieza !== null) {
            $datos['pieza'] = [
                'clave' => $this->pieza->clave(),
                'stats' => $this->pieza->bonos->toArray(),
                'habilidad' => $this->pieza->habilidadClave === null
                    ? null
                    : [
                        'clave' => $this->pieza->habilidadClave,
                        'nombre' => $this->pieza->habilidadNombre,
                        'descripcion' => $this->pieza->habilidadDescripcion,
                    ],
            ];
        }

        return $datos;
    }
}
