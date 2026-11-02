<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use App\Models\Pokemon;
use App\Models\PokemonEvolution;
use App\Support\CadenasEvolutivas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CadenasEvolutivasEtapasTest extends TestCase
{
    use RefreshDatabase;

    private function pokemon(int $id, string $name, int $speciesId, int $chainId): Pokemon
    {
        return Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => $chainId,
        ]);
    }

    private function evolucion(int $desdeSpeciesId, int $hastaPokemonId, int $nivel = 16): void
    {
        PokemonEvolution::create([
            'evolved_species_id' => $hastaPokemonId,
            'evolves_from_species_id' => $desdeSpeciesId,
            'minimum_level' => $nivel,
        ]);
    }

    #[Test]
    public function test_pichu_pikachu_raichu_usa_las_aristas_reales(): void
    {
        $this->pokemon(172, 'pichu', 172, 10);
        $this->pokemon(25, 'pikachu', 25, 10);
        $this->pokemon(26, 'raichu', 26, 10);
        $this->evolucion(172, 25, 15);
        $this->evolucion(25, 26, 25);

        $this->assertSame(
            [10 => [172 => 1, 25 => 2, 26 => 3]],
            CadenasEvolutivas::etapasDe([10]),
        );
    }

    #[Test]
    public function test_familia_normal_asigna_etapas_1_2_3(): void
    {
        $this->pokemon(1, 'bulbasaur', 1, 51);
        $this->pokemon(2, 'ivysaur', 2, 51);
        $this->pokemon(3, 'venusaur', 3, 51);
        $this->evolucion(1, 2);
        $this->evolucion(2, 3);

        $this->assertSame(
            [51 => [1 => 1, 2 => 2, 3 => 3]],
            CadenasEvolutivas::etapasDe([51]),
        );
    }

    #[Test]
    public function test_cadena_sin_aristas_se_omite(): void
    {
        $this->pokemon(4, 'charmander', 4, 52);
        $this->pokemon(5, 'charmeleon', 5, 52);

        $this->assertArrayNotHasKey(52, CadenasEvolutivas::etapasDe([52]));
    }
}
