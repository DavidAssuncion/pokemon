<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Tests\TestCase;

class ExploracionesTransformadorTest extends TestCase
{
    use RefreshDatabase;

    private const CHAIN_ID = 51;

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

    /**
     * Mapa de miembros por cadena (misma expansión que el handler).
     *
     * @return array<int, BaseCollection<int, Pokemon>>
     */
    private function miembrosPorCadena(): array
    {
        return Pokemon::query()
            ->whereNotNull('evolution_chain_id')
            ->get(['id', 'name', 'species_id', 'evolution_chain_id'])
            ->groupBy('evolution_chain_id')
            ->all();
    }

    public function test_caramelos_familia_usa_el_miembro_de_menor_species_id_como_pokemon_id(): void
    {
        // Cadena Happiny(440) -> Chansey(113) -> Blissey(242): el bebé (440) es la base
        // evolutiva, pero el menor species_id es Chansey (113) → el caramelo debe apuntar a 113.
        $this->crearPokemon(440, 'happiny', 440, self::CHAIN_ID);
        $this->crearPokemon(113, 'chansey', 113, self::CHAIN_ID);
        $this->crearPokemon(242, 'blissey', 242, self::CHAIN_ID);

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

        // Solo se derrotó Chansey, pero el mapa de miembros carga TODA la familia.
        $derrotados = Pokemon::query()
            ->whereKey(113)
            ->get()
            ->keyBy('id');

        $recompensas = new ResultadoRecompensas(
            capturas: [],
            caramelosFamilia: [new RecompensaFamilia(self::CHAIN_ID, 5)],
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 0,
        );

        $resultado = (new TransformadorResultadoExploracion())
            ->desde($recompensas, $derrotados, $this->miembrosPorCadena());

        $this->assertSame([
            [
                'evolution_chain_id' => self::CHAIN_ID,
                'nombre' => 'chansey',
                'pokemon_id' => 113,
                'cantidad' => 5,
            ],
        ], $resultado['caramelos_familia']);
    }

    public function test_caramelos_familia_sin_pokemon_de_la_cadena_devuelve_pokemon_id_null(): void
    {
        $recompensas = new ResultadoRecompensas(
            capturas: [],
            caramelosFamilia: [new RecompensaFamilia(self::CHAIN_ID, 2)],
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 0,
        );

        $resultado = (new TransformadorResultadoExploracion())
            ->desde($recompensas, new Collection(), []);

        $this->assertSame([
            [
                'evolution_chain_id' => self::CHAIN_ID,
                'nombre' => null,
                'pokemon_id' => null,
                'cantidad' => 2,
            ],
        ], $resultado['caramelos_familia']);
    }

    public function test_caramelos_familia_sin_mapa_fallback_a_los_derrotados_de_la_cadena(): void
    {
        // Sin mapa de miembros, la base se resuelve entre los derrotados de esa cadena
        // (mismo criterio: menor species_id).
        $this->crearPokemon(2, 'ivysaur', 2, self::CHAIN_ID);
        $this->crearPokemon(1, 'bulbasaur', 1, self::CHAIN_ID);

        $derrotados = Pokemon::query()
            ->whereIn('id', [1, 2])
            ->get()
            ->keyBy('id');

        $recompensas = new ResultadoRecompensas(
            capturas: [],
            caramelosFamilia: [new RecompensaFamilia(self::CHAIN_ID, 2)],
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 0,
        );

        $resultado = (new TransformadorResultadoExploracion())
            ->desde($recompensas, $derrotados);

        $this->assertSame([
            [
                'evolution_chain_id' => self::CHAIN_ID,
                'nombre' => 'bulbasaur',
                'pokemon_id' => 1,
                'cantidad' => 2,
            ],
        ], $resultado['caramelos_familia']);
    }

    public function test_caramelos_familia_fallback_sin_mapa_elige_el_derrotado_de_menor_species_id(): void
    {
        // Sin mapa de miembros, la base se resuelve entre los derrotados de la cadena
        // con el MISMO criterio determinista: menor species_id (no el primero en llegar).
        $charmander = $this->crearPokemon(2, 'charmander', 4, self::CHAIN_ID);
        $bulbasaur = $this->crearPokemon(1, 'bulbasaur', 1, self::CHAIN_ID);

        // Colección en orden de inserción: el primer derrotado (charmander, species 4)
        // NO es el de menor species_id (bulbasaur, species 1).
        $derrotados = new Collection([$charmander, $bulbasaur]);

        $recompensas = new ResultadoRecompensas(
            capturas: [],
            caramelosFamilia: [new RecompensaFamilia(self::CHAIN_ID, 2)],
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 0,
        );

        $resultado = (new TransformadorResultadoExploracion())
            ->desde($recompensas, $derrotados);

        $this->assertSame([
            [
                'evolution_chain_id' => self::CHAIN_ID,
                'nombre' => 'bulbasaur',
                'pokemon_id' => 1,
                'cantidad' => 2,
            ],
        ], $resultado['caramelos_familia']);
    }

    public function test_caramelos_familia_con_dos_cadenas_devuelve_entradas_independientes_ordenadas_por_cadena(): void
    {
        // Cadena 51: bulbasaur (species 1) + charmander (species 4) → base bulbasaur (id 1).
        $this->crearPokemon(1, 'bulbasaur', 1, self::CHAIN_ID);
        $this->crearPokemon(2, 'charmander', 4, self::CHAIN_ID);
        // Cadena 52: pikachu (species 25) + raichu (species 26) → base pikachu (id 3).
        $this->crearPokemon(3, 'pikachu', 25, 52);
        $this->crearPokemon(4, 'raichu', 26, 52);

        $derrotados = Pokemon::query()
            ->whereIn('id', [1, 3])
            ->get()
            ->keyBy('id');

        // Insertadas desordenadas a propósito: la salida debe quedar ordenada por
        // evolution_chain_id y cada entrada con la base de SU cadena (sin cruces).
        $recompensas = new ResultadoRecompensas(
            capturas: [],
            caramelosFamilia: [new RecompensaFamilia(52, 3), new RecompensaFamilia(self::CHAIN_ID, 5)],
            caramelosEv: [],
            caramelosTipo: [],
            expTotal: 0,
        );

        $resultado = (new TransformadorResultadoExploracion())
            ->desde($recompensas, $derrotados, $this->miembrosPorCadena());

        $this->assertSame([
            [
                'evolution_chain_id' => self::CHAIN_ID,
                'nombre' => 'bulbasaur',
                'pokemon_id' => 1,
                'cantidad' => 5,
            ],
            [
                'evolution_chain_id' => 52,
                'nombre' => 'pikachu',
                'pokemon_id' => 3,
                'cantidad' => 3,
            ],
        ], $resultado['caramelos_familia']);
    }
}
