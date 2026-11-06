<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Finalizacion;

use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Granjas\App\ResolverBonosFamilia;
use Src\Granjas\Domain\Collections\RefugiosHabitatCollection;
use Src\Shared\Domain\Ports\GeneradorAleatorio;
use Src\Shared\Domain\ProbabilidadCaptura;
use Src\Shared\Domain\Random\GeneradorAleatorioPredeterminado;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Domain\Sendas\PoliticaSenda;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Construye la tirada de captura de una expedición con el factor de hábitat,
 * refugio, Senda y modo. Usa el puerto de azar; no llama a mt_rand directamente.
 */
final class GeneradorTiradaCapturaExpedicion
{
    public function __construct(
        private readonly GeneradorAleatorio $generador = new GeneradorAleatorioPredeterminado(),
    ) {
    }

    /**
     * @param  callable():float|null  $aleatorio
     */
    public function crear(
        ?callable $aleatorio = null,
        ?ResolverBonosFamilia $bonos = null,
        ?RefugiosHabitatCollection $refugios = null,
        ?MapaSendaJugador $senda = null,
        ?int $nivelHabitat = null,
        float $factorModo = 1.0,
    ): callable {
        $aleatorio ??= fn (): float => $this->generador->entero(1, 100) / 100;

        $factorHabitat = match ($nivelHabitat) {
            1 => 1.0,
            2 => 0.5,
            3 => 0.3,
            default => 1.0,
        };

        return function (PokemonDerrotado $pokemon) use ($aleatorio, $bonos, $refugios, $senda, $factorHabitat, $factorModo): bool {
            $factorRefugio = 1.0;
            if ($bonos !== null && $refugios !== null && $pokemon->evolutionChainId !== null) {
                $factorRefugio = $bonos->factorCaptura($refugios, $pokemon->evolutionChainId);
            }

            $factorSenda = $senda !== null
                ? PoliticaSenda::factorCaptura($senda, self::tiposPokemon($pokemon))
                : 1.0;

            return ProbabilidadCaptura::intentarConFactores(
                $pokemon->captureRate,
                $factorHabitat,
                $factorRefugio,
                $aleatorio,
                $factorSenda * $factorModo,
            );
        };
    }

    /**
     * Convierte los labels de tipo del derrotado (español) al enum de dominio.
     *
     * @return list<TipoPokemon>
     */
    private static function tiposPokemon(PokemonDerrotado $pokemon): array
    {
        $tipoPorLabel = [];
        foreach (TipoPokemon::cases() as $tipo) {
            $tipoPorLabel[$tipo->label()] = $tipo;
        }

        $tipos = [];
        foreach ($pokemon->tipos as $label) {
            if (isset($tipoPorLabel[$label])) {
                $tipos[] = $tipoPorLabel[$label];
            }
        }

        return $tipos;
    }
}
