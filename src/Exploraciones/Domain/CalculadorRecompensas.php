<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\DataTransferObjects\HallazgosCalculados;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Exploraciones\Domain\ValueObjects\EventoExploracion;
use Src\Shared\Domain\MultiplicadoresDificultad;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Calcula las recompensas de una expedición a partir de los pokémon derrotados
 * y de los hallazgos de la bitácora. Dominio puro: sin Eloquent ni imports de
 * App; recibe datos normalizados.
 *
 * D3/RF-14: caramelos de tipo desde EXP tipada (floor(exp_tipo / 250)).
 * La política `EconomiaRecompensas` define el factor y el reparto: una sola
 * ruta calcula pool, fracción de cuenta y resto entre miembros destino para
 * ambas políticas. `multiplicadorExp` permite separar EXP y caramelos.
 * RF-08: el multiplicador de categoría se aplica (floor) a exp y caramelos.
 */
final class CalculadorRecompensas
{
    /**
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @param  callable(PokemonDerrotado):bool  $aleatorioCaptura  Decide si el
     *                                                              derrotado se captura.
     * @param  EconomiaRecompensas|null  $economia  Política económica; `null`
     *                                              equivale a `legado()`.
     * @param  float|null  $multiplicadorExp  Multiplicador de EXP; `null` usa
     *                                        `$multiplicador`. Permite separar
     *                                        EXP y caramelos (mazmorra).
     * @param  MultiplicadoresDificultad|null  $dificultad  Multiplicadores por
     *                                                     perfil del rival; `null`
     *                                                     equivale a sin dificultad
     *                                                     (1,00 en los tres ejes).
     * @param  MapaSendaJugador|null  $senda  Sendas del jugador; `null` equivale a
     *                                       sin bonos pasivos.
     */
    public function calcular(
        Collection $derrotados,
        callable $aleatorioCaptura,
        int $nivelSalvaje,
        float $multiplicador = 1.0,
        ?EconomiaRecompensas $economia = null,
        ?float $multiplicadorExp = null,
        ?MultiplicadoresDificultad $dificultad = null,
        ?MapaSendaJugador $senda = null,
    ): ResultadoRecompensas {
        $economia ??= EconomiaRecompensas::legado();
        $multiplicadorExp ??= $multiplicador;
        $dificultad ??= MultiplicadoresDificultad::sinDificultad();

        // Los caramelos usan el multiplicador de categoría × factor × dificultad;
        // la EXP usa su propio multiplicador × factor (sin dificultad). El factor
        // y cada multiplicador se aplican UNA sola vez.
        $multiplicadorCaramelos = $multiplicador * $economia->factor;
        $multiplicadorFamilia = $multiplicadorCaramelos * $dificultad->familia();
        $multiplicadorEv = $multiplicadorCaramelos * $dificultad->ev();
        $multiplicadorTipo = $multiplicadorCaramelos * $dificultad->tipo();

        $exp = $this->calcularExpRepartida($derrotados, $nivelSalvaje, $multiplicadorExp, $economia);

        return new ResultadoRecompensas(
            capturas: $this->calcularCapturas($derrotados, $aleatorioCaptura),
            caramelosFamilia: $this->calcularCaramelosFamilia($derrotados, $multiplicadorFamilia),
            caramelosEv: $this->calcularCaramelosEv($derrotados, $multiplicadorEv),
            caramelosTipo: $this->calcularCaramelosTipo($derrotados, $nivelSalvaje, $multiplicadorTipo, $senda),
            expTotal: $exp['cuenta'],
            expPorMiembro: $exp['destino'],
            expTipoPorMiembro: $exp['tiposDestino'],
            expTipoCuenta: $exp['tiposCuenta'],
        );
    }

    /**
     * Caramelos de los eventos hallazgo (D8): familia (resuelta por pokemon_id →
     * evolution_chain_id), EV (stat) y tipo (tipo_id → label).
     *
     * @param  Collection<int, array<string, mixed>>  $hallazgos  Ya filtrados por esHallazgo().
     * @param  array<int, int>  $chainPorPokemon  pokemon_id → evolution_chain_id
     *
     * @deprecated Frontera (BC con tests unitarios que pasan arrays). El dominio
     * recomienda calcularHallazgosDeEventos() con la colección tipada.
     */
    public function calcularHallazgos(Collection $hallazgos, array $chainPorPokemon, float $multiplicadorCaramelos = 1.0, int $bonusCaramelos = 0): HallazgosCalculados
    {
        return $this->calcularHallazgosDeEventos(
            ColeccionEventosExploracion::desdeArray(array_values($hallazgos->all())),
            $chainPorPokemon,
            $multiplicadorCaramelos,
            $bonusCaramelos,
        );
    }

    /**
     * Caramelos de los eventos hallazgo (D8): familia (resuelta por pokemon_id →
     * evolution_chain_id), EV (stat) y tipo (tipo_id → label).
     *
     * @param  array<int, int>  $chainPorPokemon  pokemon_id → evolution_chain_id
     */
    public function calcularHallazgosDeEventos(ColeccionEventosExploracion $eventos, array $chainPorPokemon, float $multiplicadorCaramelos = 1.0, int $bonusCaramelos = 0): HallazgosCalculados
    {
        // Cada grupo acumula la cantidad base y el número de hallazgos que lo
        // alimentan: el multiplicador nunca deja un hallazgo por debajo de 1
        // caramelo (dos hallazgos de la misma clase dan ≥2, no 1).
        /** @var array<int, array{cantidad:int, conteo:int}> $familia */
        $familia = [];
        /** @var array<int, array{cantidad:int, conteo:int}> $ev */
        $ev = [];
        /** @var array<string, array{cantidad:int, conteo:int}> $tipo */
        $tipo = [];

        /** @var list<EventoExploracion> $items */
        $items = $eventos->toList();

        foreach ($items as $evento) {
            if (! $evento->esHallazgo()) {
                continue;
            }

            $cantidadBase = $evento->cantidad ?? 1;
            if ($cantidadBase <= 0) {
                continue;
            }

            // Rango Recolector: cada hallazgo rinde ×(1 + bonus) caramelos.
            $cantidad = $cantidadBase * (1 + $bonusCaramelos);

            $this->acumularHallazgoFamiliaDeEvento($evento, $chainPorPokemon, $cantidad, $familia);
            $this->acumularHallazgoEvDeEvento($evento, $cantidad, $ev);
            $this->acumularHallazgoTipoDeEvento($evento, $cantidad, $tipo);
        }

        return new HallazgosCalculados(
            caramelosFamilia: $this->aplicarMultiplicadorFamilia($familia, $multiplicadorCaramelos),
            caramelosEv: $this->aplicarMultiplicadorEv($ev, $multiplicadorCaramelos),
            caramelosTipo: $this->aplicarMultiplicadorTipo($tipo, $multiplicadorCaramelos),
        );
    }

    /**
     * @param  array<int, int>  $chainPorPokemon
     * @param  array<int, array{cantidad:int, conteo:int}>  $familia
     */
    private function acumularHallazgoFamiliaDeEvento(EventoExploracion $hallazgo, array $chainPorPokemon, int $cantidad, array &$familia): void
    {
        if ($hallazgo->subtype !== 'caramelo_familia') {
            return;
        }

        $chainId = $chainPorPokemon[$hallazgo->pokemonId ?? 0] ?? null;
        if ($chainId !== null) {
            $familia[$chainId] ??= ['cantidad' => 0, 'conteo' => 0];
            $familia[$chainId]['cantidad'] += $cantidad;
            $familia[$chainId]['conteo']++;
        }
    }

    /**
     * @param  array<int, array{cantidad:int, conteo:int}>  $ev
     */
    private function acumularHallazgoEvDeEvento(EventoExploracion $hallazgo, int $cantidad, array &$ev): void
    {
        if ($hallazgo->subtype !== 'caramelo_ev') {
            return;
        }

        $stat = $hallazgo->stat;
        if ($stat !== null && $stat >= 1 && $stat <= 6) {
            $ev[$stat] ??= ['cantidad' => 0, 'conteo' => 0];
            $ev[$stat]['cantidad'] += $cantidad;
            $ev[$stat]['conteo']++;
        }
    }

    /**
     * @param  array<string, array{cantidad:int, conteo:int}>  $tipo
     */
    private function acumularHallazgoTipoDeEvento(EventoExploracion $hallazgo, int $cantidad, array &$tipo): void
    {
        if ($hallazgo->subtype !== 'caramelo_tipo') {
            return;
        }

        $tipoPokemon = TipoPokemon::tryFrom($hallazgo->tipoId ?? 0);
        if ($tipoPokemon !== null) {
            $label = $tipoPokemon->label();
            $tipo[$label] ??= ['cantidad' => 0, 'conteo' => 0];
            $tipo[$label]['cantidad'] += $cantidad;
            $tipo[$label]['conteo']++;
        }
    }

    /**
     * @param  array<int, array{cantidad:int, conteo:int}>  $familia
     * @return Collection<int, RecompensaFamilia>
     */
    private function aplicarMultiplicadorFamilia(array $familia, float $multiplicador): Collection
    {
        return collect($familia)
            ->map(fn (array $datos, int $chainId): RecompensaFamilia => new RecompensaFamilia(
                evolutionChainId: $chainId,
                cantidad: max($datos['conteo'], (int) floor($datos['cantidad'] * $multiplicador)),
            ))
            ->values();
    }

    /**
     * @param  array<int, array{cantidad:int, conteo:int}>  $ev
     * @return Collection<int, RecompensaEv>
     */
    private function aplicarMultiplicadorEv(array $ev, float $multiplicador): Collection
    {
        return collect($ev)
            ->map(fn (array $datos, int $stat): RecompensaEv => new RecompensaEv(
                stat: $stat,
                cantidad: max($datos['conteo'], (int) floor($datos['cantidad'] * $multiplicador)),
            ))
            ->values();
    }

    /**
     * @param  array<string, array{cantidad:int, conteo:int}>  $tipo
     * @return Collection<int, RecompensaTipo>
     */
    private function aplicarMultiplicadorTipo(array $tipo, float $multiplicador): Collection
    {
        return collect($tipo)
            ->map(fn (array $datos, string $label): RecompensaTipo => new RecompensaTipo(
                tipo: $label,
                cantidad: max($datos['conteo'], (int) floor($datos['cantidad'] * $multiplicador)),
            ))
            ->filter(fn (RecompensaTipo $recompensa): bool => $recompensa->cantidad > 0)
            ->values();
    }

    /**
     * Capturas: una tirada por derrotado, agrupadas por pokemon_id.
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @return Collection<int, RecompensaCaptura>
     */
    private function calcularCapturas(Collection $derrotados, callable $aleatorioCaptura): Collection
    {
        return $derrotados
            ->filter(fn (PokemonDerrotado $pokemon): bool => $aleatorioCaptura($pokemon))
            ->groupBy('id')
            ->map(fn (Collection $capturas): RecompensaCaptura => new RecompensaCaptura(
                pokemonId: $capturas->first()->id,
                cantidad: $capturas->count(),
            ))
            ->values();
    }

    /**
     * Caramelos de familia: fase × nº de derrotados por cadena evolutiva.
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @return Collection<int, RecompensaFamilia>
     */
    private function calcularCaramelosFamilia(Collection $derrotados, float $multiplicador): Collection
    {
        return $derrotados
            ->filter(fn (PokemonDerrotado $pokemon): bool => $pokemon->evolutionChainId !== null)
            ->groupBy('evolutionChainId')
            ->map(fn (Collection $familia): RecompensaFamilia => new RecompensaFamilia(
                evolutionChainId: $familia->first()->evolutionChainId,
                cantidad: max(0, (int) floor($familia->sum('fase') * $multiplicador)),
            ))
            ->values();
    }

    /**
     * Caramelos EV: effort acumulado por stat (solo stats con effort > 0).
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @return Collection<int, RecompensaEv>
     */
    private function calcularCaramelosEv(Collection $derrotados, float $multiplicador): Collection
    {
        return $derrotados
            ->flatMap(fn (PokemonDerrotado $pokemon): Collection => $pokemon->stats)
            ->filter(fn (array $stat): bool => $stat['effort'] > 0)
            ->groupBy('stat')
            ->map(fn (Collection $stats): RecompensaEv => new RecompensaEv(
                stat: $stats->first()['stat'],
                cantidad: max(0, (int) floor($stats->sum('effort') * $multiplicador)),
            ))
            ->values();
    }

    /**
     * EXP de un derrotado repartida por tipo (D3/RF-14): 1 tipo → 100 %,
     * 2 tipos → 50/50 (intdiv). null si el pokémon no tiene tipos.
     */
    private function expRepartidaPorTipo(PokemonDerrotado $pokemon, int $nivelSalvaje): ?int
    {
        $numTipos = count($pokemon->tipos);
        if ($numTipos === 0) {
            return null;
        }

        $exp = NivelHelper::expDerrota($pokemon->baseExperience, $nivelSalvaje);

        return $numTipos === 1 ? $exp : intdiv($exp, 2);
    }

    /**
     * Caramelos de tipo (D3/RF-14/plan v5): EXP tipada por victoria (1 tipo →
     * 100 %, 2 tipos → 50/50); caramelos = floor(exp_tipo / 250 × M × m_tipo
     * × (1 + bono)). La Senda del tipo se aplica una sola vez sobre el mismo
     * `floor` que el resto de factores. Sustituye al antiguo "1 caramelo por
     * tipo por derrotado".
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @return Collection<int, RecompensaTipo>
     */
    private function calcularCaramelosTipo(
        Collection $derrotados,
        int $nivelSalvaje,
        float $multiplicador,
        ?MapaSendaJugador $senda = null,
    ): Collection {
        $expTipo = [];

        foreach ($derrotados as $pokemon) {
            $reparto = $this->expRepartidaPorTipo($pokemon, $nivelSalvaje);
            if ($reparto === null) {
                continue;
            }

            foreach ($pokemon->tipos as $tipo) {
                $expTipo[$tipo] = ($expTipo[$tipo] ?? 0) + $reparto;
            }
        }

        $tipoPorLabel = [];
        foreach (TipoPokemon::cases() as $tipoPokemon) {
            $tipoPorLabel[$tipoPokemon->label()] = $tipoPokemon;
        }

        return collect($expTipo)
            ->map(function (int $exp, string $tipo) use ($multiplicador, $senda, $tipoPorLabel): RecompensaTipo {
                $tipoPokemon = $tipoPorLabel[$tipo] ?? null;
                $bono = $tipoPokemon !== null && $senda !== null
                    ? $senda->bonoCaramelo($tipoPokemon)
                    : 0.0;

                return new RecompensaTipo(
                    tipo: $tipo,
                    // Un solo floor: el bono de Senda multiplica antes de truncar.
                    cantidad: max(0, (int) floor($exp / 250 * $multiplicador * (1 + $bono))),
                );
            })
            ->filter(fn (RecompensaTipo $recompensa): bool => $recompensa->cantidad > 0)
            ->values();
    }

    /**
     * Reparto único de EXP para ambas políticas: un solo truncado sobre el
     * agregado (× multiplicador de EXP × factor), fracción de cuenta y el resto
     * dividido entre los miembros destino con intdiv. Invariante:
     * cuenta + destino × miembrosDestino <= pool; la diferencia es el resto de
     * intdiv. El mismo tratamiento se aplica a la EXP agregada de cada tipo.
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @return array{cuenta:int, destino:int, tiposCuenta:array<string, int>, tiposDestino:array<string, int>}
     */
    private function calcularExpRepartida(
        Collection $derrotados,
        int $nivelSalvaje,
        float $multiplicadorExp,
        EconomiaRecompensas $economia,
    ): array {
        $total = $derrotados->sum(
            fn (PokemonDerrotado $pokemon): int => NivelHelper::expDerrota($pokemon->baseExperience, $nivelSalvaje)
        );

        $pool = max(0, (int) floor($total * $multiplicadorExp * $economia->factor));
        $cuenta = (int) floor($pool * $economia->fraccionCuenta);
        $destino = intdiv($pool - $cuenta, $economia->miembrosDestino);

        $expTipo = [];
        foreach ($derrotados as $pokemon) {
            $reparto = $this->expRepartidaPorTipo($pokemon, $nivelSalvaje);
            if ($reparto === null) {
                continue;
            }

            foreach ($pokemon->tipos as $tipo) {
                $expTipo[$tipo] = ($expTipo[$tipo] ?? 0) + $reparto;
            }
        }

        $tiposCuenta = [];
        $tiposDestino = [];
        foreach ($expTipo as $tipo => $exp) {
            $poolTipo = max(0, (int) floor($exp * $multiplicadorExp * $economia->factor));
            $cuentaTipo = (int) floor($poolTipo * $economia->fraccionCuenta);
            $destinoTipo = intdiv($poolTipo - $cuentaTipo, $economia->miembrosDestino);

            if ($cuentaTipo > 0) {
                $tiposCuenta[$tipo] = $cuentaTipo;
            }
            if ($destinoTipo > 0) {
                $tiposDestino[$tipo] = $destinoTipo;
            }
        }

        return [
            'cuenta' => $cuenta,
            'destino' => $destino,
            'tiposCuenta' => $tiposCuenta,
            'tiposDestino' => $tiposDestino,
        ];
    }
}
