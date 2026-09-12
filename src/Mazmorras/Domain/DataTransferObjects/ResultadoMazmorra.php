<?php

declare(strict_types=1);

namespace Src\Mazmorras\Domain\DataTransferObjects;

/**
 * Resultado de registrar el combate de un piso de mazmorra
 * (RegistrarResultadoMazmorra::registrar()).
 *
 * Sustituye el array {avance, completado} (deuda F6).
 */
final readonly class ResultadoMazmorra
{
    public function __construct(
        public readonly bool $avance,
        public readonly bool $completado,
    ) {
    }

    /**
     * Frontera — shape exacto del contrato previo.
     *
     * @return array{avance: bool, completado: bool}
     *
     * @deprecated Usar las propiedades tipadas.
     */
    public function toArray(): array
    {
        return [
            'avance' => $this->avance,
            'completado' => $this->completado,
        ];
    }
}
