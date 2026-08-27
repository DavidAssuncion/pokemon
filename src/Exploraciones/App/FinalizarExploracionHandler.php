<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use LogicException;
use Src\Exploraciones\Domain\CalculadorRecompensas;
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

        $pokemons = $this->cargarPokemonsDerrotados($exploracion);
        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $pokemons,
            $this->idsDerrotados($exploracion->eventos ?? []),
        );
        $recompensas = $this->calculador->calcular($derrotados, $this->rollAleatorio(), $this->nivelSalvaje());

        $this->persistir->persistir($recompensas, $exploracion);
        $this->despacharAvistados($pokemons->pluck('id'));
        $this->registrarResultado($exploracion, $recompensas, $pokemons);
        $exploracion->update(['regreso' => now(), 'eventos' => $exploracion->eventos]);

        return null;
    }

    /**
     * @return Collection<int, Pokemon> keyBy id, con evolutionChain.pokemon, stats y types.
     */
    private function cargarPokemonsDerrotados(ExploracionActiva $exploracion): Collection
    {
        $ids = $this->idsDerrotados($exploracion->eventos ?? []);

        if ($ids === []) {
            return new Collection();
        }

        return Pokemon::query()
            ->with('evolutionChain.pokemon', 'stats', 'types')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * IDs de pokémon derrotados según la bitácora (una entrada por evento).
     *
     * @param  array<string, mixed>  $eventos
     * @return list<int>
     */
    private function idsDerrotados(array $eventos): array
    {
        $derrotados = [];
        foreach ($eventos['bitacora'] ?? [] as $evento) {
            if (($evento['tipo'] ?? null) === 'pokemon') {
                $derrotados[] = (int) $evento['pokemon_id'];
            }
        }

        return $derrotados;
    }

    /**
     * @param  BaseCollection<int, int>  $pokemonIds
     */
    private function despacharAvistados(BaseCollection $pokemonIds): void
    {
        if ($pokemonIds->isEmpty()) {
            return;
        }

        $this->unitOfWork->afterCommit(function () use ($pokemonIds): void {
            foreach ($pokemonIds->unique() as $pokemonId) {
                ActualizarPokedexJob::dispatch($pokemonId, 'AVISTADO');
            }
        });
    }

    /**
     * Tirada de captura: chance = min(1, capture_rate / 255).
     */
    private function rollAleatorio(): callable
    {
        return fn (int $captureRate): bool => mt_rand(1, 100) / 100 <= min(1.0, $captureRate / 255);
    }

    private function nivelSalvaje(): int
    {
        $usuario = User::first();

        return $usuario !== null ? $usuario->nivel() : 1;
    }

    /**
     * Escribe eventos['derrotados'] (bitácora) y eventos['resultado'] (DTO → JSON)
     * en el modelo para persistirlos junto con el regreso.
     */
    private function registrarResultado(
        ExploracionActiva $exploracion,
        ResultadoRecompensas $recompensas,
        Collection $pokemons,
    ): void {
        $eventos = $exploracion->eventos ?? [];
        $eventos['derrotados'] = $this->idsDerrotados($eventos);
        $eventos['resultado'] = $this->transformador->desde($recompensas, $pokemons);

        $exploracion->eventos = $eventos;
    }
}
