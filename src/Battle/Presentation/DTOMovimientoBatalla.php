<?php

declare(strict_types=1);

namespace Src\Battle\Presentation;

use Livewire\Wireable;
use Src\Battle\Domain\Collections\CambiosStatsCollection;
use Src\Battle\Domain\Collections\EfectosSecundariosCollection;
use Src\Battle\Domain\Collections\FlagsMovimientoCollection;
use Src\Battle\Domain\Enums\CategoriaMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\ObjetivoMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
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
        /** @var array<string, mixed> */
        public readonly array $perfil = [],
        public readonly string $objetivo = 'normal',
        /** @var list<string> */
        public readonly array $flags = [],
        /** @var list<array<string, mixed>> */
        public readonly array $secundarios = [],
        /** @var list<array<string, mixed>> */
        public readonly array $autoEfectos = [],
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
            perfil: $move->perfil->toArray(),
            objetivo: $move->objetivo->value,
            flags: $move->flags->toArray(),
            secundarios: array_map(
                static fn ($efecto): array => $efecto->toArray(),
                $move->secundarios->all(),
            ),
            autoEfectos: array_map(
                static fn ($efecto): array => $efecto->toArray(),
                $move->autoEfectos->all(),
            ),
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
     *     perfil: array<string, mixed>,
     *     objetivo: string,
     *     flags: list<string>,
     *     secundarios: list<array<string, mixed>>,
     *     autoEfectos: list<array<string, mixed>>,
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
            'perfil' => $this->perfil,
            'objetivo' => $this->objetivo,
            'flags' => $this->flags,
            'secundarios' => $this->secundarios,
            'autoEfectos' => $this->autoEfectos,
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
            perfil: is_array($value['perfil'] ?? null) ? $value['perfil'] : [],
            objetivo: is_string($value['objetivo'] ?? null) ? $value['objetivo'] : 'normal',
            flags: is_array($value['flags'] ?? null) ? $value['flags'] : [],
            secundarios: is_array($value['secundarios'] ?? null) ? $value['secundarios'] : [],
            autoEfectos: is_array($value['autoEfectos'] ?? null) ? $value['autoEfectos'] : [],
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
            perfil: PerfilMovimiento::desdeArray($this->perfil),
            objetivo: ObjetivoMovimiento::tryFrom($this->objetivo) ?? ObjetivoMovimiento::NORMAL,
            flags: FlagsMovimientoCollection::desdeArray($this->flags),
            secundarios: EfectosSecundariosCollection::desdeArray($this->secundarios),
            autoEfectos: EfectosSecundariosCollection::desdeArray($this->autoEfectos, true),
        );
    }

    /**
     * @return array<int, array{stat: string, factor: float, etapas: int}>
     */
    private static function cambiosParaArray(CambiosStatsCollection $cambios): array
    {
        $resultado = [];
        foreach ($cambios as $cambio) {
            $resultado[] = [
                'stat' => $cambio->statValue(),
                'factor' => $cambio->factorTemporal(),
                'etapas' => $cambio->etapas,
            ];
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
                $items[] = new CambioStat($stat, (float) $cambio['factor'], (int) ($cambio['etapas'] ?? 0));
            } elseif (array_key_exists('stages', $cambio)) {
                $items[] = CambioStat::desdeEtapas($stat, (int) $cambio['stages']);
            }
        }

        return new CambiosStatsCollection($items);
    }
}
