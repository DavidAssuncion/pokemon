<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\ValueObjects;

/**
 * Value Object inmutable con la bitácora de una exploración: secuencia de
 * eventos (encuentros, hallazgos, emboscadas, contratiempos, descansos).
 */
final readonly class BitacoraExploracion
{
    public function __construct(
        public readonly ColeccionEventosExploracion $eventos,
    ) {
    }

    /**
     * Frontera — lectura del contrato persistido de una bitácora.
     *
     * @param  list<array<string, mixed>>  $bitacora
     */
    public static function desdeArray(array $bitacora): self
    {
        return new self(ColeccionEventosExploracion::desdeArray($bitacora));
    }

    /**
     * Frontera — shape exacto del contrato previo de la bitácora.
     *
     * @return list<array<string, mixed>>
     *
     * @deprecated Usar ->eventos.
     */
    public function aArray(): array
    {
        return $this->eventos->aArrays();
    }

    /**
     * Número de eventos de la bitácora.
     */
    public function tamano(): int
    {
        return $this->eventos->tamano();
    }
}
