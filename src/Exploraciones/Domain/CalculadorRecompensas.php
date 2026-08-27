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

/**
 * Calcula las recompensas de una exploración a partir de los pokémon derrotados.
 * Dominio puro: sin Eloquent ni imports de App; recibe datos normalizados.
 */
final class CalculadorRecompensas
{
    /**
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @param  callable(PokemonDerrotado):bool  $aleatorioCaptura  Decide si el
     *                                                              derrotado se captura.
     */
    public function calcular(Collection $derrotados, callable $aleatorioCaptura, int $nivelSalvaje): ResultadoRecompensas
    {
        return new ResultadoRecompensas(
            capturas: $this->calcularCapturas($derrotados, $aleatorioCaptura),
            caramelosFamilia: $this->calcularCaramelosFamilia($derrotados),
            caramelosEv: $this->calcularCaramelosEv($derrotados),
            caramelosTipo: $this->calcularCaramelosTipo($derrotados),
            expTotal: $this->calcularExp($derrotados, $nivelSalvaje),
        );
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
    private function calcularCaramelosFamilia(Collection $derrotados): Collection
    {
        return $derrotados
            ->filter(fn (PokemonDerrotado $pokemon): bool => $pokemon->evolutionChainId !== null)
            ->groupBy('evolutionChainId')
            ->map(fn (Collection $familia): RecompensaFamilia => new RecompensaFamilia(
                evolutionChainId: $familia->first()->evolutionChainId,
                cantidad: $familia->sum('fase'),
            ))
            ->values();
    }

    /**
     * Caramelos EV: effort acumulado por stat (solo stats con effort > 0).
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @return Collection<int, RecompensaEv>
     */
    private function calcularCaramelosEv(Collection $derrotados): Collection
    {
        return $derrotados
            ->flatMap(fn (PokemonDerrotado $pokemon): Collection => $pokemon->stats)
            ->filter(fn (array $stat): bool => $stat['effort'] > 0)
            ->groupBy('stat')
            ->map(fn (Collection $stats): RecompensaEv => new RecompensaEv(
                stat: $stats->first()['stat'],
                cantidad: $stats->sum('effort'),
            ))
            ->values();
    }

    /**
     * Caramelos de tipo: 1 por cada tipo del pokémon derrotado, agrupados por tipo.
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @return Collection<int, RecompensaTipo>
     */
    private function calcularCaramelosTipo(Collection $derrotados): Collection
    {
        return $derrotados
            ->flatMap(fn (PokemonDerrotado $pokemon): array => $pokemon->tipos)
            ->groupBy(fn (string $tipo): string => $tipo)
            ->map(fn (Collection $tipos, string $tipo): RecompensaTipo => new RecompensaTipo(
                tipo: $tipo,
                cantidad: $tipos->count(),
            ))
            ->values();
    }

    /**
     * EXP total: expDerrota(base_experience, nivel_salvaje) por derrotado.
     *
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     */
    private function calcularExp(Collection $derrotados, int $nivelSalvaje): int
    {
        return $derrotados->sum(
            fn (PokemonDerrotado $pokemon): int => NivelHelper::expDerrota($pokemon->baseExperience, $nivelSalvaje)
        );
    }
}
