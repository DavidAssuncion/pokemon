<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\DataTransferObjects;

use Src\Gimnasios\Domain\Collections\EtapaGimnasioCollection;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Detalle de un gimnasio para la vista pública
 * (ObtenerGimnasioDetalle::obtener()).
 *
 * Sustituye el array {slug, medalla, tipo, ..., etapas} que devolvía el caso
 * de uso (deuda F6). toArray() solo se usa en la frontera (JSON / vista).
 */
final readonly class DetalleGimnasio
{
    public function __construct(
        public readonly string $slug,
        public readonly string $medalla,
        public readonly TipoPokemon $tipo,
        public readonly int $nivelMinimo,
        public readonly int $nivelJugador,
        public readonly int $etapaActual,
        public readonly string $estado,
        public readonly EtapaGimnasioCollection $etapas,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo (claves snake_case).
     *
     * @return array{slug: string, medalla: string, tipo: int, nivel_minimo: int, nivel_jugador: int, etapa_actual: int, estado: string, etapas: list<array{etapa: int, nombre: string}>}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'medalla' => $this->medalla,
            'tipo' => $this->tipo->value,
            'nivel_minimo' => $this->nivelMinimo,
            'nivel_jugador' => $this->nivelJugador,
            'etapa_actual' => $this->etapaActual,
            'estado' => $this->estado,
            'etapas' => $this->etapas->toArray(),
        ];
    }
}
