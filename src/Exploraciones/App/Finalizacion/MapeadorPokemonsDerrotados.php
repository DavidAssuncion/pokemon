<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Finalizacion;

use App\Models\Pokemon;
use App\Support\CadenasEvolutivas;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\App\NormalizadorPokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\ClasificadorEventosExploracion;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Exploraciones\Domain\ValueObjects\EventoExploracion;

/**
 * Carga desde Eloquent, expande y normaliza los pokémon derrotados de una
 * bitácora, y resuelve el mapa pokemon_id → evolution_chain_id de los
 * hallazgos. Capa App: usa Eloquent por convención del proyecto.
 */
final class MapeadorPokemonsDerrotados
{
    public function __construct(
        private readonly ClasificadorEventosExploracion $clasificador = new ClasificadorEventosExploracion(),
    ) {
    }

    public function mapear(ColeccionEventosExploracion $bitacora): DatosDerrotadosExpedicion
    {
        $idsDerrotados = $this->clasificador->idsDerrotados($bitacora);
        $pokemons = $this->cargarPokemonsDerrotados($idsDerrotados);
        $miembrosPorCadena = CadenasEvolutivas::miembrosDe($pokemons->pluck('evolution_chain_id'));
        $etapasPorCadena = CadenasEvolutivas::etapasDe($pokemons->pluck('evolution_chain_id'));

        return new DatosDerrotadosExpedicion(
            $idsDerrotados,
            $pokemons,
            $miembrosPorCadena,
            $etapasPorCadena,
            NormalizadorPokemonDerrotado::normalizar(
                $this->expandirDerrotados($pokemons, $idsDerrotados),
                $miembrosPorCadena,
                $etapasPorCadena,
            ),
        );
    }

    /**
     * Mapa pokemon_id → evolution_chain_id para resolver los caramelos de
     * familia de los hallazgos (pueden referenciar pokémon NO derrotados).
     *
     * @param  Collection<int, Pokemon>  $pokemons  keyBy id
     * @return array<int, int>
     */
    public function chainPorPokemon(ColeccionEventosExploracion $hallazgos, Collection $pokemons): array
    {
        $resultado = [];
        foreach ($pokemons as $pokemon) {
            $resultado[$pokemon->id] = $pokemon->evolution_chain_id;
        }

        $faltan = collect($hallazgos->pluck(fn (EventoExploracion $evento): ?int => $evento->pokemonId))
            ->filter(fn (?int $id): bool => $id !== null && $id > 0 && ! array_key_exists($id, $resultado))
            ->unique()
            ->values();

        if ($faltan->isNotEmpty()) {
            $query = Pokemon::query();
            $query->getQuery()->whereIn('id', $faltan);

            foreach ($query->get(['id', 'evolution_chain_id']) as $pokemon) {
                $resultado[$pokemon->id] = $pokemon->evolution_chain_id;
            }
        }

        return $resultado;
    }

    /**
     * @param  list<int>  $idsDerrotados
     * @return Collection<int, Pokemon> keyBy id, con stats y types.
     */
    private function cargarPokemonsDerrotados(array $idsDerrotados): Collection
    {
        $ids = collect($idsDerrotados)->unique()->values();

        if ($ids->isEmpty()) {
            return new Collection();
        }

        $query = Pokemon::query()->with('stats', 'types');
        $query->getQuery()->whereIn('id', $ids);

        return $query->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, Pokemon>  $pokemons  keyBy id
     * @param  list<int>  $idsDerrotados
     * @return BaseCollection<int, Pokemon>
     */
    private function expandirDerrotados(Collection $pokemons, array $idsDerrotados): BaseCollection
    {
        return collect($idsDerrotados)
            ->map(fn (int $id): ?Pokemon => $pokemons->get($id))
            ->filter()
            ->values();
    }
}
