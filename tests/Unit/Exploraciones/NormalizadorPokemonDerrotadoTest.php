<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use App\Models\EvolutionChain;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Src\Exploraciones\App\NormalizadorPokemonDerrotado;
use Tests\TestCase;

class NormalizadorPokemonDerrotadoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Carga los pokémon con las mismas relaciones que el handler (evolutionChain.pokemon,
     * stats, types) y los devuelve keyBy id.
     *
     * @return EloquentCollection<int, Pokemon>
     */
    private function cargarConRelaciones(): EloquentCollection
    {
        return Pokemon::query()
            ->with('evolutionChain.pokemon', 'stats', 'types')
            ->get()
            ->keyBy('id');
    }

    /**
     * Expande una entrada por derrota de la bitácora (misma expansión que el handler).
     *
     * @param  EloquentCollection<int, Pokemon>  $pokemons
     * @param  list<int>  $idsDerrotados
     * @return Collection<int, Pokemon>
     */
    private function expandirDerrotados(EloquentCollection $pokemons, array $idsDerrotados): Collection
    {
        return collect($idsDerrotados)
            ->map(fn (int $id): ?Pokemon => $pokemons->get($id))
            ->filter()
            ->values();
    }

    public function test_calcula_la_fase_segun_la_posicion_en_la_cadena(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 2}']);

        Pokemon::create(['id' => 1, 'name' => 'bulbasaur', 'species_id' => 1, 'capture_rate' => 255, 'base_experience' => 64, 'height' => 7, 'weight' => 69, 'hatch' => 10, 'evolution_chain_id' => $chain->id]);
        Pokemon::create(['id' => 2, 'name' => 'ivysaur', 'species_id' => 2, 'capture_rate' => 45, 'base_experience' => 142, 'height' => 10, 'weight' => 130, 'hatch' => 10, 'evolution_chain_id' => $chain->id]);

        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($this->cargarConRelaciones(), [1, 2]),
        );

        $this->assertSame(1, $derrotados->get(0)->fase); // solo bulbasaur con species_id <= 1
        $this->assertSame(2, $derrotados->get(1)->fase); // bulbasaur + ivysaur
    }

    public function test_expande_una_entrada_por_derrota_de_la_bitacora(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 1}']);
        Pokemon::create(['id' => 1, 'name' => 'bulbasaur', 'species_id' => 1, 'capture_rate' => 255, 'base_experience' => 64, 'height' => 7, 'weight' => 69, 'hatch' => 10, 'evolution_chain_id' => $chain->id]);
        Pokemon::create(['id' => 2, 'name' => 'charmander', 'species_id' => 4, 'capture_rate' => 255, 'base_experience' => 62, 'height' => 6, 'weight' => 85, 'hatch' => 10, 'evolution_chain_id' => $chain->id]);

        // bulbasaur derrotado 2 veces, charmander 1 → 3 entradas normalizadas
        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($this->cargarConRelaciones(), [1, 1, 2]),
        );

        $this->assertCount(3, $derrotados);
        $this->assertSame(1, $derrotados->get(0)->id);
        $this->assertSame(1, $derrotados->get(1)->id);
        $this->assertSame(2, $derrotados->get(2)->id);
    }

    public function test_normaliza_datos_basicos_tipos_y_stats(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 1}']);

        Pokemon::create(['id' => 1, 'name' => 'bulbasaur', 'species_id' => 1, 'capture_rate' => 255, 'base_experience' => 64, 'height' => 7, 'weight' => 69, 'hatch' => 10, 'evolution_chain_id' => $chain->id]);

        PokemonType::create(['pokemon_id' => 1, 'type' => 13, 'slot' => 1]); // Eléctrico
        PokemonType::create(['pokemon_id' => 1, 'type' => 10, 'slot' => 2]); // Fuego
        PokemonStat::create(['pokemon_id' => 1, 'stat' => 1, 'base_stat' => 45, 'effort' => 2]);
        PokemonStat::create(['pokemon_id' => 1, 'stat' => 2, 'base_stat' => 49, 'effort' => 0]);

        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($this->cargarConRelaciones(), [1]),
        );
        $pokemon = $derrotados->get(0);

        $this->assertSame(1, $pokemon->id);
        $this->assertSame(64, $pokemon->baseExperience);
        $this->assertSame($chain->id, $pokemon->evolutionChainId);
        $this->assertSame(1, $pokemon->speciesId);
        $this->assertSame(255, $pokemon->captureRate);
        $this->assertSame(['Eléctrico', 'Fuego'], $pokemon->tipos);
        $this->assertSame(
            [['stat' => 1, 'effort' => 2], ['stat' => 2, 'effort' => 0]],
            $pokemon->stats->all(),
        );
    }

    public function test_pokemon_sin_cadena_normaliza_con_fase_uno_y_cadena_nula(): void
    {
        Pokemon::create(['id' => 1, 'name' => 'mew', 'species_id' => 151, 'capture_rate' => 45, 'base_experience' => 300, 'height' => 4, 'weight' => 40, 'hatch' => 10, 'evolution_chain_id' => null]);

        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($this->cargarConRelaciones(), [1]),
        );

        $this->assertNull($derrotados->get(0)->evolutionChainId);
        // fase 1 por defecto (el calculador filtra por cadena nula, así que no produce caramelos)
        $this->assertSame(1, $derrotados->get(0)->fase);
    }

    public function test_pokemon_sin_tipos_ni_stats_se_normaliza_con_listas_vacias(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 1}']);
        Pokemon::create(['id' => 1, 'name' => 'bulbasaur', 'species_id' => 1, 'capture_rate' => 255, 'base_experience' => 64, 'height' => 7, 'weight' => 69, 'hatch' => 10, 'evolution_chain_id' => $chain->id]);

        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($this->cargarConRelaciones(), [1]),
        );

        $this->assertSame([], $derrotados->get(0)->tipos);
        $this->assertTrue($derrotados->get(0)->stats->isEmpty());
    }

    public function test_ids_desconocidos_se_descartan_sin_romper_el_mapa(): void
    {
        Pokemon::create(['id' => 1, 'name' => 'bulbasaur', 'species_id' => 1, 'capture_rate' => 255, 'base_experience' => 64, 'height' => 7, 'weight' => 69, 'hatch' => 10, 'evolution_chain_id' => null]);

        $derrotados = NormalizadorPokemonDerrotado::normalizar(
            $this->expandirDerrotados($this->cargarConRelaciones(), [1, 999]),
        );

        $this->assertCount(1, $derrotados);
        $this->assertSame(1, $derrotados->get(0)->id);
    }
}
