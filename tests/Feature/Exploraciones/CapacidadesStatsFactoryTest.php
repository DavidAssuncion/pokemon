<?php

declare(strict_types=1);

namespace Tests\Feature\Exploraciones;

use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Src\Exploraciones\Domain\CapacidadesStats;
use Tests\TestCase;

class CapacidadesStatsFactoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_desde_reclutado_calcula_niveles_y_stats(): void
    {
        // exp 125.000 → nivel 50 (curva 10×nivel³ → 10*50³ = 1.250.000)
        // usuario con experiencia 10*20³ = 80.000 → nivel 20
        $user = User::factory()->create(['experiencia' => 80_000]);
        $reclutado = $this->crearReclutado($user, expTotal: 1_250_000);

        $stats = CapacidadesStats::desdeReclutado($reclutado, $user);

        $this->assertSame(50, $stats->nivelPokemon);
        $this->assertSame(20, $stats->nivelEntrenador);
        $this->assertSame(100, $stats->hp);
        $this->assertSame(80, $stats->atk);
        $this->assertSame(70, $stats->def);
        $this->assertSame(90, $stats->spAtk);
        $this->assertSame(60, $stats->spDef);
        $this->assertSame(50, $stats->speed);
    }

    #[Test]
    public function test_desde_reclutado_stats_faltantes_van_a_cero(): void
    {
        $user = User::factory()->create(['experiencia' => 0]); // nivel 1
        $reclutado = $this->crearReclutado($user, expTotal: 0, soloHp: true);

        $stats = CapacidadesStats::desdeReclutado($reclutado, $user);

        $this->assertSame(1, $stats->nivelPokemon);
        $this->assertSame(1, $stats->nivelEntrenador);
        $this->assertSame(45, $stats->hp);
        $this->assertSame(0, $stats->atk);
        $this->assertSame(0, $stats->def);
        $this->assertSame(0, $stats->spAtk);
        $this->assertSame(0, $stats->spDef);
        $this->assertSame(0, $stats->speed);
    }

    #[Test]
    public function test_todas_con_factory_coincide_con_formulas_manuales(): void
    {
        $user = User::factory()->create(['experiencia' => 10 * 3 ** 3]); // nivel 3
        $reclutado = $this->crearReclutado($user, expTotal: 10 * 7 ** 3); // nivel 7

        $stats = CapacidadesStats::desdeReclutado($reclutado, $user);

        // Recalculamos con los valores reales: hp=100, atk=80, def=70, spAtk=90, spDef=60, speed=50
        $manual = (new CapacidadesStats(100, 80, 70, 90, 60, 50, 7, 3))->todas();
        $this->assertSame($manual, $stats->todas());
    }

    private function crearReclutado(User $user, int $expTotal, bool $soloHp = false): Reclutado
    {
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 255,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);

        if ($soloHp) {
            PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => 1, 'base_stat' => 45, 'effort' => 2]);
        } else {
            $stats = [
                1 => 100, // HP
                2 => 80,  // ATTACK
                3 => 70,  // DEFENSE
                4 => 90,  // SPECIAL_ATTACK
                5 => 60,  // SPECIAL_DEFENSE
                6 => 50,  // SPEED
            ];
            foreach ($stats as $stat => $base) {
                PokemonStat::create(['pokemon_id' => $pokemon->id, 'stat' => $stat, 'base_stat' => $base, 'effort' => 0]);
            }
        }

        return Reclutado::create([
            'user_id' => $user->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Bulbi',
            'exp' => ['total' => $expTotal],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }
}
