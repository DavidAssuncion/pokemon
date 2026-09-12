<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\Domain\DataTransferObjects;

/**
 * Estado de desbloqueo de un entrenador de hábitat para el listado público
 * (ObtenerEntrenadoresHabitat::obtener()).
 *
 * Sustituye el array {indice, desbloqueado} (deuda F6).
 */
final readonly class EstadoEntrenador
{
    public function __construct(
        public readonly int $indice,
        public readonly bool $desbloqueado,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo.
     *
     * @return array{indice: int, desbloqueado: bool}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'indice' => $this->indice,
            'desbloqueado' => $this->desbloqueado,
        ];
    }
}
