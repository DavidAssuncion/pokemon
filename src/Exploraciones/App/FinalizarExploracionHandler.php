<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use LogicException;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Bus\UnitOfWork;

/**
 * Reparte todas las recompensas de una exploración (pokedex, capturas,
 * caramelos familia/EV/tipo, EXP) y marca el regreso. Idempotente.
 *
 * @todo Excepción temporal a la regla de dependencias: src/ importa
 *       App\Models/App\Jobs/Illuminate (deuda WIP). Ticket v2: extraer
 *       repositorio/interfaz en Domain o mover handlers a app/.
 */
final class FinalizarExploracionHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CalculadorRecompensas $calculador,
        private readonly PersistirRecompensas $persistir,
        private readonly TransformadorResultadoExploracion $transformador,
    ) {
    }

    public function handle(Command $command): mixed
    {
        if (! $command instanceof FinalizarExploracionCommand) {
            throw new LogicException('FinalizarExploracionHandler requires a FinalizarExploracionCommand.');
        }

        $exploracion = $command->exploracion->refresh();

        // Idempotencia: si ya regresó (modelo refrescado de DB), no repartir otra vez.
        if ($exploracion->regreso !== null) {
            return null;
        }

        $eventos = $exploracion->eventos ?? [];
        $idsDerrotados = $this->idsDerrotados($eventos);
        $pokemons = $this->cargarPokemonsDerrotados($idsDerrotados);
        $miembrosPorCadena = $this->cargarMiembrosDeCadenas($pokemons);
        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($pokemons, $idsDerrotados),
            $miembrosPorCadena,
        );

        $this->repartirRecompensas($exploracion, $derrotados, $pokemons, $miembrosPorCadena, $idsDerrotados);

        return null;
    }

    /**
     * Calcula, persiste y registra todas las recompensas y marca el regreso.
     * Multiplayer: el dueño de la exploración (relación belongsTo del trait
     * BelongsToUser). Con FK cascade el dueño siempre existe; si no se resuelve
     * (caso artificial), nivel salvaje 1 y sin recompensas al jugador.
     *
     * @param  BaseCollection<int, Pokemon>  $derrotados
     * @param  Collection<int, Pokemon>  $pokemons
     * @param  array<int, Collection<int, Pokemon>>  $miembrosPorCadena
     * @param  list<int>  $idsDerrotados
     */
    private function repartirRecompensas(
        ExploracionActiva $exploracion,
        BaseCollection $derrotados,
        Collection $pokemons,
        array $miembrosPorCadena,
        array $idsDerrotados,
    ): void {
        $usuario = $exploracion->user;
        $recompensas = $this->calculador->calcular(
            $derrotados,
            $this->rollAleatorio(),
            $usuario !== null ? $usuario->nivel() : 1,
        );

        $this->persistir->persistir($recompensas, $exploracion->team, $usuario);
        $this->despacharAvistados($pokemons->pluck('id'), $exploracion->user_id);
        $this->registrarResultado($exploracion, $recompensas, $pokemons, $miembrosPorCadena, $idsDerrotados);
        $exploracion->update(['regreso' => now(), 'eventos' => $exploracion->eventos]);
    }

    /**
     * IDs de pokémon derrotados según la bitácora (una entrada por derrota).
     *
     * @param  array<string, mixed>  $eventos
     * @return list<int>
     */
    private function idsDerrotados(array $eventos): array
    {
        /** @var list<array<string, mixed>> $bitacora */
        $bitacora = $eventos['bitacora'] ?? [];

        return collect($bitacora)
            ->filter(fn (array $evento): bool => ($evento['tipo'] ?? null) === 'pokemon')
            ->pluck('pokemon_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
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
     * Mapa de TODOS los miembros de las cadenas implicadas, keyed por
     * evolution_chain_id (columna). Sustituye a la antigua relación de la tabla
     * evolution_chains (eliminada): incluye también los miembros NO derrotados
     * para preservar fase y base de familia.
     *
     * @param  Collection<int, Pokemon>  $derrotados  keyBy id
     * @return array<int, Collection<int, Pokemon>>  keyed por evolution_chain_id
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
     * Expande los ids de la bitácora a una entrada por derrota, descartando
     * ids sin pokémon cargado (no deberían ocurrir: la bitácora viene del pool).
     *
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

    /**
     * @param  BaseCollection<int, int>  $pokemonIds
     */
    private function despacharAvistados(BaseCollection $pokemonIds, int $userId): void
    {
        if ($pokemonIds->isEmpty()) {
            return;
        }

        $this->unitOfWork->afterCommit(function () use ($pokemonIds, $userId): void {
            foreach ($pokemonIds->unique() as $pokemonId) {
                ActualizarPokedexJob::dispatch($userId, $pokemonId, 'AVISTADO');
            }
        });
    }

    /**
     * Tirada de captura: chance = min(1, capture_rate / 255).
     */
    private function rollAleatorio(): callable
    {
        return fn (PokemonDerrotado $pokemon): bool => mt_rand(1, 100) / 100 <= min(1.0, $pokemon->captureRate / 255);
    }

    /**
     * Escribe eventos['derrotados'] (bitácora) y eventos['resultado'] (DTO → JSON)
     * en el modelo para persistirlos junto con el regreso.
     *
     * @param  Collection<int, Pokemon>  $pokemons
     * @param  array<int, Collection<int, Pokemon>>  $miembrosPorCadena
     * @param  list<int>  $idsDerrotados
     */
    private function registrarResultado(
        ExploracionActiva $exploracion,
        ResultadoRecompensas $recompensas,
        Collection $pokemons,
        array $miembrosPorCadena,
        array $idsDerrotados,
    ): void {
        $eventos = $exploracion->eventos ?? [];
        $eventos['derrotados'] = $idsDerrotados;
        $eventos['resultado'] = $this->transformador->desde($recompensas, $pokemons, $miembrosPorCadena);

        $exploracion->eventos = $eventos;
    }
}
