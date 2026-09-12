<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Carga los miembros de las cadenas evolutivas implicadas por
 * evolution_chain_id. Sustituye a la relación de la antigua tabla
 * evolution_chains (eliminada, bug 23503): mismo criterio (columna
 * evolution_chain_id) y cubre cadenas huérfanas con fase 1 en vez de error.
 *
 * Compartido por FinalizarExploracionHandler, OtorgarRecompensasEntrenador y
 * ReclutamientoController (antes un método privado duplicado en cada uno).
 */
final class CadenasEvolutivas
{
    /**
     * Mapa de TODOS los miembros de las cadenas dadas, keyed por
     * evolution_chain_id (columna). Incluye los miembros no derrotados para
     * preservar fase y base de familia.
     *
     * @param  iterable<mixed>  $chainIds  valores evolution_chain_id (pueden venir
     *                                      mezclados/null desde distintos orígenes)
     * @return array<int, Collection<int, Pokemon>>
     */
    public static function miembrosDe(iterable $chainIds): array
    {
        $ids = collect($chainIds)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $query = Pokemon::query();
        $query->getQuery()->whereIn('evolution_chain_id', $ids);

        return $query->get(['id', 'name', 'species_id', 'evolution_chain_id'])
            ->groupBy('evolution_chain_id')
            ->all();
    }
}
