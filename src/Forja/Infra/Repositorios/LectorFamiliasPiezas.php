<?php

declare(strict_types=1);

namespace Src\Forja\Infra\Repositorios;

use App\Enums\StatEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Src\Forja\Domain\Piezas\CalculadoraStatsPieza;
use Src\Forja\Domain\Piezas\Collections\DefinicionesPiezaCollection;
use Src\Forja\Domain\Piezas\Collections\NidosConstruidosCollection;
use Src\Forja\Domain\Piezas\DataTransferObjects\DefinicionPieza;
use Src\Forja\Domain\Piezas\Enums\TipoPieza;
use Src\Forja\Domain\Piezas\ReglasPiezas;
use Src\Forja\Domain\Piezas\ResolvedorEtapasPiezas;
use Src\Forja\Domain\Ports\DefinicionesPieza;
use Src\Forja\Domain\Ports\PiezasPorEspecie;
use Src\Pokemon\Domain\Evolution\AristaEvolutiva;
use Src\Pokemon\Domain\Evolution\PokemonFamilia;
use Src\Pokemon\Domain\Stats\StatsValue;
use Src\Shared\Collections\IntCollection;

/**
 * Read model de las familias evolutivas para la Forja.
 *
 * Carga especies, stats, habilidad slot 1 y aristas de evolución en un número
 * constante de consultas (`whereIn`) y produce las definiciones de pieza ya
 * resueltas: etapa, profundidad, gate y bonos. Sirve tanto para las familias
 * con nido construido como para resolver piezas ya equipadas por su clave.
 */
final readonly class LectorFamiliasPiezas implements DefinicionesPieza, PiezasPorEspecie
{
    /** @var list<string> */
    private const COLUMNAS_POKEMON = ['id', 'name', 'species_id', 'evolution_chain_id'];

    public function __construct(
        private ResolvedorEtapasPiezas $resolvedor,
        private CalculadoraStatsPieza $calculadora,
    ) {
    }

    public function definiciones(NidosConstruidosCollection $nidos): DefinicionesPiezaCollection
    {
        $cadenas = $this->cadenasDe($nidos);

        if ($cadenas === []) {
            return new DefinicionesPiezaCollection();
        }

        $filas = DB::table('pokemon')
            ->whereIn('evolution_chain_id', $cadenas)
            ->orderBy('species_id')
            ->orderBy('id')
            ->get(self::COLUMNAS_POKEMON);

        return $this->definicionesDeFilas($filas);
    }

    /**
     * Definiciones de las especies pedidas sin depender de los nidos del jugador.
     *
     * Se cargan las familias completas de esas especies (necesarias para la
     * etapa) y solo se emiten las especies solicitadas. Las especies que no
     * existen en `pokemon` se omiten.
     */
    public function definicionesDeEspecies(IntCollection $speciesIds): DefinicionesPiezaCollection
    {
        $especies = $speciesIds->all();

        if ($especies === []) {
            return new DefinicionesPiezaCollection();
        }

        $filas = DB::table('pokemon')
            ->whereIn('evolution_chain_id', function ($query) use ($especies): void {
                $query->select('evolution_chain_id')
                    ->from('pokemon')
                    ->whereNotNull('evolution_chain_id')
                    ->whereIn('species_id', $especies);
            })
            ->orderBy('species_id')
            ->orderBy('id')
            ->get(self::COLUMNAS_POKEMON);

        return $this->definicionesDeFilas($filas, array_fill_keys($especies, true));
    }

    /**
     * @param  Collection<int, \stdClass>  $filas
     * @param  array<int, true>|null  $soloEspecies  species_id a emitir (null = todas)
     */
    private function definicionesDeFilas(Collection $filas, ?array $soloEspecies = null): DefinicionesPiezaCollection
    {
        $definiciones = new DefinicionesPiezaCollection();

        if ($filas->isEmpty()) {
            return $definiciones;
        }

        /** @var array<int, array<int, \stdClass>> $porCadena */
        $porCadena = [];
        $pokemonIds = [];
        $speciesIds = [];

        foreach ($filas as $fila) {
            $porCadena[(int) $fila->evolution_chain_id][(int) $fila->species_id] = $fila;
            $pokemonIds[] = (int) $fila->id;
            $speciesIds[] = (int) $fila->species_id;
        }

        $stats = $this->statsPorPokemon($pokemonIds);
        $habilidades = $this->habilidadesSlot1($pokemonIds);
        $aristas = $this->aristas($speciesIds);

        foreach ($porCadena as $cadena => $grupo) {
            $this->definicionesDeFamilia($definiciones, $cadena, $grupo, $aristas, $habilidades, $stats, $soloEspecies);
        }

        return $definiciones;
    }

    /**
     * @param  array<int, \stdClass>  $grupo  especies keyed por species_id
     * @param  list<AristaEvolutiva>  $aristas
     * @param  array<int, array{clave: string|null, nombre: string, descripcion: string}>  $habilidades
     * @param  array<int, StatsValue>  $stats
     * @param  array<int, true>|null  $soloEspecies
     */
    private function definicionesDeFamilia(
        DefinicionesPiezaCollection $definiciones,
        int $cadena,
        array $grupo,
        array $aristas,
        array $habilidades,
        array $stats,
        ?array $soloEspecies,
    ): void {
        $especies = array_keys($grupo);
        $conjunto = array_fill_keys($especies, true);

        $aristasFamilia = array_values(array_filter(
            $aristas,
            static fn (AristaEvolutiva $arista): bool => isset(
                $conjunto[$arista->desdeSpeciesId],
                $conjunto[$arista->haciaSpeciesId],
            ),
        ));

        $familia = $this->resolvedor->resolver(
            array_values(array_map(
                static fn (\stdClass $pokemon): PokemonFamilia => new PokemonFamilia(
                    id: (int) $pokemon->id,
                    nombre: (string) $pokemon->name,
                    speciesId: (int) $pokemon->species_id,
                ),
                $grupo,
            )),
            $aristasFamilia,
        );

        $speciesPorPokemon = [];

        foreach ($grupo as $pokemon) {
            $speciesPorPokemon[(int) $pokemon->id] = (int) $pokemon->species_id;
        }

        $miembros = $familia->miembros();
        $padrePorEspecie = [];
        $profundidad = 1;

        foreach ($miembros as $miembro) {
            $profundidad = max($profundidad, $miembro->stage);

            if ($miembro->parentId !== null) {
                $padrePorEspecie[$miembro->speciesId] = $speciesPorPokemon[$miembro->parentId] ?? null;
            }
        }

        $primeraEspecie = $especies[0] ?? null;

        if ($primeraEspecie === null) {
            return;
        }

        $familiaNombre = (string) $grupo[$primeraEspecie]->name;

        foreach ($miembros as $miembro) {
            if ($soloEspecies !== null && ! isset($soloEspecies[$miembro->speciesId])) {
                continue;
            }

            $pokemon = $grupo[$miembro->speciesId] ?? null;

            if ($pokemon === null) {
                continue;
            }

            $base = $stats[(int) $pokemon->id] ?? null;

            if ($base === null) {
                continue;
            }

            $habilidad = $habilidades[(int) $pokemon->id] ?? null;
            $padreEspecie = $padrePorEspecie[$miembro->speciesId] ?? null;

            foreach (TipoPieza::cases() as $tipo) {
                $habilidadPasiva = $tipo === TipoPieza::AMULETO && $miembro->stage === 3
                    ? $habilidad
                    : null;

                $definiciones->add(new DefinicionPieza(
                    tipo: $tipo,
                    speciesId: (int) $pokemon->species_id,
                    speciesNombre: (string) $pokemon->name,
                    evolutionChainId: $cadena,
                    familiaNombre: $familiaNombre,
                    familiaPrimerIntegranteId: $primeraEspecie,
                    etapa: $miembro->stage,
                    profundidadFamilia: $profundidad,
                    nivelNidoRequerido: ReglasPiezas::nivelNidoPara($profundidad, $miembro->stage),
                    parentSpeciesId: $padreEspecie,
                    bonos: $this->calculadora->calcular($base, $tipo, $miembro->stage),
                    icono: '/images/iconos_webp/'.$pokemon->id.'.webp',
                    habilidadClave: $habilidadPasiva['clave'] ?? null,
                    habilidadNombre: $habilidadPasiva['nombre'] ?? null,
                    habilidadDescripcion: $habilidadPasiva['descripcion'] ?? null,
                ));
            }
        }
    }

    /**
     * @return list<int>
     */
    private function cadenasDe(NidosConstruidosCollection $nidos): array
    {
        $cadenas = [];

        foreach ($nidos as $nido) {
            if (! in_array($nido->evolutionChainId, $cadenas, true)) {
                $cadenas[] = $nido->evolutionChainId;
            }
        }

        return $cadenas;
    }

    /**
     * @param  list<int>  $pokemonIds
     * @return array<int, StatsValue>
     */
    private function statsPorPokemon(array $pokemonIds): array
    {
        if ($pokemonIds === []) {
            return [];
        }

        $filas = DB::table('pokemon_stats')
            ->whereIn('pokemon_id', $pokemonIds)
            ->get(['pokemon_id', 'stat', 'base_stat']);

        /** @var array<int, array<string, float>> $valores */
        $valores = [];

        foreach ($filas as $fila) {
            $stat = StatEnum::tryFrom((int) $fila->stat);

            if ($stat === null) {
                continue;
            }

            $valores[(int) $fila->pokemon_id][$stat->key()] = (float) $fila->base_stat;
        }

        $stats = [];

        foreach ($valores as $pokemonId => $porStat) {
            $stats[$pokemonId] = new StatsValue(
                hp: $porStat['hp'] ?? 0.0,
                attack: $porStat['atk'] ?? 0.0,
                defense: $porStat['def'] ?? 0.0,
                spAtk: $porStat['spAtk'] ?? 0.0,
                spDef: $porStat['spDef'] ?? 0.0,
                speed: $porStat['speed'] ?? 0.0,
            );
        }

        return $stats;
    }

    /**
     * @param  list<int>  $speciesIds
     * @return list<AristaEvolutiva>
     */
    private function aristas(array $speciesIds): array
    {
        if ($speciesIds === []) {
            return [];
        }

        $filas = DB::table('pokemon_evolution')
            ->where(function ($query) use ($speciesIds): void {
                $query->whereIn('evolves_from_species_id', $speciesIds)
                    ->orWhereIn('evolved_species_id', $speciesIds);
            })
            ->get(['evolves_from_species_id', 'evolved_species_id', 'minimum_level']);

        $aristas = [];

        foreach ($filas as $fila) {
            $desde = $fila->evolves_from_species_id;

            if ($desde === null) {
                continue;
            }

            $aristas[] = new AristaEvolutiva(
                desdeSpeciesId: (int) $desde,
                haciaSpeciesId: (int) $fila->evolved_species_id,
                nivelMinimo: (int) $fila->minimum_level,
            );
        }

        return $aristas;
    }

    /**
     * @param  list<int>  $pokemonIds
     * @return array<int, array{clave: string|null, nombre: string, descripcion: string}>
     */
    private function habilidadesSlot1(array $pokemonIds): array
    {
        if ($pokemonIds === []) {
            return [];
        }

        $filas = DB::table('ability_pokemon')
            ->join('abilities', 'abilities.id', '=', 'ability_pokemon.ability_id')
            ->whereIn('ability_pokemon.pokemon_id', $pokemonIds)
            ->where('ability_pokemon.slot', 1)
            ->select([
                'ability_pokemon.pokemon_id',
                'abilities.name',
                'abilities.effect',
                'abilities.data',
            ])
            ->get();

        $habilidades = [];

        foreach ($filas as $fila) {
            $datos = json_decode((string) $fila->data, true);
            $claves = is_array($datos) ? ($datos['effect_keys'] ?? []) : [];
            $clave = is_array($claves) ? ($claves[0] ?? null) : null;

            $habilidades[(int) $fila->pokemon_id] = [
                'clave' => is_string($clave) ? $clave : null,
                'nombre' => (string) $fila->name,
                'descripcion' => (string) $fila->effect,
            ];
        }

        return $habilidades;
    }
}
