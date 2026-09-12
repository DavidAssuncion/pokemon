<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\User;
use App\Support\ReclutadoSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclutadoSerializerCPTest extends TestCase
{
    use RefreshDatabase;

    public function test_serializer_expone_cp_con_ev_cero(): void
    {
        $user = User::factory()->create();
        $pokemon = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $stats = [
            [StatEnum::HP, 60],
            [StatEnum::ATTACK, 65],
            [StatEnum::DEFENSE, 100],
            [StatEnum::SPECIAL_ATTACK, 100],
            [StatEnum::SPECIAL_DEFENSE, 100],
            [StatEnum::SPEED, 45],
        ];
        foreach ($stats as [$stat, $base]) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat,
                'base_stat' => $base,
                'effort' => 0,
            ]);
        }
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => TipoEnum::GRASS, 'slot' => 1]);

        $reclutado = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'Bulbi',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 100], // nivel 2
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
            'behavior' => 'COMBATIENTE',
        ]);

        $serializado = ReclutadoSerializer::serializar($reclutado);

        $this->assertArrayHasKey('cp', $serializado);
        $this->assertIsInt($serializado['cp']);

        // BattleStats a nivel 2 sin EVs:
        //   hp = floor((2*60)*2/100 + 2 + 10) = 14
        //   atk = floor((2*65)*2/100 + 5) = 7
        //   def/spAtk/spDef = 9; speed = floor((2*45)*2/100 + 5) = 6
        //   suma = 14+7+9+9+9+6 = 54
        //   CP = floor(54 * 2 * 6 / 100 + 0) = floor(6.48) = 6
        $this->assertSame(6, $serializado['cp']);
    }

    public function test_serializer_cp_sin_stats_devuelve_cero(): void
    {
        $user = User::factory()->create();
        $pokemon = Pokemon::create([
            'id' => 2,
            'name' => 'sin-stats',
            'species_id' => 2,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $reclutado = Reclutado::create([
            'user_id' => $user->id,
            'nombre' => 'Sin stats',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 1000],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
            'behavior' => 'COMBATIENTE',
        ]);

        $serializado = ReclutadoSerializer::serializar($reclutado);

        $this->assertArrayHasKey('cp', $serializado);
        $this->assertSame(0, $serializado['cp']);
    }
}
