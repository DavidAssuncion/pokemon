<?php

declare(strict_types=1);

namespace Src\Pokemon\Domain\Evolution;

/**
 * Resuelve una familia evolutiva a partir de los nodos y aristas ya cargados.
 *
 * El recorrido se hace por componentes conexas: cada componente se recorre en
 * anchura desde su raíz (nodo sin arista entrante interna) o, si es un ciclo
 * sin raíz, desde su nodo de menor species id. `visited` evita bucles y
 * `maxEtapas` permite el modo compatibilidad con el resolvedor de hábitats.
 */
final class ResolvedorFamiliaEvolutiva
{
    private const SIN_PADRE = PHP_INT_MIN;

    /**
     * @param  list<PokemonFamilia>  $nodos
     * @param  list<AristaEvolutiva>  $aristas
     */
    public function resolver(
        array $nodos,
        array $aristas,
        bool $multiRaiz = true,
        ?int $maxEtapas = null,
    ): FamiliaEvolutiva {
        $porSpecies = $this->normalizarNodos($nodos);

        if ($porSpecies === []) {
            return new FamiliaEvolutiva();
        }

        $padres = $this->indexarPadres($aristas, $porSpecies);
        $hijos = $this->indexarHijos($padres, $porSpecies);
        $componentes = $this->componentes($porSpecies, $this->construirAdyacencia($padres));

        $componentesAProcesar = $multiRaiz
            ? $componentes
            : $this->seleccionarComponenteCompatibilidad($componentes, $padres, $porSpecies);

        /** @var list<MiembroFamiliaEvolutiva> $miembros */
        $miembros = [];

        foreach ($componentesAProcesar as $componente) {
            $raiz = $this->raizDe($componente, $padres);

            foreach ($this->recorrer($raiz, $porSpecies, $padres, $hijos, $maxEtapas) as $miembro) {
                $miembros[] = $miembro;
            }
        }

        usort($miembros, $this->comparar(...));

        return new FamiliaEvolutiva($miembros);
    }

    /**
     * Deduplica por id, ordena por species id e id, y se queda con el primer
     * nodo de cada species id.
     *
     * @param  list<PokemonFamilia>  $nodos
     * @return array<int, PokemonFamilia>
     */
    private function normalizarNodos(array $nodos): array
    {
        $porId = [];

        foreach ($nodos as $nodo) {
            $porId[$nodo->id] = $nodo;
        }

        $nodos = array_values($porId);
        usort(
            $nodos,
            fn (PokemonFamilia $a, PokemonFamilia $b): int => [$a->speciesId, $a->id] <=> [$b->speciesId, $b->id],
        );

        $porSpecies = [];

        foreach ($nodos as $nodo) {
            $porSpecies[$nodo->speciesId] ??= $nodo;
        }

        return $porSpecies;
    }

    /**
     * Indexa una única arista entrante por especie evolucionada. Se ignoran las
     * aristas externas al conjunto y los bucles; si hay duplicados, gana la de
     * menor nivel mínimo.
     *
     * @param  list<AristaEvolutiva>  $aristas
     * @param  array<int, PokemonFamilia>  $porSpecies
     * @return array<int, AristaEvolutiva>
     */
    private function indexarPadres(array $aristas, array $porSpecies): array
    {
        $padres = [];

        foreach ($aristas as $arista) {
            if (! isset($porSpecies[$arista->haciaSpeciesId], $porSpecies[$arista->desdeSpeciesId])) {
                continue;
            }

            if ($arista->haciaSpeciesId === $arista->desdeSpeciesId) {
                continue;
            }

            $anterior = $padres[$arista->haciaSpeciesId] ?? null;

            if ($anterior === null || $this->nivelOrden($arista) < $this->nivelOrden($anterior)) {
                $padres[$arista->haciaSpeciesId] = $arista;
            }
        }

        return $padres;
    }

    /**
     * Agrupa los hijos por especie padre y los ordena por
     * `(nivel mínimo, species id, id)`; la posición resultante es el
     * `branchIndex`.
     *
     * @param  array<int, AristaEvolutiva>  $padres
     * @param  array<int, PokemonFamilia>  $porSpecies
     * @return array<int, list<int>>
     */
    private function indexarHijos(array $padres, array $porSpecies): array
    {
        $hijos = [];

        foreach ($padres as $haciaSpeciesId => $arista) {
            $hijos[$arista->desdeSpeciesId][] = $haciaSpeciesId;
        }

        foreach ($hijos as $padreSpeciesId => $lista) {
            usort(
                $lista,
                fn (int $a, int $b): int => [
                    $this->nivelOrden($padres[$a]),
                    $porSpecies[$a]->speciesId,
                    $porSpecies[$a]->id,
                ] <=> [
                    $this->nivelOrden($padres[$b]),
                    $porSpecies[$b]->speciesId,
                    $porSpecies[$b]->id,
                ],
            );

            $hijos[$padreSpeciesId] = $lista;
        }

        return $hijos;
    }

    /**
     * @param  array<int, AristaEvolutiva>  $padres
     * @return array<int, list<int>>
     */
    private function construirAdyacencia(array $padres): array
    {
        $adyacencia = [];

        foreach ($padres as $haciaSpeciesId => $arista) {
            $adyacencia[$arista->desdeSpeciesId][] = $haciaSpeciesId;
            $adyacencia[$haciaSpeciesId][] = $arista->desdeSpeciesId;
        }

        return $adyacencia;
    }

    /**
     * @param  array<int, PokemonFamilia>  $porSpecies
     * @param  array<int, list<int>>  $adyacencia
     * @return list<list<int>>
     */
    private function componentes(array $porSpecies, array $adyacencia): array
    {
        $componentes = [];
        $asignados = [];

        foreach (array_keys($porSpecies) as $speciesId) {
            if (isset($asignados[$speciesId])) {
                continue;
            }

            $cola = [$speciesId];
            $asignados[$speciesId] = true;
            $componente = [];

            while ($cola !== []) {
                $actual = array_shift($cola);
                $componente[] = $actual;

                foreach ($adyacencia[$actual] ?? [] as $vecino) {
                    if (! isset($asignados[$vecino])) {
                        $asignados[$vecino] = true;
                        $cola[] = $vecino;
                    }
                }
            }

            usort(
                $componente,
                fn (int $a, int $b): int => [$a, $porSpecies[$a]->id] <=> [$b, $porSpecies[$b]->id],
            );

            $componentes[] = $componente;
        }

        return $componentes;
    }

    /**
     * @param  list<int>  $componente
     * @param  array<int, AristaEvolutiva>  $padres
     */
    private function raizDe(array $componente, array $padres): int
    {
        foreach ($componente as $speciesId) {
            if (! isset($padres[$speciesId])) {
                return $speciesId;
            }
        }

        return $componente[0];
    }

    /**
     * Modo compatibilidad con `ResolvedorCadenasEvolutivas`: se recorre solo el
     * componente de la primera raíz global. Si no hay ninguna raíz (todos los
     * componentes son ciclos), se usa el primer componente.
     *
     * @param  list<list<int>>  $componentes
     * @param  array<int, AristaEvolutiva>  $padres
     * @param  array<int, PokemonFamilia>  $porSpecies
     * @return list<list<int>>
     */
    private function seleccionarComponenteCompatibilidad(
        array $componentes,
        array $padres,
        array $porSpecies,
    ): array {
        $elegido = null;
        $raizElegida = null;

        foreach ($componentes as $componente) {
            $raiz = $this->raizDe($componente, $padres);

            if (isset($padres[$raiz])) {
                continue;
            }

            if ($raizElegida === null) {
                $elegido = $componente;
                $raizElegida = $raiz;

                continue;
            }

            $comparacion = [$raiz, $porSpecies[$raiz]->id] <=> [$raizElegida, $porSpecies[$raizElegida]->id];

            if ($comparacion < 0) {
                $elegido = $componente;
                $raizElegida = $raiz;
            }
        }

        if ($elegido !== null) {
            return [$elegido];
        }

        return $componentes === [] ? [] : [$componentes[0]];
    }

    /**
     * Recorre un componente en anchura desde su raíz.
     *
     * @param  array<int, PokemonFamilia>  $porSpecies
     * @param  array<int, AristaEvolutiva>  $padres
     * @param  array<int, list<int>>  $hijos
     * @return list<MiembroFamiliaEvolutiva>
     */
    private function recorrer(
        int $raiz,
        array $porSpecies,
        array $padres,
        array $hijos,
        ?int $maxEtapas,
    ): array {
        /** @var array<int, true> $visitados */
        $visitados = [];
        /** @var list<MiembroFamiliaEvolutiva> $miembros */
        $miembros = [];
        /** @var list<array{species:int, stage:int, parent:int|null, level:int|null, branch:int}> $cola */
        $cola = [[
            'species' => $raiz,
            'stage' => 1,
            'parent' => null,
            'level' => null,
            'branch' => 0,
        ]];

        while ($cola !== []) {
            $actual = array_shift($cola);
            $speciesId = $actual['species'];

            if (isset($visitados[$speciesId])) {
                continue;
            }

            $visitados[$speciesId] = true;
            $nodo = $porSpecies[$speciesId];

            $miembros[] = new MiembroFamiliaEvolutiva(
                id: $nodo->id,
                nombre: $nodo->nombre,
                speciesId: $nodo->speciesId,
                stage: $actual['stage'],
                parentId: $actual['parent'] !== null ? $porSpecies[$actual['parent']]->id : null,
                nivelMinimo: $actual['level'],
                branchIndex: $actual['branch'],
            );

            if ($maxEtapas !== null && $actual['stage'] >= $maxEtapas) {
                continue;
            }

            foreach ($hijos[$speciesId] ?? [] as $branchIndex => $hijoSpeciesId) {
                if (isset($visitados[$hijoSpeciesId])) {
                    continue;
                }

                $cola[] = [
                    'species' => $hijoSpeciesId,
                    'stage' => $actual['stage'] + 1,
                    'parent' => $speciesId,
                    'level' => $padres[$hijoSpeciesId]->nivelMinimo,
                    'branch' => $branchIndex,
                ];
            }
        }

        return $miembros;
    }

    private function nivelOrden(AristaEvolutiva $arista): int
    {
        return $arista->nivelMinimo ?? PHP_INT_MAX;
    }

    private function comparar(MiembroFamiliaEvolutiva $a, MiembroFamiliaEvolutiva $b): int
    {
        $comparacion = $a->stage <=> $b->stage;

        if ($comparacion !== 0) {
            return $comparacion;
        }

        $comparacion = ($a->parentId ?? self::SIN_PADRE) <=> ($b->parentId ?? self::SIN_PADRE);

        if ($comparacion !== 0) {
            return $comparacion;
        }

        $comparacion = $a->branchIndex <=> $b->branchIndex;

        if ($comparacion !== 0) {
            return $comparacion;
        }

        $comparacion = $a->speciesId <=> $b->speciesId;

        if ($comparacion !== 0) {
            return $comparacion;
        }

        return $a->id <=> $b->id;
    }
}
