<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Jobs\ActualizarPokedexJob;
use App\Models\Caramelo;
use App\Models\CarameloEv;
use App\Models\CarameloTipo;
use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use App\Models\Reclutable;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Src\Exploraciones\Domain\SimuladorEncuentros;
use Src\Shared\Domain\NivelHelper;

final class ProcesarExploracionService
{
    private const MINUTOS_POR_ENCUENTRO = 5;

    /**
     * Procesa un tick de exploración: genera encuentros nuevos desde el último
     * tick y, si toca, ejecuta la vuelta completa (recompensas + regreso).
     */
    public function procesar(ExploracionActiva $exploracion, bool $forzarRegreso = false): void
    {
        if ($exploracion->regreso !== null) {
            return;
        }

        $inicio = $this->inicioExploracion($exploracion);
        $fin = $this->finExploracion($exploracion, $inicio);
        $inicioVuelta = $fin !== null
            ? $fin->copy()->subMinutes(intdiv((int) abs($fin->diffInMinutes($inicio)), 4))
            : null;

        $eventos = $exploracion->eventos ?? [];
        $desde = $this->ultimoProcesado($eventos) ?? $inicio;
        $hasta = $this->limiteTick(now(), $fin, $inicioVuelta);

        if ($hasta->greaterThan($desde)) {
            $nuevos = SimuladorEncuentros::generarEventos(
                SimuladorEncuentros::poolPonderado($this->poolHabitat($exploracion)),
                intdiv((int) abs($hasta->diffInMinutes($desde)), self::MINUTOS_POR_ENCUENTRO),
                $desde,
                $hasta,
            );

            if ($nuevos !== []) {
                $bitacora = $eventos['bitacora'] ?? [];
                $eventos['bitacora'] = [...$bitacora, ...$nuevos];
            }
        }

        $eventos['ultimo_procesado'] = $hasta->toIso8601String();
        $exploracion->update(['eventos' => $eventos]);

        $completada = $forzarRegreso
            || ($inicioVuelta !== null && now()->greaterThanOrEqualTo($inicioVuelta));

        if ($completada) {
            $this->finalizar($exploracion);
        }
    }

    private function inicioExploracion(ExploracionActiva $exploracion): CarbonInterface
    {
        if ($exploracion->inicio_exploracion !== null) {
            return $exploracion->inicio_exploracion->copy();
        }

        if ($exploracion->created_at !== null) {
            return $exploracion->created_at->copy();
        }

        return now();
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

    private function limiteTick(
        CarbonInterface $ahora,
        ?CarbonInterface $fin,
        ?CarbonInterface $inicioVuelta,
    ): CarbonInterface {
        $limite = $ahora;

        if ($fin !== null && $fin->lessThan($limite)) {
            $limite = $fin;
        }

        if ($inicioVuelta !== null && $inicioVuelta->lessThan($limite)) {
            $limite = $inicioVuelta;
        }

        return $limite;
    }

    /**
     * @param  array<string, mixed>  $eventos
     */
    private function ultimoProcesado(array $eventos): ?CarbonInterface
    {
        $ultimo = $eventos['ultimo_procesado'] ?? null;

        return is_string($ultimo) ? Carbon::parse($ultimo) : null;
    }

    /**
     * Pool de encuentros: pokémon del hábitat asignados al nivel de la exploración.
     *
     * @return array<int, array{id: int, capture_rate: int, hatch: int|null}>
     */
    private function poolHabitat(ExploracionActiva $exploracion): array
    {
        $habitat = $exploracion->habitat;
        if ($habitat === null) {
            return [];
        }

        return $habitat->pokemon()
            ->wherePivot('level', $exploracion->nivel)
            ->get()
            ->map(fn (Pokemon $pokemon) => [
                'id' => $pokemon->id,
                'capture_rate' => $pokemon->capture_rate,
                'hatch' => $pokemon->hatch,
            ])
            ->values()
            ->all();
    }

    private function finalizar(ExploracionActiva $exploracion): void
    {
        $eventos = $exploracion->eventos ?? [];
        $bitacora = $eventos['bitacora'] ?? [];

        $derrotados = [];
        foreach ($bitacora as $evento) {
            if (($evento['tipo'] ?? null) === 'pokemon') {
                $derrotados[] = (int) $evento['pokemon_id'];
            }
        }

        $eventos['derrotados'] = $derrotados;

        $query = Pokemon::query()->with('evolutionChain.pokemon', 'stats', 'types');
        $query->getQuery()->whereIn('id', array_values(array_unique($derrotados)));
        $pokemons = $query->get()->keyBy('id');

        // Pokedex (AVISTADO) e intento de captura por cada derrotado. La captura se
        // resuelve aquí (misma regla que CapturarPokemonJob) para que el resumen
        // de resultado refleje exactamente los reclutables generados.
        $capturadosPorEspecie = [];
        foreach ($derrotados as $pokemonId) {
            $pokemon = $pokemons->get($pokemonId);
            if ($pokemon === null) {
                continue;
            }

            ActualizarPokedexJob::dispatch($pokemonId, 'AVISTADO');

            $captureChance = min(1.0, ($pokemon->capture_rate ?? 45) / 255);
            if (mt_rand(1, 100) / 100 <= $captureChance) {
                $reclutable = Reclutable::where('pokemon_id', $pokemonId)->first();
                if ($reclutable !== null) {
                    $reclutable->increment('cantidad');
                } else {
                    Reclutable::create(['pokemon_id' => $pokemonId, 'cantidad' => 1]);
                }

                $capturadosPorEspecie[$pokemonId] = ($capturadosPorEspecie[$pokemonId] ?? 0) + 1;
            }
        }

        // Caramelos de familia: fase × nº de derrotados por cadena evolutiva
        $caramelosFamilia = [];
        $basePorCadena = [];
        foreach ($derrotados as $pokemonId) {
            $pokemon = $pokemons->get($pokemonId);
            $cadena = $pokemon?->evolutionChain;
            if ($cadena === null || $pokemon->evolution_chain_id === null) {
                continue;
            }

            $fase = $cadena->pokemon
                ->where('species_id', '<=', $pokemon->species_id)
                ->count();
            $cadenaId = $pokemon->evolution_chain_id;
            $caramelosFamilia[$cadenaId] = ($caramelosFamilia[$cadenaId] ?? 0) + $fase;

            if (! isset($basePorCadena[$cadenaId])) {
                $basePorCadena[$cadenaId] = $cadena->pokemon->sortBy('species_id')->first();
            }
        }

        foreach ($caramelosFamilia as $cadenaId => $cantidad) {
            $existente = Caramelo::where('evolution_chain_id', $cadenaId)->first();
            if ($existente !== null) {
                $existente->increment('cantidad', $cantidad);
            } else {
                Caramelo::create(['evolution_chain_id' => $cadenaId, 'cantidad' => $cantidad]);
            }
        }

        // Caramelos EV: effort de cada derrotado por stat
        $evPorStat = [];
        foreach ($derrotados as $pokemonId) {
            $pokemon = $pokemons->get($pokemonId);
            if ($pokemon === null) {
                continue;
            }

            foreach ($pokemon->stats as $stat) {
                if ($stat->effort <= 0) {
                    continue;
                }

                $evPorStat[$stat->stat->value] = ($evPorStat[$stat->stat->value] ?? 0) + $stat->effort;

                $existente = CarameloEv::where('stat', $stat->stat->value)->first();
                if ($existente !== null) {
                    $existente->increment('cantidad', $stat->effort);
                } else {
                    CarameloEv::create(['stat' => $stat->stat->value, 'cantidad' => $stat->effort]);
                }
            }
        }

        // Caramelos de tipo: 1 por cada tipo del pokemon derrotado
        $caramelosTipo = [];
        foreach ($derrotados as $pokemonId) {
            $pokemon = $pokemons->get($pokemonId);
            if ($pokemon === null) {
                continue;
            }

            foreach ($pokemon->types as $tipo) {
                $nombre = $tipo->tipo_nombre ?? null;
                if ($nombre === null) {
                    continue;
                }

                $caramelosTipo[$nombre] = ($caramelosTipo[$nombre] ?? 0) + 1;

                $existente = CarameloTipo::where('tipo', $nombre)->first();
                if ($existente !== null) {
                    $existente->increment('cantidad');
                } else {
                    CarameloTipo::create(['tipo' => $nombre, 'cantidad' => 1]);
                }
            }
        }

        // EXP: recompensa completa por derrotado para el jugador y cada miembro del equipo
        $usuario = User::first();
        $nivelSalvaje = $usuario !== null ? $usuario->nivel() : 1;

        $expTotal = 0;
        foreach ($derrotados as $pokemonId) {
            $pokemon = $pokemons->get($pokemonId);
            if ($pokemon === null) {
                continue;
            }

            $expTotal += NivelHelper::expDerrota($pokemon->base_experience, $nivelSalvaje);
        }

        if ($usuario !== null && $expTotal > 0) {
            $usuario->increment('experiencia', $expTotal);
        }

        $equipo = $exploracion->team;
        if ($equipo !== null) {
            foreach ($equipo->members as $miembro) {
                $reclutado = $miembro->reclutado;
                if ($reclutado === null) {
                    continue;
                }

                /** @var array<string, int> $expActual */
                $expActual = $reclutado->exp ?? ['total' => 0];
                $expActual['total'] = ($expActual['total'] ?? 0) + $expTotal;
                $reclutado->update(['exp' => $expActual]);
            }
        }

        $eventos['resultado'] = $this->resumenResultado(
            $derrotados,
            $capturadosPorEspecie,
            $caramelosFamilia,
            $basePorCadena,
            $evPorStat,
            $caramelosTipo,
            $expTotal,
            $pokemons,
        );

        $exploracion->update(['regreso' => now(), 'eventos' => $eventos]);
    }

    /**
     * Resumen de recompensas persistido en eventos['resultado'] para la página
     * de exploraciones: avistados, capturados, caramelos familia/EV/tipo y exp.
     *
     * @param  list<int>  $derrotados
     * @param  array<int, int>  $capturadosPorEspecie
     * @param  array<int, int>  $caramelosFamilia
     * @param  array<int, Pokemon|null>  $basePorCadena
     * @param  array<int, int>  $evPorStat
     * @param  array<string, int>  $caramelosTipo
     * @param  \Illuminate\Database\Eloquent\Collection<int, Pokemon>  $pokemons
     * @return array<string, mixed>
     */
    private function resumenResultado(
        array $derrotados,
        array $capturadosPorEspecie,
        array $caramelosFamilia,
        array $basePorCadena,
        array $evPorStat,
        array $caramelosTipo,
        int $expTotal,
        \Illuminate\Database\Eloquent\Collection $pokemons,
    ): array {
        $idsUnicos = array_values(array_unique($derrotados));
        sort($idsUnicos);

        $avistados = [];
        foreach ($idsUnicos as $id) {
            $pokemon = $pokemons->get($id);
            if ($pokemon === null) {
                continue;
            }

            $avistados[] = ['pokemon_id' => $id, 'nombre' => $pokemon->name];
        }

        $capturados = [];
        ksort($capturadosPorEspecie);
        foreach ($capturadosPorEspecie as $id => $cantidad) {
            $pokemon = $pokemons->get($id);
            if ($pokemon === null) {
                continue;
            }

            $capturados[] = [
                'pokemon_id' => $id,
                'nombre' => $pokemon->name,
                'cantidad' => $cantidad,
            ];
        }

        $caramelosFamiliaResumen = [];
        ksort($caramelosFamilia);
        foreach ($caramelosFamilia as $cadenaId => $cantidad) {
            $caramelosFamiliaResumen[] = [
                'evolution_chain_id' => $cadenaId,
                'nombre' => $basePorCadena[$cadenaId]?->name,
                'cantidad' => $cantidad,
            ];
        }

        $caramelosEvResumen = [];
        ksort($evPorStat);
        foreach ($evPorStat as $stat => $cantidad) {
            $caramelosEvResumen[] = ['stat' => $stat, 'cantidad' => $cantidad];
        }

        $caramelosTipoResumen = [];
        ksort($caramelosTipo);
        foreach ($caramelosTipo as $tipo => $cantidad) {
            $caramelosTipoResumen[] = [
                'tipo' => $tipo,
                'slug' => $this->slugTipo($tipo),
                'cantidad' => $cantidad,
            ];
        }

        return [
            'avistados' => $avistados,
            'capturados' => $capturados,
            'caramelos_familia' => $caramelosFamiliaResumen,
            'caramelos_ev' => $caramelosEvResumen,
            'caramelos_tipo' => $caramelosTipoResumen,
            'exp' => $expTotal,
        ];
    }

    /**
     * Nombre de archivo (sin acentos, minúsculas) para la imagen del caramelo
     * de tipo: 'Eléctrico' → 'electrico', 'Dragón' → 'dragon'.
     */
    private function slugTipo(string $nombre): string
    {
        return strtolower(Str::ascii($nombre));
    }
}
