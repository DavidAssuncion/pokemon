<?php

declare(strict_types=1);

namespace Tests\Unit\CombateEntrenadores;

use App\Models\Pokemon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Pokemon\Domain\Stats\StatsValue;
use Tests\TestCase;

class MapeadorPokemonBatallaTest extends TestCase
{
    use RefreshDatabase;

    private const POKEMON_ID = 9999;

    #[Test]
    public function test_desde_pokemon_pasa_evs_cuando_se_especifican(): void
    {
        $pokemon = $this->crearPokemonBasico();
        $evs = new StatsValue(252, 128, 64, 0, 0, 0);

        $mapeador = new MapeadorPokemonBatalla(new GeneradorMovimientosTipo());
        $datos = $mapeador->desdePokemon(
            pokemon: $pokemon,
            id: 'test_1',
            nombre: 'Test',
            posicion: Posicion::VANGUARDIA,
            evs: $evs,
        );

        $this->assertSame(252.0, $datos->evs->hp);
        $this->assertSame(128.0, $datos->evs->attack);
        $this->assertSame(64.0, $datos->evs->defense);
        $this->assertSame(0.0, $datos->evs->spAtk);
        $this->assertSame(0.0, $datos->evs->spDef);
        $this->assertSame(0.0, $datos->evs->speed);
    }

    #[Test]
    public function test_desde_pokemon_sin_evs_usa_default_cero(): void
    {
        $pokemon = $this->crearPokemonBasico();

        $mapeador = new MapeadorPokemonBatalla(new GeneradorMovimientosTipo());
        $datos = $mapeador->desdePokemon(
            pokemon: $pokemon,
            id: 'test_2',
            nombre: 'Test',
            posicion: Posicion::VANGUARDIA,
        );

        $this->assertSame(0.0, $datos->evs->hp);
        $this->assertSame(0.0, $datos->evs->attack);
        $this->assertSame(0.0, $datos->evs->defense);
        $this->assertSame(0.0, $datos->evs->spAtk);
        $this->assertSame(0.0, $datos->evs->spDef);
        $this->assertSame(0.0, $datos->evs->speed);
    }

    private function crearPokemonBasico(): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => self::POKEMON_ID,
            'name' => 'test-pokemon',
            'species_id' => self::POKEMON_ID,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $pokemon->stats()->createMany([
            ['stat' => 1, 'base_stat' => 50, 'effort' => 0],  // HP
            ['stat' => 2, 'base_stat' => 60, 'effort' => 0],  // ATTACK
            ['stat' => 3, 'base_stat' => 50, 'effort' => 0],  // DEFENSE
            ['stat' => 4, 'base_stat' => 60, 'effort' => 0],  // SPECIAL_ATTACK
            ['stat' => 5, 'base_stat' => 50, 'effort' => 0],  // SPECIAL_DEFENSE
            ['stat' => 6, 'base_stat' => 55, 'effort' => 0],  // SPEED
        ]);

        $pokemon->types()->createMany([
            ['type' => 1, 'slot' => 1], // NORMAL
        ]);

        return $pokemon;
    }
}
