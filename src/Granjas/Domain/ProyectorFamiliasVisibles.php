<?php

declare(strict_types=1);

namespace Src\Granjas\Domain;

use Src\Granjas\Domain\Collections\TiposPokemonCollection;
use Src\Habitats\Presentation\ColeccionPokemonFamilia;
use Src\Habitats\Presentation\DTOFamiliaDisponible;
use Src\Habitats\Presentation\DTOFamiliasDisponibles;
use Src\Habitats\Presentation\DTOPokemonFamilia;
use Src\Shared\Collections\IntCollection;

/**
 * Proyecta las familias de un hábitat a lo que el jugador ha avistado:
 * solo se conservan familias con al menos un miembro visto, el representante
 * (`base`) es el miembro visto de menor etapa y `evolutions`/`types` solo
 * contienen miembros vistos.
 *
 * Es una política de dominio pura: no consulta nada, no muta los DTO de
 * entrada (compartidos con el catálogo de hábitats) y conserva el orden de
 * las familias recibido.
 */
final class ProyectorFamiliasVisibles
{
    public function proyectar(
        DTOFamiliasDisponibles $familias,
        IntCollection $pokemonVistos,
        TiposPokemonCollection $tipos,
    ): DTOFamiliasDisponibles {
        $vistos = array_fill_keys($pokemonVistos->all(), true);
        $proyectadas = new DTOFamiliasDisponibles();

        foreach ($familias->all() as $familia) {
            $miembros = $this->miembrosVistos($familia, $vistos);

            if ($miembros->isEmpty()) {
                continue;
            }

            $ordenados = $miembros->sort($this->comparador());
            $base = $ordenados->first();

            if (! $base instanceof DTOPokemonFamilia) {
                continue;
            }

            $evoluciones = new ColeccionPokemonFamilia();
            foreach ($ordenados as $miembro) {
                if (! $miembro instanceof DTOPokemonFamilia || $miembro->id === $base->id) {
                    continue;
                }

                $evoluciones->add($miembro);
            }

            $ids = new IntCollection([$base->id, ...$evoluciones->map(
                fn (DTOPokemonFamilia $miembro): int => $miembro->id,
            )]);

            $proyectadas->add(new DTOFamiliaDisponible(
                evolutionChainId: $familia->evolutionChainId,
                base: $base,
                evolutions: $evoluciones,
                types: $tipos->tiposDe($ids),
            ));
        }

        return $proyectadas;
    }

    /**
     * Miembros de la familia (base + evoluciones) que el jugador ha visto.
     *
     * @param  array<int, true>  $vistos
     */
    private function miembrosVistos(DTOFamiliaDisponible $familia, array $vistos): ColeccionPokemonFamilia
    {
        $miembros = new ColeccionPokemonFamilia();

        if (isset($vistos[$familia->base->id])) {
            $miembros->add($familia->base);
        }

        foreach ($familia->evolutions as $evolucion) {
            if (! $evolucion instanceof DTOPokemonFamilia || ! isset($vistos[$evolucion->id])) {
                continue;
            }

            $miembros->add($evolucion);
        }

        return $miembros;
    }

    /**
     * Orden del representante: etapa, luego especie, luego id (mismo criterio
     * de negocio que el "primer integrante" de la familia).
     */
    private function comparador(): callable
    {
        return static function (object $a, object $b): int {
            if (! $a instanceof DTOPokemonFamilia || ! $b instanceof DTOPokemonFamilia) {
                return 0;
            }

            return [$a->stage, $a->speciesId, $a->id] <=> [$b->stage, $b->speciesId, $b->id];
        };
    }
}
