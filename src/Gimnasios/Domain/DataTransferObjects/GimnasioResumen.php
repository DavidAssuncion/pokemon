<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\DataTransferObjects;

use Src\Shared\Tipos\TipoPokemon;

/**
 * Resumen de un gimnasio para el listado público
 * (ObtenerGimnasios::obtener()).
 *
 * Sustituye el array {slug, medalla, tipo, nivel_minimo, ...} que devolvía el
 * caso de uso (deuda F6). toArray() solo se usa en la frontera (JSON / vista).
 */
final readonly class GimnasioResumen
{
    public function __construct(
        public readonly string $slug,
        public readonly string $medalla,
        public readonly TipoPokemon $tipo,
        public readonly string $tipoNombre,
        public readonly string $tipoSlug,
        public readonly string $tipoMedalla,
        public readonly string $tipoColor,
        public readonly int $nivelMinimo,
        public readonly int $nivelJugador,
        public readonly int $etapaActual,
        public readonly string $estado,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo (claves snake_case).
     *
     * @return array{slug: string, medalla: string, tipo: int, tipo_nombre: string, tipo_slug: string, tipo_medalla: string, tipo_color: string, nivel_minimo: int, nivel_jugador: int, etapa_actual: int, estado: string}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'medalla' => $this->medalla,
            'tipo' => $this->tipo->value,
            'tipo_nombre' => $this->tipoNombre,
            'tipo_slug' => $this->tipoSlug,
            'tipo_medalla' => $this->tipoMedalla,
            'tipo_color' => $this->tipoColor,
            'nivel_minimo' => $this->nivelMinimo,
            'nivel_jugador' => $this->nivelJugador,
            'etapa_actual' => $this->etapaActual,
            'estado' => $this->estado,
        ];
    }
}
