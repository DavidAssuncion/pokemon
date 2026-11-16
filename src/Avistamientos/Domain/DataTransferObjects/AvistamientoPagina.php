<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\DataTransferObjects;

/**
 * Datos ya resueltos para la página de avistamientos.
 *
 * La presentación solo formatea; los textos, el estado «sin efecto» y la
 * agrupación por niveles llegan calculados desde aplicación.
 */
final class AvistamientoPagina
{
    public function __construct(
        public readonly bool $activo,
        public readonly ?int $indice,
        public readonly ?int $habitatId,
        public readonly ?string $habitatNombre,
        public readonly ?string $familiaNombre,
        public readonly ?int $evolutionChainId,
        public readonly float $factor,
        public readonly ?string $terminaEn,
        public readonly ?string $restanteTexto,
        public readonly bool $sinEfecto,
        public readonly NivelesAvistamientoCollection $niveles,
    ) {
    }

    /**
     * Estado vacío cuando la base de datos no ofrece ningún candidato.
     */
    public static function vacio(): self
    {
        return new self(
            activo: false,
            indice: null,
            habitatId: null,
            habitatNombre: null,
            familiaNombre: null,
            evolutionChainId: null,
            factor: 1.0,
            terminaEn: null,
            restanteTexto: null,
            sinEfecto: false,
            niveles: new NivelesAvistamientoCollection(),
        );
    }
}
