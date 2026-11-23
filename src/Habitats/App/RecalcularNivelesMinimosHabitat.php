<?php

declare(strict_types=1);

namespace Src\Habitats\App;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Src\Habitats\Domain\CalculadoraNivelMinimoSpawn;

/**
 * Recalcula `pokemon_habitat.min_level` a partir del tier, la evolución y la
 * regla de starters. Respeta las filas marcadas como `manual` (override del
 * administrador).
 *
 * Se ejecuta al asignar una familia y desde el comando
 * `habitats:recalcular-niveles` para una pasada completa.
 */
final class RecalcularNivelesMinimosHabitat
{
    private const CHUNK_SIZE = 500;

    private readonly CalculadoraNivelMinimoSpawn $calculadora;

    public function __construct(?CalculadoraNivelMinimoSpawn $calculadora = null)
    {
        $this->calculadora = $calculadora ?? new CalculadoraNivelMinimoSpawn(
            minLevelPorTier: self::minLevelPorTier(),
            starterMinLevel: (int) config('habitats.starter_min_level', 35),
            starterChainIds: self::idsDeConfig('habitats.starter_chain_ids'),
            starterSpeciesIds: self::idsDeConfig('habitats.starter_species_ids'),
            paradoxMinLevel: (int) config('habitats.paradox_min_level', 55),
            paradoxSpeciesIds: self::idsDeConfig('habitats.paradox_species_ids'),
            speciesMinLevelOverrides: self::mapaDeConfig('habitats.species_min_level_overrides'),
        );
    }

    /**
     * Recalcula todos los hábitats. Devuelve el número de spawns actualizados.
     */
    public function todos(): int
    {
        return $this->recalcular(null);
    }

    /**
     * Recalcula un hábitat concreto. Devuelve el número de spawns actualizados.
     */
    public function paraHabitat(int $habitatId): int
    {
        return $this->recalcular($habitatId);
    }

    private function recalcular(?int $habitatId): int
    {
        $query = DB::table('pokemon_habitat as ph')
            ->join('pokemon as p', 'p.id', '=', 'ph.pokemon_id')
            ->where('ph.manual', false)
            ->select([
                'ph.pokemon_id',
                'ph.habitat_id',
                'ph.level',
                'p.species_id',
                'p.evolution_chain_id',
            ]);

        if ($habitatId !== null) {
            $query->where('ph.habitat_id', $habitatId);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        /** @var Collection<int, int|string> $evoluciones */
        $evoluciones = DB::table('pokemon_evolution')
            ->whereIn('evolved_species_id', $rows->pluck('species_id')->unique()->values()->all())
            ->whereNotNull('minimum_level')
            ->pluck('minimum_level', 'evolved_species_id');

        $registros = [];

        foreach ($rows as $row) {
            $speciesId = (int) $row->species_id;
            $nivelEvolucion = $evoluciones[$speciesId] ?? null;

            $registros[] = [
                'pokemon_id' => (int) $row->pokemon_id,
                'habitat_id' => (int) $row->habitat_id,
                'level' => (int) $row->level,
                'min_level' => $this->calculadora->calcular(
                    tier: (int) $row->level,
                    evolutionChainId: (int) ($row->evolution_chain_id ?? 0),
                    speciesId: $speciesId,
                    nivelEvolucion: $nivelEvolucion !== null ? (int) $nivelEvolucion : null,
                ),
            ];
        }

        DB::transaction(function () use ($registros): void {
            foreach (array_chunk($registros, self::CHUNK_SIZE) as $chunk) {
                DB::table('pokemon_habitat')->upsert(
                    $chunk,
                    ['pokemon_id', 'habitat_id'],
                    ['min_level'],
                );
            }
        });

        return count($registros);
    }

    /**
     * @return array<int, int>
     */
    private static function minLevelPorTier(): array
    {
        /** @var array<int|string, int|string> $tiers */
        $tiers = config('habitats.min_level_por_tier', [1 => 5, 2 => 12, 3 => 20]);

        $resultado = [];
        foreach ($tiers as $tier => $minimo) {
            $resultado[(int) $tier] = (int) $minimo;
        }

        return $resultado;
    }

    /**
     * @return list<int>
     */
    private static function idsDeConfig(string $clave): array
    {
        /** @var array<int, int|string> $ids */
        $ids = config($clave, []);

        return array_values(array_map(static fn (int|string $id): int => (int) $id, $ids));
    }

    /**
     * @return array<int, int>
     */
    private static function mapaDeConfig(string $clave): array
    {
        /** @var array<int|string, int|string> $valores */
        $valores = config($clave, []);

        $resultado = [];
        foreach ($valores as $claveItem => $valor) {
            $resultado[(int) $claveItem] = (int) $valor;
        }

        return $resultado;
    }
}
