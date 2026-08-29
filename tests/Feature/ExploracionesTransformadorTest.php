<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EvolutionChain;
use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Tests\TestCase;

class ExploracionesTransformadorTest extends TestCase
{
    use RefreshDatabase;

    private function crearPokemon(int $id, string $name, int $speciesId, int $chainId): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => 10,
            'evolution_chain_id' => $chainId,
        ]);
    }

    public function test_caramelos_familia_usa_el_miembro_de_menor_species_id_como_pokemon_id(): void
    {
        // Cadena Happiny(440) -> Chansey(113) -> Blissey(242): el bebé (440) es la base
        // evolutiva, pero el menor species_id es Chansey (113) → el caramelo debe apuntar a 113.
        $chain = EvolutionChain::create(['data' => '{"stages": 3}']);
        $this->crearPokemon(440, 'happiny', 440, $chain->id);
        $this->crearPokemon(113, 'chansey', 113, $chain->id);
        $this->crearPokemon(242, 'blissey', 242, $chain->id);

        PokemonEvolution::create([
            'evolved_species_id' => 440,
            'evolves_from_species_id' => null,
            'minimum_level' => 1,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 113,
            'evolves_from_species_id' => 440,
            'minimum_level' => 1,
        ]);
        PokemonEvolution::create([
            'evolved_species_id' => 242,
            'evolves_from_species_id' => 113,
            'minimum_level' => 1,
        ]);

        // Solo se derrotó Chansey, pero la relación evolutionChain.pokemon carga toda la familia.
        $derrotados = Pokemon::query()
            ->with('evolutionChain.pokemon')
            ->whereKey(113)
            ->get()
            ->keyBy('id');

        $recompensas = new ResultadoRecompensas(
            capturas: [],
            caramelosFamilia: [new RecompensaFamilia($chain->id, 5)],
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 0,
        );

        $resultado = (new TransformadorResultadoExploracion())->desde($recompensas, $derrotados);

        $this->assertSame([
            [
                'evolution_chain_id' => $chain->id,
                'nombre' => 'chansey',
                'pokemon_id' => 113,
                'cantidad' => 5,
            ],
        ], $resultado['caramelos_familia']);
    }

    public function test_caramelos_familia_sin_pokemon_de_la_cadena_devuelve_pokemon_id_null(): void
    {
        $chain = EvolutionChain::create(['data' => '{"stages": 1}']);

        $recompensas = new ResultadoRecompensas(
            capturas: [],
            caramelosFamilia: [new RecompensaFamilia($chain->id, 2)],
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 0,
        );

        $resultado = (new TransformadorResultadoExploracion())->desde($recompensas, new Collection());

        $this->assertSame([
            [
                'evolution_chain_id' => $chain->id,
                'nombre' => null,
                'pokemon_id' => null,
                'cantidad' => 2,
            ],
        ], $resultado['caramelos_familia']);
    }
}
