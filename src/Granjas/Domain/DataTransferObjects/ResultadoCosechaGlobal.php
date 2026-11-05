<?php

declare(strict_types=1);

namespace Src\Granjas\Domain\DataTransferObjects;

use Src\Granjas\Domain\Collections\BayasCosechadasCollection;
use Src\Granjas\Domain\Collections\ErroresCosechaCollection;

/**
 * Agregado de una cosecha global: totales, bayas obtenidas y errores parciales
 * por nido.
 */
final readonly class ResultadoCosechaGlobal
{
    public function __construct(
        public int $totalBayas,
        public int $totalPlantaciones,
        public int $nidos,
        public BayasCosechadasCollection $bayas,
        public ErroresCosechaCollection $errores,
    ) {
    }

    public static function vacio(): self
    {
        return new self(
            totalBayas: 0,
            totalPlantaciones: 0,
            nidos: 0,
            bayas: new BayasCosechadasCollection(),
            errores: new ErroresCosechaCollection(),
        );
    }

    /**
     * @return array{
     *     total_bayas:int,
     *     total_plantaciones:int,
     *     nidos:int,
     *     bayas:list<array<string, mixed>>,
     *     errores:list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'total_bayas' => $this->totalBayas,
            'total_plantaciones' => $this->totalPlantaciones,
            'nidos' => $this->nidos,
            'bayas' => $this->bayas->toArray(),
            'errores' => $this->errores->toArray(),
        ];
    }
}
