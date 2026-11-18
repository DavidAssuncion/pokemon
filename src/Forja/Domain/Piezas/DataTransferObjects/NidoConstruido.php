<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\DataTransferObjects;

use Src\Shared\Domain\Items\ClaveBonguri;

/**
 * Nido construido del jugador con los datos de su hábitat y bonguri.
 *
 * Es el DTO de frontera del puerto `NidosConstruidos`: Forja no conoce las
 * tablas de Granjas, solo recibe esta proyección.
 */
final readonly class NidoConstruido
{
    public function __construct(
        public int $farmId,
        public int $habitatId,
        public string $habitatNombre,
        public int $evolutionChainId,
        public int $nivelNido,
        public ClaveBonguri $bonguri,
    ) {
    }
}
