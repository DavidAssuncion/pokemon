<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use App\Support\CadenasEvolutivas;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use LogicException;
use Src\Exploraciones\Domain\CalculadorFinExploracion;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Exploraciones\Domain\ValueObjects\EventoExploracion;
use Src\Exploraciones\Presentation\DTOIncidentesExploracion;
use Src\Exploraciones\Presentation\DTOObjetoPerdido;
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
 * La capa App/ usa Eloquent (App\Models) e Illuminate directamente por
 * convención del proyecto (la regla DDD solo exige Domain puro).
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
        $bitacoraEventos = ColeccionEventosExploracion::desdeArray($bitacora);

        $idsDerrotados = $this->idsDerrotados($bitacoraEventos);
        $pokemons = $this->cargarPokemonsDerrotados($idsDerrotados);
        $miembrosPorCadena = CadenasEvolutivas::miembrosDe(
            $pokemons->pluck('evolution_chain_id')
        );
        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($pokemons, $idsDerrotados),
            $miembrosPorCadena,
        );

        $categoria = EvaluadorExploracion::categoriaFinalDeEventos($bitacoraEventos);
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

        // Hallazgos (D8): caramelos de familia/EV/tipo de los eventos hallazgo,
        // más el hallazgo equivalente de cada emboscada evitada. Rango
        // Recolector: cada hallazgo rinde ×(1 + bonus) caramelos.
        $bitacoraEventos = ColeccionEventosExploracion::desdeArray($bitacora);
        $hallazgos = new ColeccionEventosExploracion([
            ...$bitacoraEventos->filter(fn (EventoExploracion $evento): bool => $evento->esHallazgo())->toList(),
            ...$this->hallazgosEmboscadasEvitadasDe($bitacoraEventos),
        ]);
        $caramelosHallazgos = $this->calculador->calcularHallazgosDeEventos(
            $hallazgos,
            $this->chainPorPokemonDeEventos($hallazgos, $pokemons),
            $this->multiplicadorCaramelosEquipo($exploracion, $multiplicador),
            $this->bonusCaramelosRecoleccion($exploracion),
        );
        $recompensas = $recompensas->sumarHallazgos(
            $caramelosHallazgos['caramelosFamilia'],
            $caramelosHallazgos['caramelosEv'],
            $caramelosHallazgos['caramelosTipo'],
        );

        // Derrota → pérdida de objetos: ceil(cantidad/2) de cada recompensa
        // (familia/EV/tipo/capturas), restado de las recompensas finales.
        $objetosPerdidos = $this->objetosPerdidos($bitacora, $eventos, $recompensas);
        if ($objetosPerdidos !== []) {
            $recompensas = $this->restarPerdidas($recompensas, $objetosPerdidos);
            $eventos->put('objetos_perdidos', array_map(
                fn (DTOObjetoPerdido $objeto): array => $objeto->toArray(),
                $objetosPerdidos,
            ));
        }

        $this->persistir->persistir($recompensas, $exploracion->reclutado, $usuario);
        $this->despacharAvistados($this->idsAvistadosDeEventos($bitacoraEventos), $exploracion->user_id);

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
     * @return list<int>
     */
    private function idsDerrotados(ColeccionEventosExploracion $bitacora): array
    {
        $ids = [];
        foreach ($bitacora as $evento) {
            if (! $evento->esVictoria()) {
                continue;
            }

            foreach ($evento->pokemonIds()->all() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * IDs de pokémon avistados: todo evento con pokemon_id(s) (encuentro,
     * emboscada, huida y legacy 'pokemon') → ActualizarPokedexJob AVISTADO.
     *
     * @return list<int>
     */
    private function idsAvistadosDeEventos(ColeccionEventosExploracion $bitacora): array
    {
        $ids = [];
        foreach ($bitacora as $evento) {
            if (! $evento->esAvistamiento()) {
                continue;
            }

            foreach ($evento->pokemonIds()->all() as $id) {
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
     * @param  Collection<int, Pokemon>  $pokemons  keyBy id
     * @return array<int, int>
     */
    private function chainPorPokemonDeEventos(ColeccionEventosExploracion $hallazgos, Collection $pokemons): array
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
     * Cada emboscada evitada produce un hallazgo equivalente de 1 caramelo de
     * familia del primer pokémon implicado. Además cuenta como victoria en
     * idsDerrotados (esVictoria): doble premio intencional según spec.
     *
     * @return list<EventoExploracion>
     */
    private function hallazgosEmboscadasEvitadasDe(ColeccionEventosExploracion $bitacora): array
    {
        $hallazgos = [];
        foreach ($bitacora as $evento) {
            if ($evento->esEmboscadaEvitada()) {
                $ids = $evento->pokemonIds()->all();
                if ($ids !== []) {
                    $hallazgos[] = $this->hallazgoDeEmboscadaEvitada($ids[0]);
                }
            }
        }

        return $hallazgos;
    }

    /**
     * Hallazgo equivalente de una emboscada evitada: 1 caramelo de familia del
     * primer pokémon implicado (origen 'emboscada_evitada').
     */
    private function hallazgoDeEmboscadaEvitada(int $pokemonId): EventoExploracion
    {
        return EventoExploracion::desdeArray([
            'tipo' => 'hallazgo',
            'subtype' => 'caramelo_familia',
            'pokemon_id' => $pokemonId,
            'cantidad' => 1,
            'origen' => 'emboscada_evitada',
        ]);
    }

    /**
     * Multiplicador de caramelos de hallazgo: categoría × rol Recolector (+50 %)
     * × sinergia (prospección/recolección segura, etc.). RFC: el rol se lee del
     * reclutado individual ($reclutado->rol(), columna behavior propia), ya no
     * de team_members.behavior, por lo que aplica también sin equipo.
     */
    private function multiplicadorCaramelosEquipo(ExploracionActiva $exploracion, float $multiplicadorCategoria): float
    {
        $reclutado = $exploracion->reclutado;
        if ($reclutado === null) {
            return $multiplicadorCategoria;
        }

        return $multiplicadorCategoria * $reclutado->rol()->multiplicadorCaramelosHallazgo();
    }

    /**
     * Bonus de caramelos por recolección del rango Recolector del reclutado
     * (0/1/2/3 según dificultad del hábitat). Sin reclutado/usuario → 0.
     */
    private function bonusCaramelosRecoleccion(ExploracionActiva $exploracion): int
    {
        $reclutado = $exploracion->reclutado;
        $usuario = $exploracion->user;

        if ($reclutado === null || $usuario === null) {
            return 0;
        }

        $dificultad = $exploracion->habitat?->minLvlParaNivel($exploracion->nivel)
            ?? EvaluadorExploracion::dificultad('normal', (int) ($exploracion->habitat?->peligro ?? 1));

        return FabricaCapacidadesStats::desdeReclutado($reclutado, $usuario)->bonusCaramelosRecoleccion($dificultad);
    }

    /**
     * Duración real de la expedición: nominal (minutos entre inicio y fin) menos
     * el tiempo perdido acumulado, mínimo 0 (RF-05).
     */
    private function duracionReal(ExploracionActiva $exploracion, int $tiempoPerdido): int
    {
        $inicio = $exploracion->inicio_exploracion?->copy() ?? $exploracion->created_at?->copy() ?? now();
        $fin = CalculadorFinExploracion::calcular($exploracion->hora_limite, $exploracion->duracion_horas, $inicio);

        if ($fin === null) {
            return 0;
        }

        $nominal = max(0, (int) abs($fin->diffInMinutes($inicio)));

        return max(0, $nominal - $tiempoPerdido);
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     */
    private function incidentes(array $bitacora): DTOIncidentesExploracion
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

        return new DTOIncidentesExploracion(
            encuentros: $encuentros,
            victorias: $victorias,
            huidas: $huidas,
            emboscadas: $emboscadas,
            contratiempos: $contratiempos,
        );
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
        DTOIncidentesExploracion $incidentes,
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
            incidentes: $incidentes->toArray(),
        ));

        $exploracion->eventos = $eventos;
    }

    /**
     * Detecta si la exploración terminó por derrota (bitácora contiene un evento
     * con resolución 'derrota' o el flag eventos['derrota'] está presente).
     *
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     */
    private function terminoPorDerrota(array $bitacora, BaseCollection $eventos): bool
    {
        if ($eventos->get('derrota') !== null) {
            return true;
        }

        foreach ($bitacora as $evento) {
            if (($evento['resolucion'] ?? '') === 'derrota') {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcula la pérdida de objetos por derrota: ceil(cantidad/2) de cada
     * recompensa (familia/EV/tipo/capturas). Si no hay derrota, devuelve [].
     *
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     * @return list<DTOObjetoPerdido>
     */
    private function objetosPerdidos(array $bitacora, BaseCollection $eventos, ResultadoRecompensas $recompensas): array
    {
        if (! $this->terminoPorDerrota($bitacora, $eventos)) {
            return [];
        }

        $perdidas = [];

        foreach ($recompensas->caramelosFamilia as $recompensa) {
            $perdida = (int) ceil($recompensa->cantidad / 2);
            if ($perdida <= 0) {
                continue;
            }
            $perdidas[] = new DTOObjetoPerdido(
                tipo: 'familia',
                id: $recompensa->evolutionChainId,
                label: null,
                cantidad_perdida: $perdida,
            );
        }

        foreach ($recompensas->caramelosEv as $recompensa) {
            $perdida = (int) ceil($recompensa->cantidad / 2);
            if ($perdida <= 0) {
                continue;
            }
            $perdidas[] = new DTOObjetoPerdido(
                tipo: 'ev',
                id: $recompensa->stat,
                label: null,
                cantidad_perdida: $perdida,
            );
        }

        foreach ($recompensas->caramelosTipo as $recompensa) {
            $perdida = (int) ceil($recompensa->cantidad / 2);
            if ($perdida <= 0) {
                continue;
            }
            $perdidas[] = new DTOObjetoPerdido(
                tipo: 'tipo',
                id: $recompensa->slug(),
                label: $recompensa->tipo,
                cantidad_perdida: $perdida,
            );
        }

        foreach ($recompensas->capturas as $recompensa) {
            $perdida = (int) ceil($recompensa->cantidad / 2);
            if ($perdida <= 0) {
                continue;
            }
            $perdidas[] = new DTOObjetoPerdido(
                tipo: 'captura',
                id: $recompensa->pokemonId,
                label: null,
                cantidad_perdida: $perdida,
            );
        }

        return $perdidas;
    }

    /**
     * Resta las pérdidas de las recompensas finales, devolviendo una nueva
     * instancia de ResultadoRecompensas con cantidades reducidas.
     *
     * @param  list<DTOObjetoPerdido>  $perdidas
     */
    private function restarPerdidas(ResultadoRecompensas $recompensas, array $perdidas): ResultadoRecompensas
    {
        $mapaPerdidas = [];
        foreach ($perdidas as $p) {
            $mapaPerdidas[$p->tipo][(string) $p->id] = $p->cantidad_perdida;
        }

        $reducir = function (int $cantidad, int|string $clave, string $tipo) use ($mapaPerdidas): int {
            $perdida = $mapaPerdidas[$tipo][(string) $clave] ?? 0;

            return max(0, $cantidad - $perdida);
        };

        return new ResultadoRecompensas(
            capturas: $recompensas->capturas
                ->map(fn (RecompensaCaptura $c) => new RecompensaCaptura(
                    pokemonId: $c->pokemonId,
                    cantidad: $reducir($c->cantidad, $c->pokemonId, 'captura'),
                ))
                ->filter(fn (RecompensaCaptura $c) => $c->cantidad > 0),
            caramelosFamilia: $recompensas->caramelosFamilia
                ->map(fn (RecompensaFamilia $c) => new RecompensaFamilia(
                    evolutionChainId: $c->evolutionChainId,
                    cantidad: $reducir($c->cantidad, $c->evolutionChainId, 'familia'),
                ))
                ->filter(fn (RecompensaFamilia $c) => $c->cantidad > 0),
            caramelosEv: $recompensas->caramelosEv
                ->map(fn (RecompensaEv $c) => new RecompensaEv(
                    stat: $c->stat,
                    cantidad: $reducir($c->cantidad, $c->stat, 'ev'),
                ))
                ->filter(fn (RecompensaEv $c) => $c->cantidad > 0),
            caramelosTipo: $recompensas->caramelosTipo
                ->map(fn (RecompensaTipo $c) => new RecompensaTipo(
                    tipo: $c->tipo,
                    cantidad: $reducir($c->cantidad, $c->slug(), 'tipo'),
                ))
                ->filter(fn (RecompensaTipo $c) => $c->cantidad > 0),
            expTotal: $recompensas->expTotal,
            expPorMiembro: $recompensas->expPorMiembro,
            expTipoPorMiembro: $recompensas->expTipoPorMiembro,
        );
    }
}
