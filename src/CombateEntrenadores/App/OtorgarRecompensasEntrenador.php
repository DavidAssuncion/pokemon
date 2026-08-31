<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Pokemon;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Src\Exploraciones\App\NormalizadorPokemonDerrotado;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;

/**
 * Otorga las recompensas de un combate contra entrenador ganado: DOBLE de lo
 * que daría una exploración por los mismos derrotados (misma fórmula), con
 * EXP a la cuenta y a cada miembro del equipo, caramelos familia/EV/tipo, y
 * dispara el evento de avistados de los pokémon rivales.
 *
 * Sin capturas: el combate contra entrenadores no recluta rivales.
 */
final class OtorgarRecompensasEntrenador
{
    public function __construct(
        private readonly CalculadorRecompensas $calculador,
        private readonly PersistirRecompensas $persistir,
    ) {
    }

    /**
     * @param  list<int>  $speciesIdsRival  ids de las especies derrotadas (team2)
     */
    public function otorgar(int $userId, int $teamId, array $speciesIdsRival, int $nivelEntrenador): void
    {
        $usuario = User::find($userId);
        $equipo = Team::with('members.reclutado')->find($teamId);

        if ($usuario === null) {
            return;
        }

        $ids = array_values(array_unique(array_filter(
            $speciesIdsRival,
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return;
        }

        $pokemons = Pokemon::query()->with('stats', 'types');
        $pokemons->getQuery()->whereIn('id', $ids);
        $pokemons = $pokemons->get()->keyBy('id');

        if ($pokemons->isEmpty()) {
            return;
        }

        $miembrosPorCadena = $this->cargarMiembrosDeCadenas($pokemons);
        $derrotados = NormalizadorPokemonDerrotado::normalizar($pokemons, $miembrosPorCadena);

        // Combate de entrenadores: nunca hay captura del rival.
        $aleatorioCaptura = static fn (PokemonDerrotado $pokemon): bool => false;

        $nivelSalvaje = $usuario->nivel();
        $multiplicador = 2.0;

        $recompensas = $this->calculador->calcular(
            $derrotados,
            $aleatorioCaptura,
            $nivelSalvaje,
            $multiplicador,
        );

        $this->persistir->persistir($recompensas, $equipo, $usuario);

        $this->despacharAvistados($ids, $userId);
    }

    /**
     * Mapa de TODOS los miembros de las cadenas implicadas, keyed por
     * evolution_chain_id (misma lógica que FinalizarExploracionHandler).
     *
     * @param  Collection<int, Pokemon>  $derrotados  keyBy id
     * @return array<int, Collection<int, Pokemon>>
     */
    private function cargarMiembrosDeCadenas(Collection $derrotados): array
    {
        $chainIds = $derrotados
            ->pluck('evolution_chain_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($chainIds->isEmpty()) {
            return [];
        }

        $query = Pokemon::query();
        $query->getQuery()->whereIn('evolution_chain_id', $chainIds);

        return $query->get(['id', 'name', 'species_id', 'evolution_chain_id'])->groupBy('evolution_chain_id')->all();
    }

    /**
     * @param  list<int>  $pokemonIds
     */
    private function despacharAvistados(array $pokemonIds, int $userId): void
    {
        foreach ($pokemonIds as $pokemonId) {
            ActualizarPokedexJob::dispatch($userId, $pokemonId, 'AVISTADO');
        }
    }
}
