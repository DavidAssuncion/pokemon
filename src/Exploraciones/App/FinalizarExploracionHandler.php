<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use LogicException;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Exploraciones\Domain\SinergiaEquipo;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Bus\UnitOfWork;
use Src\Shared\Domain\ProbabilidadCaptura;

/**
 * Reparte todas las recompensas de una expedición (pokedex, capturas, caramelos
 * familia/EV/tipo, EXP) y marca el regreso. Idempotente.
 *
 * RF-07: derrotados = solo resolucion 'victoria' (retrocompat: evento sin
 * resolucion = victoria); avistados = todo evento con pokemon_id(s).
 * RF-08/RF-09: categoría final + multiplicador; retirada conserva lo obtenido.
 *
 * @todo Excepción temporal a la regla de dependencias: src/ importa
 *       App\Models/App\Jobs/Illuminate (deuda WIP). Ticket v2.
 */
final class FinalizarExploracionHandler implements CommandHandler
{
    private readonly ?Closure $aleatorio;

    /**
     * @param  callable():float|null  $aleatorio  Proveedor determinista de la
     *                                            tirada [0,1) para TEST (seam).
     *                                            null → mt_rand(1,100)/100.
     */
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CalculadorRecompensas $calculador,
        private readonly PersistirRecompensas $persistir,
        private readonly TransformadorResultadoExploracion $transformador,
        ?callable $aleatorio = null,
    ) {
        $this->aleatorio = $aleatorio !== null ? Closure::fromCallable($aleatorio) : null;
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

        /** @var BaseCollection<string, mixed> $eventos */
        $eventos = $exploracion->eventos ?? collect();
        /** @var list<array<string, mixed>> $bitacora */
        $bitacora = $eventos->get('bitacora', []);

        $idsDerrotados = $this->idsDerrotados($bitacora);
        $pokemons = $this->cargarPokemonsDerrotados($idsDerrotados);
        $miembrosPorCadena = $this->cargarMiembrosDeCadenas($pokemons);
        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($pokemons, $idsDerrotados),
            $miembrosPorCadena,
        );

        $categoria = EvaluadorExploracion::categoriaFinal($bitacora);
        $multiplicador = EvaluadorExploracion::multiplicador($categoria);

        $this->repartirRecompensas(
            $exploracion,
            $derrotados,
            $pokemons,
            $miembrosPorCadena,
            $idsDerrotados,
            $bitacora,
            $eventos,
            $categoria,
            $multiplicador,
            $this->aleatorio,
        );

        return null;
    }

    /**
     * Calcula, persiste y registra todas las recompensas y marca el regreso.
     * Multiplayer: el dueño de la exploración (belongsToUser). Con FK cascade el
     * dueño siempre existe; si no se resuelve (caso artificial), nivel salvaje 1
     * y sin recompensas al jugador.
     *
     * @param  BaseCollection<int, PokemonDerrotado>  $derrotados
     * @param  Collection<int, Pokemon>  $pokemons
     * @param  array<int, Collection<int, Pokemon>>  $miembrosPorCadena
     * @param  list<int>  $idsDerrotados
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     * @param  callable():float|null  $aleatorio
     */
    private function repartirRecompensas(
        ExploracionActiva $exploracion,
        BaseCollection $derrotados,
        Collection $pokemons,
        array $miembrosPorCadena,
        array $idsDerrotados,
        array $bitacora,
        BaseCollection $eventos,
        string $categoria,
        float $multiplicador,
        ?callable $aleatorio = null,
    ): void {
        $usuario = $exploracion->user;
        $recompensas = $this->calculador->calcular(
            $derrotados,
            $this->rollAleatorio($aleatorio),
            $usuario !== null ? $usuario->nivel() : 1,
            $multiplicador,
        );

        // Hallazgos (D8): caramelos de familia/EV/tipo de los eventos hallazgo.
        /** @var BaseCollection<int, array<string, mixed>> $hallazgos */
        $hallazgos = collect($bitacora)
            ->filter(fn (array $evento): bool => ($evento['tipo'] ?? '') === 'hallazgo')
            ->values();
        $caramelosHallazgos = $this->calculador->calcularHallazgos(
            $hallazgos,
            $this->chainPorPokemon($hallazgos, $pokemons),
            $this->multiplicadorCaramelosEquipo($exploracion, $multiplicador),
        );
        $recompensas = $recompensas->sumarHallazgos(
            $caramelosHallazgos['caramelosFamilia'],
            $caramelosHallazgos['caramelosEv'],
            $caramelosHallazgos['caramelosTipo'],
        );

        $this->persistir->persistir($recompensas, $exploracion->team, $usuario);
        $this->despacharAvistados($this->idsAvistados($bitacora), $exploracion->user_id);

        $tiempoPerdido = (int) $eventos->get('tiempo_perdido', 0);
        $this->registrarResultado(
            $exploracion,
            $recompensas,
            $pokemons,
            $miembrosPorCadena,
            $idsDerrotados,
            $categoria,
            $this->duracionReal($exploracion, $tiempoPerdido),
            $tiempoPerdido,
            $this->incidentes($bitacora),
            $eventos,
        );

        $exploracion->update(['regreso' => now(), 'eventos' => $eventos]);
    }

    /**
     * IDs de pokémon derrotados: solo eventos con resolución victoria (o sin
     * resolución, retrocompat RF-07), expandidos por pokemon_id/pokemon_ids.
     *
     * @param  list<array<string, mixed>>  $bitacora
     * @return list<int>
     */
    private function idsDerrotados(array $bitacora): array
    {
        $ids = [];
        foreach ($bitacora as $evento) {
            if (! EvaluadorExploracion::esVictoria($evento)) {
                continue;
            }

            foreach (EvaluadorExploracion::pokemonIdsDelEvento($evento) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * IDs de pokémon avistados: todo evento con pokemon_id(s) (encuentro,
     * emboscada, huida y legacy 'pokemon') → ActualizarPokedexJob AVISTADO.
     *
     * @param  list<array<string, mixed>>  $bitacora
     * @return list<int>
     */
    private function idsAvistados(array $bitacora): array
    {
        $ids = [];
        foreach ($bitacora as $evento) {
            if (! EvaluadorExploracion::esAvistamiento($evento)) {
                continue;
            }

            foreach (EvaluadorExploracion::pokemonIdsDelEvento($evento) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
     * Mapa pokemon_id → evolution_chain_id para resolver los caramelos de
     * familia de los hallazgos (pueden referenciar pokémon NO derrotados).
     *
     * @param  BaseCollection<int, array<string, mixed>>  $hallazgos
     * @param  Collection<int, Pokemon>  $pokemons  keyBy id
     * @return array<int, int>
     */
    private function chainPorPokemon(BaseCollection $hallazgos, Collection $pokemons): array
    {
        $resultado = [];
        foreach ($pokemons as $pokemon) {
            $resultado[$pokemon->id] = $pokemon->evolution_chain_id;
        }

        $faltan = $hallazgos
            ->pluck('pokemon_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && ! array_key_exists($id, $resultado))
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
     * Multiplicador de caramelos de hallazgo: categoría × rol Recolector (+50 %)
     * × sinergia (prospección/recolección segura, etc.).
     */
    private function multiplicadorCaramelosEquipo(ExploracionActiva $exploracion, float $multiplicadorCategoria): float
    {
        $multiplicador = $multiplicadorCategoria;
        $roles = $this->rolesDelEquipo($exploracion);

        foreach ($roles as $rol) {
            $multiplicador *= $rol->multiplicadorCaramelosHallazgo();
        }

        $sinergia = SinergiaEquipo::sinergiaPara($roles);
        if ($sinergia !== null) {
            $multiplicador *= $sinergia['multiplicadorCaramelos'];
        }

        return $multiplicador;
    }

    /**
     * @return list<RolExploracion>
     */
    private function rolesDelEquipo(ExploracionActiva $exploracion): array
    {
        $roles = [];
        foreach ($exploracion->team->members ?? [] as $miembro) {
            $roles[] = RolExploracion::tryFrom($miembro->behavior ?? '') ?? RolExploracion::COMBATIENTE;
        }

        return $roles;
    }

    /**
     * Duración real de la expedición: nominal (minutos entre inicio y fin) menos
     * el tiempo perdido acumulado, mínimo 0 (RF-05).
     */
    private function duracionReal(ExploracionActiva $exploracion, int $tiempoPerdido): int
    {
        $inicio = $exploracion->inicio_exploracion?->copy() ?? $exploracion->created_at?->copy() ?? now();
        $fin = $this->finExploracion($exploracion, $inicio);

        if ($fin === null) {
            return 0;
        }

        $nominal = max(0, (int) abs($fin->diffInMinutes($inicio)));

        return max(0, $nominal - $tiempoPerdido);
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     * @return array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int}
     */
    private function incidentes(array $bitacora): array
    {
        $encuentros = 0;
        $victorias = 0;
        $huidas = 0;
        $emboscadas = 0;
        $contratiempos = 0;

        foreach ($bitacora as $evento) {
            $tipo = $evento['tipo'] ?? '';
            $resolucion = $evento['resolucion'] ?? null;

            if ($tipo === 'emboscada') {
                $emboscadas++;
            } elseif ($tipo === 'contratiempo') {
                $contratiempos++;
            } elseif ($tipo === 'encuentro' || $tipo === 'pokemon') {
                $encuentros++;
                if ($resolucion === null || $resolucion === 'victoria') {
                    $victorias++;
                }
            } elseif ($resolucion === 'huida') {
                $huidas++;
            }
        }

        return [
            'encuentros' => $encuentros,
            'victorias' => $victorias,
            'huidas' => $huidas,
            'emboscadas' => $emboscadas,
            'contratiempos' => $contratiempos,
        ];
    }

    /**
     * @param  list<int>  $pokemonIds
     */
    private function despacharAvistados(array $pokemonIds, int $userId): void
    {
        if ($pokemonIds === []) {
            return;
        }

        $this->unitOfWork->afterCommit(function () use ($pokemonIds, $userId): void {
            foreach (array_unique($pokemonIds) as $pokemonId) {
                ActualizarPokedexJob::dispatch($userId, $pokemonId, 'AVISTADO');
            }
        });
    }

    /**
     * Tirada de captura (regla cap-25, dominio ProbabilidadCaptura): seam de
     * test con aleatorio [0,1) determinista; por defecto mt_rand(1, 100) / 100.
     *
     * @param  callable():float|null  $aleatorio
     */
    private function rollAleatorio(?callable $aleatorio = null): callable
    {
        $aleatorio = $aleatorio ?? fn (): float => mt_rand(1, 100) / 100;

        return fn (PokemonDerrotado $pokemon): bool => ProbabilidadCaptura::intentar($pokemon->captureRate, $aleatorio);
    }

    /**
     * Escribe eventos['derrotados'] y eventos['resultado'] (contrato aditivo
     * RF-10) en el modelo para persistirlos junto con el regreso.
     *
     * @param  Collection<int, Pokemon>  $pokemons
     * @param  array<int, Collection<int, Pokemon>>  $miembrosPorCadena
     * @param  list<int>  $idsDerrotados
     * @param  BaseCollection<string, mixed>  $eventos
     * @param  array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int}  $incidentes
     */
    private function registrarResultado(
        ExploracionActiva $exploracion,
        ResultadoRecompensas $recompensas,
        Collection $pokemons,
        array $miembrosPorCadena,
        array $idsDerrotados,
        string $categoria,
        int $durationReal,
        int $tiempoPerdido,
        array $incidentes,
        BaseCollection $eventos,
    ): void {
        $eventos->put('derrotados', $idsDerrotados);
        $eventos->put('resultado', $this->transformador->desde(
            $recompensas,
            $pokemons,
            $miembrosPorCadena,
            categoria: $categoria,
            durationReal: $durationReal,
            tiempoPerdido: $tiempoPerdido,
            incidentes: $incidentes,
        ));

        $exploracion->eventos = $eventos;
    }

    private function finExploracion(ExploracionActiva $exploracion, CarbonInterface $inicio): ?CarbonInterface
    {
        if ($exploracion->hora_limite !== null) {
            return Carbon::today()->setTimeFromTimeString($exploracion->hora_limite);
        }

        if ($exploracion->duracion_horas !== null) {
            return $inicio->copy()->addHours($exploracion->duracion_horas);
        }

        return null;
    }
}
