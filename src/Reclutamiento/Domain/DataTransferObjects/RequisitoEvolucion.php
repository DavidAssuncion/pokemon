<?php

declare(strict_types=1);

namespace Src\Reclutamiento\Domain\DataTransferObjects;

/**
 * Requisito de UN tipo para evolucionar: umbral del nivel actual, exp de tipo
 * del JSON y caramelos disponibles del jugador.
 *
 * Sustituye el array {tipo, slug, necesario, actual, caramelosDisponibles}
 * (deuda F6).
 */
final readonly class RequisitoEvolucion
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $slug,
        public readonly int $necesario,
        public readonly int $actual,
        public readonly int $caramelosDisponibles,
    ) {
    }

    /**
     * True si la exp de tipo acumulada alcanza el umbral.
     */
    public function cumple(): bool
    {
        return $this->actual >= $this->necesario;
    }

    /**
     * Frontera — shape exacto del contrato previo.
     *
     * @return array{tipo: string, slug: string, necesario: int, actual: int, caramelosDisponibles: int}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'slug' => $this->slug,
            'necesario' => $this->necesario,
            'actual' => $this->actual,
            'caramelosDisponibles' => $this->caramelosDisponibles,
        ];
    }
}
