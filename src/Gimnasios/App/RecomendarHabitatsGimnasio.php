<?php

declare(strict_types=1);

namespace Src\Gimnasios\App;

use App\Models\Habitat;
use Illuminate\Support\Facades\DB;
use Src\Gimnasios\Domain\Collections\HabitatRecomendadoCollection;
use Src\Gimnasios\Domain\DataTransferObjects\HabitatRecomendado;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Sugiere hábitats útiles para preparar un gimnasio a partir de los tipos del
 * pool real (`pokemon_types`): cuenta los Pokémon con al menos un tipo
 * supereficaz (×2 o más) contra el tipo del gimnasio y ordena por número de
 * coincidencias y nivel mínimo.
 *
 * Es una pista de progresión, no un bloqueo: todos los hábitats siguen
 * abiertos.
 */
final class RecomendarHabitatsGimnasio
{
    /** Número máximo de hábitats sugeridos. */
    private const MAX_RECOMENDADOS = 3;

    /** Multiplicador mínimo para considerar un tipo supereficaz (tabla propia: ×1.5). */
    private const MULTIPLICADOR_SUPEREFICAZ = 1.5;

    public function recomendar(TipoPokemon $tipoRival): HabitatRecomendadoCollection
    {
        $filas = DB::table('pokemon_habitat as ph')
            ->join('pokemon as p', 'p.id', '=', 'ph.pokemon_id')
            ->join('pokemon_types as pt', 'pt.pokemon_id', '=', 'p.id')
            ->join('habitats as h', 'h.id', '=', 'ph.habitat_id')
            ->join('provinces as pr', 'pr.id', '=', 'h.province_id')
            ->select([
                'ph.habitat_id',
                'ph.pokemon_id',
                'ph.min_level',
                'pt.type as tipo',
                'h.name as habitat_nombre',
                'pr.name as provincia_nombre',
            ])
            ->get();

        /** @var array<int, array{nombre: string, provincia: string, total: array<int, bool>, super: array<int, bool>, minimo: int|null}> $datos */
        $datos = [];

        foreach ($filas as $fila) {
            $habitatId = (int) $fila->habitat_id;
            $pokemonId = (int) $fila->pokemon_id;

            if (Habitat::sinSpawns($habitatId)) {
                continue;
            }

            $datos[$habitatId] ??= [
                'nombre' => (string) $fila->habitat_nombre,
                'provincia' => (string) $fila->provincia_nombre,
                'total' => [],
                'super' => [],
                'minimo' => null,
            ];

            $datos[$habitatId]['total'][$pokemonId] = true;

            $tipo = TipoPokemon::from((int) $fila->tipo);
            if ($tipo->effectivenessAgainst($tipoRival) < self::MULTIPLICADOR_SUPEREFICAZ) {
                continue;
            }

            $datos[$habitatId]['super'][$pokemonId] = true;

            $minimo = (int) ($fila->min_level ?? 0);
            if ($minimo > 0) {
                $datos[$habitatId]['minimo'] = $datos[$habitatId]['minimo'] === null
                    ? $minimo
                    : min($datos[$habitatId]['minimo'], $minimo);
            }
        }

        $recomendados = [];

        foreach ($datos as $habitatId => $dato) {
            $coincidencias = count($dato['super']);
            if ($coincidencias === 0) {
                continue;
            }

            $recomendados[] = new HabitatRecomendado(
                habitatId: $habitatId,
                nombre: $dato['nombre'],
                provincia: $dato['provincia'],
                coincidencias: $coincidencias,
                total: count($dato['total']),
                nivelMinimo: $dato['minimo'] ?? 1,
            );
        }

        usort($recomendados, static function (HabitatRecomendado $a, HabitatRecomendado $b): int {
            $porCoincidencias = $b->coincidencias <=> $a->coincidencias;
            if ($porCoincidencias !== 0) {
                return $porCoincidencias;
            }

            $porNivel = $a->nivelMinimo <=> $b->nivelMinimo;
            if ($porNivel !== 0) {
                return $porNivel;
            }

            return strcmp($a->nombre, $b->nombre);
        });

        return new HabitatRecomendadoCollection(array_slice($recomendados, 0, self::MAX_RECOMENDADOS));
    }
}
