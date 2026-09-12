<?php

declare(strict_types=1);

namespace Src\Battle\Presentation;

use Livewire\Wireable;
use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Shared\Tipos\TipoPokemon;

/**
 * DTO de presentación para MovimientoBatalla.
 * Implementa Wireable para Livewire sin acoplar la entidad de dominio.
 */
class DTOMovimientoBatalla implements Wireable
{
    public function __construct(
        public readonly string $nombre,
        public readonly int $potencia,
        public readonly string $tipo,
        public readonly string $categoria,
        public readonly string $statusEffect = '',
        public readonly int $priority = 0,
        /** @var array<array{stat: string, factor: float}> */
        public readonly array $selfStatChanges = [],
        /** @var array<array{stat: string, factor: float}> */
        public readonly array $targetStatChanges = [],
    ) {
    }

    public static function desdeDominio(MovimientoBatalla $move): self
    {
        return new self(
            nombre: $move->nombre,
            potencia: $move->potencia,
            tipo: (string) $move->tipo->value,
            categoria: $move->categoria->value,
            statusEffect: $move->statusEffect->value,
            priority: $move->priority,
            selfStatChanges: self::cambiosParaArray($move->selfStatChanges),
            targetStatChanges: self::cambiosParaArray($move->targetStatChanges),
        );
    }

    /**
     * @return array{
     *     nombre: string,
     *     potencia: int,
     *     tipo: string,
     *     categoria: string,
     *     statusEffect: string,
     *     priority: int,
     *     selfStatChanges: array<int, array{stat: string, factor: float}>,
     *     targetStatChanges: array<int, array{stat: string, factor: float}>,
     * }
     */
    public function toLivewire(): array
    {
        return [
            'nombre' => $this->nombre,
            'potencia' => $this->potencia,
            'tipo' => $this->tipo,
            'categoria' => $this->categoria,
            'statusEffect' => $this->statusEffect,
            'priority' => $this->priority,
            'selfStatChanges' => $this->selfStatChanges,
            'targetStatChanges' => $this->targetStatChanges,
        ];
    }

    /**
     * @param  mixed  $value  Valor hidratado por Livewire (array plano tolerante).
     */
    public static function fromLivewire($value): self
    {
        return new self(
            nombre: $value['nombre'],
            potencia: $value['potencia'],
            tipo: $value['tipo'],
            categoria: $value['categoria'],
            statusEffect: $value['statusEffect'] ?? '',
            priority: $value['priority'] ?? 0,
            selfStatChanges: $value['selfStatChanges'] ?? [],
            targetStatChanges: $value['targetStatChanges'] ?? [],
        );
    }

    public function toDomain(): MovimientoBatalla
    {
        return new MovimientoBatalla(
            nombre: $this->nombre,
            potencia: $this->potencia,
            tipo: TipoPokemon::from((int) $this->tipo),
            categoria: CategoriaMovimiento::from($this->categoria),
            statusEffect: $this->statusEffect !== '' ? EstadoPokemon::from($this->statusEffect) : EstadoPokemon::NONE,
            priority: $this->priority,
            selfStatChanges: self::cambiosDesdeArray($this->selfStatChanges),
            targetStatChanges: self::cambiosDesdeArray($this->targetStatChanges),
        );
    }

    /**
     * @return array<int, array{stat: string, factor: float}>
     */
    private static function cambiosParaArray(CambiosStatsCollection $cambios): array
    {
        $resultado = [];
        foreach ($cambios as $cambio) {
            $resultado[] = ['stat' => $cambio->statValue(), 'factor' => $cambio->factor];
        }

        return $resultado;
    }

    /**
     * Acepta el formato actual ({stat, factor}) y el legacy v9 ({stat, stages}).
     *
     * @param  array<int, array<string, mixed>>  $cambios
     */
    private static function cambiosDesdeArray(array $cambios): CambiosStatsCollection
    {
        $items = [];
        foreach ($cambios as $cambio) {
            $stat = StatClave::tryFrom((string) ($cambio['stat'] ?? ''));
            if ($stat === null) {
                continue;
            }

            if (array_key_exists('factor', $cambio)) {
                $items[] = new CambioStat($stat, (float) $cambio['factor']);
            } elseif (array_key_exists('stages', $cambio)) {
                $items[] = CambioStat::desdeEtapas($stat, (int) $cambio['stages']);
            }
        }

        return new CambiosStatsCollection($items);
    }
}
