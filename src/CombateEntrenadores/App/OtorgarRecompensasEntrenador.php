<?php

declare(strict_types=1);

namespace Src\CombateEntrenadores\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Pokemon;
use App\Models\Team;
use App\Models\User;
use App\Support\ItemCatalogo;
use Illuminate\Support\Collection;
use Src\Exploraciones\App\NormalizadorPokemonDerrotado;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;

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
     * @param  float  $multiplicador  multiplicador sobre la fórmula de exploración
     *                                (2.0 entrenadores, 10.0 gimnasios)
     */
    public function otorgar(int $userId, int $teamId, array $speciesIdsRival, int $nivelEntrenador, float $multiplicador = 2.0): array
    {
        $usuario = User::find($userId);
        $equipo = Team::with('members.reclutado')->find($teamId);

        if ($usuario === null) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            $speciesIdsRival,
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $pokemons = Pokemon::query()->with('stats', 'types');
        $pokemons->getQuery()->whereIn('id', $ids);
        $pokemons = $pokemons->get()->keyBy('id');

        if ($pokemons->isEmpty()) {
            return [];
        }

        $miembrosPorCadena = $this->cargarMiembrosDeCadenas($pokemons);
        $derrotados = NormalizadorPokemonDerrotado::normalizar($pokemons, $miembrosPorCadena);

        // Combate de entrenadores: nunca hay captura del rival.
        $aleatorioCaptura = static fn (PokemonDerrotado $pokemon): bool => false;

        $nivelSalvaje = $usuario->nivel();

        $recompensas = $this->calculador->calcular(
            $derrotados,
            $aleatorioCaptura,
            $nivelSalvaje,
            $multiplicador,
        );

        $this->persistir->persistir($recompensas, $equipo, $usuario);

        $this->despacharAvistados($ids, $userId);

        return $this->aDatosModal($recompensas);
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

    /**
     * Datos de presentación para el modal de victoria.
     *
     * @return array{
     *     exp_total: int,
     *     exp_miembro: int,
     *     caramelos: list<array{nombre: string, imagen: string, cantidad: int}>
     * }
     */
    private function aDatosModal(ResultadoRecompensas $recompensas): array
    {
        $caramelos = [];

        foreach ($recompensas->caramelosFamilia as $recompensa) {
            $resuelto = ItemCatalogo::resolve(ItemCatalogo::keyFamilia($recompensa->evolutionChainId));
            $caramelos[] = [
                'nombre' => $resuelto['nombre'],
                'imagen' => $resuelto['imagen'],
                'cantidad' => $recompensa->cantidad,
            ];
        }

        foreach ($recompensas->caramelosEv as $recompensa) {
            $resuelto = ItemCatalogo::resolve(ItemCatalogo::keyEv($recompensa->stat));
            $caramelos[] = [
                'nombre' => $resuelto['nombre'],
                'imagen' => $resuelto['imagen'],
                'cantidad' => $recompensa->cantidad,
            ];
        }

        foreach ($recompensas->caramelosTipo as $recompensa) {
            $caramelos[] = [
                'nombre' => $recompensa->tipo,
                'imagen' => '/images/candy_type/'.$recompensa->slug().'.webp',
                'cantidad' => $recompensa->cantidad,
            ];
        }

        return [
            'exp_total' => $recompensas->expTotal,
            'exp_miembro' => $recompensas->expPorMiembro,
            'caramelos' => $caramelos,
        ];
    }
}
