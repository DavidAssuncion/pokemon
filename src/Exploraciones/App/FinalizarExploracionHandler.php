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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use LogicException;
use Src\Shared\Bus\Command;
use Src\Shared\Bus\CommandHandler;
use Src\Shared\Bus\UnitOfWork;
use Src\Shared\Domain\NivelHelper;

final class FinalizarExploracionHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function handle(Command $command): mixed
    {
        if (! $command instanceof FinalizarExploracionCommand) {
            throw new LogicException('FinalizarExploracionHandler requires a FinalizarExploracionCommand.');
        }

        $exploracion = $command->exploracion;

        // Idempotencia: si ya regresó, no repartir recompensas otra vez.
        if ($exploracion->regreso !== null) {
            return null;
        }

        // Guard anti-stale: aunque el modelo en memoria diga lo contrario, si la
        // DB ya tiene regreso (p. ej. otra corrida del scheduler), no duplicar.
        if (ExploracionActiva::whereKey($exploracion->getKey())->value('regreso') !== null) {
            return null;
        }

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

        // Pokedex (AVISTADO): los jobs se despachan tras el commit (afterCommit)
        // para que con cola sync no corran si la transacción revierte.
        $pokedexIds = [];
        $capturadosPorEspecie = [];
        foreach ($derrotados as $pokemonId) {
            $pokemon = $pokemons->get($pokemonId);
            if ($pokemon === null) {
                continue;
            }

            $pokedexIds[] = $pokemonId;

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

        if ($pokedexIds !== []) {
            $this->unitOfWork->afterCommit(function () use ($pokedexIds): void {
                foreach ($pokedexIds as $pokemonId) {
                    ActualizarPokedexJob::dispatch($pokemonId, 'AVISTADO');
                }
            });
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

        return null;
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
     * @param  Collection<int, Pokemon>  $pokemons
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
        Collection $pokemons,
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
