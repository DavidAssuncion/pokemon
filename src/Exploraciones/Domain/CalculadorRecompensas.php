<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Shared\Domain\NivelHelper;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Calcula las recompensas de una expedición a partir de los pokémon derrotados
 * y de los hallazgos de la bitácora. Dominio puro: sin Eloquent ni imports de
 * App; recibe datos normalizados.
 *
 * D3/RF-14: caramelos de tipo desde EXP tipada (floor((exp_tipo × 0.2)/100));
 * la cuenta del jugador recibe el 100 % de T y cada integrante floor((T×0.8)/3).
 * RF-08: el multiplicador de categoría se aplica (floor) a exp y caramelos.
 */
final class CalculadorRecompensas
{
    /**
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @param  callable(PokemonDerrotado):bool  $aleatorioCaptura  Decide si el
     *                                                              derrotado se captura.
     */
    public function calcular(
        Collection $derrotados,
        callable $aleatorioCaptura,
        int $nivelSalvaje,
        float $multiplicador = 1.0,
    ): ResultadoRecompensas {
        return new ResultadoRecompensas(
            capturas: $this->calcularCapturas($derrotados, $aleatorioCaptura),
            caramelosFamilia: $this->calcularCaramelosFamilia($derrotados, $multiplicador),
            caramelosEv: $this->calcularCaramelosEv($derrotados, $multiplicador),
            caramelosTipo: $this->calcularCaramelosTipo($derrotados, $nivelSalvaje, $multiplicador),
            expTotal: $this->calcularExp($derrotados, $nivelSalvaje, $multiplicador),
            expPorMiembro: $this->calcularExpPorMiembro($derrotados, $nivelSalvaje, $multiplicador),
        );
    }

    /**
     * Caramelos de los eventos hallazgo (D8): familia (resuelta por pokemon_id →
     * evolution_chain_id), EV (stat) y tipo (tipo_id → label).
     *
     * @param  Collection<int, array<string, mixed>>  $hallazgos
     * @param  array<int, int>  $chainPorPokemon  pokemon_id → evolution_chain_id
     * @return array{
     *     caramelosFamilia: Collection<int, RecompensaFamilia>,
     *     caramelosEv: Collection<int, RecompensaEv>,
     *     caramelosTipo: Collection<int, RecompensaTipo>,
     * }
     */
    public function calcularHallazgos(Collection $hallazgos, array $chainPorPokemon, float $multiplicadorCaramelos = 1.0): array
    {
        $familia = [];
        $ev = [];
        $tipo = [];

        foreach ($hallazgos as $hallazgo) {
            $cantidad = (int) ($hallazgo['cantidad'] ?? 1);
            if ($cantidad <= 0) {
                continue;
            }

            $this->acumularHallazgoFamilia($hallazgo, $chainPorPokemon, $cantidad, $familia);
            $this->acumularHallazgoEv($hallazgo, $cantidad, $ev);
            $this->acumularHallazgoTipo($hallazgo, $cantidad, $tipo);
        }

        return [
            'caramelosFamilia' => $this->aplicarMultiplicadorFamilia($familia, $multiplicadorCaramelos),
            'caramelosEv' => $this->aplicarMultiplicadorEv($ev, $multiplicadorCaramelos),
            'caramelosTipo' => $this->aplicarMultiplicadorTipo($tipo, $multiplicadorCaramelos),
        ];
    }

    /**
     * @param  array<string, mixed>  $hallazgo
     * @param  array<int, int>  $chainPorPokemon
     * @param  array<int, int>  $familia
     */
    private function acumularHallazgoFamilia(array $hallazgo, array $chainPorPokemon, int $cantidad, array &$familia): void
    {
        if (($hallazgo['subtype'] ?? null) !== 'caramelo_familia') {
            return;
        }

        $chainId = $chainPorPokemon[(int) ($hallazgo['pokemon_id'] ?? 0)] ?? null;
        if ($chainId !== null) {
            $familia[$chainId] = ($familia[$chainId] ?? 0) + $cantidad;
        }
    }

    /**
     * @param  array<string, mixed>  $hallazgo
     * @param  array<int, int>  $ev
     */
    private function acumularHallazgoEv(array $hallazgo, int $cantidad, array &$ev): void
    {
        if (($hallazgo['subtype'] ?? null) !== 'caramelo_ev') {
            return;
        }

        $stat = (int) ($hallazgo['stat'] ?? 0);
        if ($stat >= 1 && $stat <= 6) {
            $ev[$stat] = ($ev[$stat] ?? 0) + $cantidad;
        }
    }

    /**
     * @param  array<string, mixed>  $hallazgo
     * @param  array<string, int>  $tipo
     */
    private function acumularHallazgoTipo(array $hallazgo, int $cantidad, array &$tipo): void
    {
        if (($hallazgo['subtype'] ?? null) !== 'caramelo_tipo') {
            return;
        }

        $tipoPokemon = TipoPokemon::tryFrom((int) ($hallazgo['tipo_id'] ?? 0));
        if ($tipoPokemon !== null) {
            $tipo[$tipoPokemon->label()] = ($tipo[$tipoPokemon->label()] ?? 0) + $cantidad;
        }
    }

    /**
     * @param  array<int, int>  $familia
     * @return Collection<int, RecompensaFamilia>
     */
    private function aplicarMultiplicadorFamilia(array $familia, float $multiplicador): Collection
    {
        return collect($familia)
            ->map(fn (int $cantidad, int $chainId): RecompensaFamilia => new RecompensaFamilia(
                evolutionChainId: $chainId,
                cantidad: max(0, (int) floor($cantidad * $multiplicador)),
            ))
            ->values();
    }

    /**
     * @param  array<int, int>  $ev
     * @return Collection<int, RecompensaEv>
     */
    private function aplicarMultiplicadorEv(array $ev, float $multiplicador): Collection
    {
        return collect($ev)
            ->map(fn (int $cantidad, int $stat): RecompensaEv => new RecompensaEv(
                stat: $stat,
                cantidad: max(0, (int) floor($cantidad * $multiplicador)),
            ))
            ->values();
    }

    /**
     * @param  array<string, int>  $tipo
     * @return Collection<int, RecompensaTipo>
     */
    private function aplicarMultiplicadorTipo(array $tipo, float $multiplicador): Collection
    {
        return collect($tipo)
            ->map(fn (int $cantidad, string $label): RecompensaTipo => new RecompensaTipo(
                tipo: $label,
                cantidad: max(0, (int) floor($cantidad * $multiplicador)),
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
     * Caramelos de tipo (D3/RF-14): EXP tipada por victoria (1 tipo → 100 %,
     * 2 tipos → 50/50); caramelos = floor((exp_tipo × 0.2)/100). Sustituye al
     * antiguo "1 caramelo por tipo por derrotado".
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @return Collection<int, RecompensaTipo>
     */
    private function calcularCaramelosTipo(Collection $derrotados, int $nivelSalvaje, float $multiplicador): Collection
    {
        $expTipo = [];

        foreach ($derrotados as $pokemon) {
            $exp = NivelHelper::expDerrota($pokemon->baseExperience, $nivelSalvaje);
            $numTipos = count($pokemon->tipos);
            if ($numTipos === 0) {
                continue;
            }
            $reparto = $numTipos === 1 ? $exp : intdiv($exp, 2);

            foreach ($pokemon->tipos as $tipo) {
                $expTipo[$tipo] = ($expTipo[$tipo] ?? 0) + $reparto;
            }
        }

        return collect($expTipo)
            ->map(fn (int $exp, string $tipo): RecompensaTipo => new RecompensaTipo(
                tipo: $tipo,
                cantidad: max(0, (int) floor($exp * 0.2 / 100 * $multiplicador)),
            ))
            ->filter(fn (RecompensaTipo $recompensa): bool => $recompensa->cantidad > 0)
            ->values();
    }

    /**
     * EXP total (cuenta del jugador, 100 %): expDerrota por derrotado.
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     */
    private function calcularExp(Collection $derrotados, int $nivelSalvaje, float $multiplicador): int
    {
        $total = $derrotados->sum(
            fn (PokemonDerrotado $pokemon): int => NivelHelper::expDerrota($pokemon->baseExperience, $nivelSalvaje)
        );

        return max(0, (int) floor($total * $multiplicador));
    }

    /**
     * EXP por integrante (D3): cada miembro del equipo recibe floor((T×0.8)/3)
     * por cada derrota (reparto 80 % entre 3).
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     */
    private function calcularExpPorMiembro(Collection $derrotados, int $nivelSalvaje, float $multiplicador): int
    {
        $total = 0;

        foreach ($derrotados as $pokemon) {
            $exp = NivelHelper::expDerrota($pokemon->baseExperience, $nivelSalvaje);
            $total += (int) floor($exp * 0.8 / 3);
        }

        return max(0, (int) floor($total * $multiplicador));
    }
}
