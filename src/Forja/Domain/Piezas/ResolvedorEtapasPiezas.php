<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Piezas;

use Src\Pokemon\Domain\Evolution\AristaEvolutiva;
use Src\Pokemon\Domain\Evolution\FamiliaEvolutiva;
use Src\Pokemon\Domain\Evolution\PokemonFamilia;
use Src\Pokemon\Domain\Evolution\ResolvedorFamiliaEvolutiva;

/**
 * Resuelve la etapa de cada especie de una familia evolutiva para las piezas.
 *
 * Delega en `ResolvedorFamiliaEvolutiva` con `multiRaiz: true` y
 * `maxEtapas: null`: soporta familias ramificadas (Eevee) y de más de tres
 * etapas sin el fallback peligroso del resolvedor de hábitats.
 */
final class ResolvedorEtapasPiezas
{
    /**
     * @param  list<PokemonFamilia>  $nodos
     * @param  list<AristaEvolutiva>  $aristas
     */
    public function resolver(array $nodos, array $aristas): FamiliaEvolutiva
    {
        return (new ResolvedorFamiliaEvolutiva())->resolver(
            $nodos,
            $aristas,
            multiRaiz: true,
            maxEtapas: null,
        );
    }
}
