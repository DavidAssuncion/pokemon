<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Collections\EfectosSecundariosCollection;
use Src\Battle\Domain\Collections\FlagsMovimientoCollection;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\ValueObjects\GolpesMovimiento;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Entidad de catálogo de un movimiento.
 *
 * Es la fuente de verdad persistida; `MovimientoBatalla` es la instantánea
 * inmutable que consume el motor. La conversión la hace
 * `Movimiento::aMovimientoBatalla()`.
 */
class Movimiento
{
    public function __construct(
        public int $id,
        public string $slug,
        public ?int $num,
        public string $nombre,
        public ?string $descripcion,
        public TipoPokemon $tipo,
        public CategoriaMovimiento $categoria,
        public int $potencia,
        public ?int $precision,
        public int $prioridad,
        public ObjetivoMovimiento $objetivo,
        public int $ratioCritico,
        public bool $criticoSeguro,
        public EstadoPokemon $estado,
        public int $probabilidadEstado,
        public GolpesMovimiento $golpes,
        public bool $multiaccuracy,
        public int $drenajePorcentaje,
        public int $recoilPorcentaje,
        public int $curacionPorcentaje,
        public FlagsMovimientoCollection $flags,
        public EfectosSecundariosCollection $secundarios,
        public EfectosSecundariosCollection $autoEfectos,
        public CambiosStatsCollection $cambiosPropios,
        public CambiosStatsCollection $cambiosObjetivo,
        public ?ClaveEfectoMovimiento $claveEfecto,
        public PerfilMovimiento $perfil,
        public int $nivelRequerido = 1,
    ) {
    }

    /**
     * Instantánea para el motor de batalla. Conserva el perfil ya normalizado
     * por la factoría (precisión, probabilidades, golpes, drenaje, etc.).
     */
    public function aMovimientoBatalla(): MovimientoBatalla
    {
        return new MovimientoBatalla(
            nombre: $this->nombre,
            potencia: $this->potencia,
            tipo: $this->tipo,
            categoria: $this->categoria,
            statusEffect: $this->estado,
            priority: $this->prioridad,
            selfStatChanges: $this->cambiosPropios,
            targetStatChanges: $this->cambiosObjetivo,
            perfil: $this->perfil,
            objetivo: $this->objetivo,
            flags: $this->flags,
            secundarios: $this->secundarios,
            autoEfectos: $this->autoEfectos,
            nivelRequerido: $this->nivelRequerido,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'num' => $this->num,
            'name' => $this->nombre,
            'description' => $this->descripcion,
            'type_id' => $this->tipo->value,
            'category' => $this->categoria->value,
            'base_power' => $this->potencia,
            'nivel_requerido' => $this->nivelRequerido,
            'accuracy' => $this->precision,
            'priority' => $this->prioridad,
            'target' => $this->objetivo->value,
            'crit_ratio' => $this->ratioCritico,
            'will_crit' => $this->criticoSeguro,
            'status_effect' => $this->estado->value,
            'status_chance' => $this->probabilidadEstado,
            'multihit_min' => $this->golpes->minimo,
            'multihit_max' => $this->golpes->maximo,
            'multiaccuracy' => $this->multiaccuracy,
            'drain_percent' => $this->drenajePorcentaje,
            'recoil_percent' => $this->recoilPorcentaje,
            'heal_percent' => $this->curacionPorcentaje,
            'effect_key' => $this->claveEfecto?->value,
            'flags' => $this->flags->toArray(),
            'secondaries' => array_map(
                static fn ($efecto): array => $efecto->toArray(),
                $this->secundarios->all(),
            ),
            'self_effects' => array_map(
                static fn ($efecto): array => $efecto->toArray(),
                $this->autoEfectos->all(),
            ),
            'perfil' => $this->perfil->toArray(),
        ];
    }
}
