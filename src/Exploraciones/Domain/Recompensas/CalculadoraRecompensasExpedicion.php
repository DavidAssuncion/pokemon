<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\Recompensas;

use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\EconomiaRecompensas;
use Src\Exploraciones\Domain\Perdidas\CalculadoraPerdidas;
use Src\Exploraciones\Domain\Perdidas\ColeccionObjetosPerdidos;
use Src\Exploraciones\Domain\Perdidas\ObjetoPerdido;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Shared\Domain\Sendas\MapaSendaJugador;

/**
 * Compone las recompensas de una expedición: base por derrotados, hallazgos,
 * botín y pérdidas por derrota. Dominio puro; recibe el botín ya resuelto.
 */
final class CalculadoraRecompensasExpedicion
{
    public function __construct(
        private readonly CalculadorRecompensas $calculador,
        private readonly CalculadoraPerdidas $perdidas = new CalculadoraPerdidas(),
    ) {
    }

    /**
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     * @param  callable(PokemonDerrotado):bool  $aleatorioCaptura
     */
    public function calcularBase(
        Collection $derrotados,
        callable $aleatorioCaptura,
        int $nivelSalvaje,
        float $multiplicadorCaramelos,
        EconomiaRecompensas $economia,
        float $multiplicadorExp,
        MapaSendaJugador $senda,
    ): ResultadoRecompensas {
        return $this->calculador->calcular(
            $derrotados,
            $aleatorioCaptura,
            $nivelSalvaje,
            $multiplicadorCaramelos,
            $economia,
            multiplicadorExp: $multiplicadorExp,
            senda: $senda,
        );
    }

    /**
     * @param  array<int, int>  $chainPorPokemon
     */
    public function sumarHallazgos(
        ResultadoRecompensas $recompensas,
        ColeccionEventosExploracion $hallazgos,
        array $chainPorPokemon,
        float $multiplicadorCaramelos,
        int $bonusCaramelos,
    ): ResultadoRecompensas {
        $calculados = $this->calculador->calcularHallazgosDeEventos(
            $hallazgos,
            $chainPorPokemon,
            $multiplicadorCaramelos,
            $bonusCaramelos,
        );

        return $recompensas->sumarHallazgos(
            $calculados->caramelosFamilia,
            $calculados->caramelosEv,
            $calculados->caramelosTipo,
        );
    }

    public function sumarBotin(
        ResultadoRecompensas $recompensas,
        RecompensaItemCollection $botin,
    ): ResultadoRecompensas {
        return $recompensas->sumarBotin($botin);
    }

    /**
     * @return list<ObjetoPerdido>
     */
    public function calcularPerdidas(ResultadoRecompensas $recompensas, bool $huboDerrota): array
    {
        return $this->perdidas->calcular($recompensas, $huboDerrota)->all();
    }

    /**
     * @param  list<ObjetoPerdido>  $perdidas
     */
    public function restarPerdidas(ResultadoRecompensas $recompensas, array $perdidas): ResultadoRecompensas
    {
        return $this->perdidas->restar($recompensas, new ColeccionObjetosPerdidos($perdidas));
    }
}
