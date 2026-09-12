<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain\DataTransferObjects;

/**
 * Resultado de registrar la victoria/derrota de un combate de gimnasio
 * (RegistrarResultadoGimnasio::registrar()).
 *
 * Sustituye el array {avance, completado, medalla} (deuda F6).
 */
final readonly class ResultadoGimnasio
{
    public function __construct(
        public readonly bool $avance,
        public readonly bool $completado,
        public readonly ?string $medalla,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo.
     *
     * @return array{avance: bool, completado: bool, medalla: string|null}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'avance' => $this->avance,
            'completado' => $this->completado,
            'medalla' => $this->medalla,
        ];
    }
}
