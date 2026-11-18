<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas\DataTransferObjects;

use Src\Forja\Domain\Piezas\Enums\TipoPieza;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Domain\Items\ClavePieza;

/**
 * Definición generativa de una pieza: identidad, familia, etapa, gate y bonos.
 *
 * No depende de Eloquent: la infraestructura de lectura rellena sus datos y el
 * catálogo la combina con los nidos del jugador para producir recetas.
 */
final readonly class DefinicionPieza
{
    public function __construct(
        public TipoPieza $tipo,
        public int $speciesId,
        public string $speciesNombre,
        public int $evolutionChainId,
        public string $familiaNombre,
        public int $familiaPrimerIntegranteId,
        public int $etapa,
        public int $profundidadFamilia,
        public int $nivelNidoRequerido,
        public ?int $parentSpeciesId,
        public StatsValue $bonos,
        public string $icono,
        public ?string $habilidadClave = null,
        public ?string $habilidadNombre = null,
        public ?string $habilidadDescripcion = null,
    ) {
    }

    public function clave(): string
    {
        return $this->tipo->clave($this->speciesId);
    }

    public function clavePieza(): ClavePieza
    {
        return $this->tipo->aClavePieza($this->speciesId);
    }

    public function nombre(): string
    {
        return $this->tipo->nombre().' de '.$this->speciesNombre;
    }
}
