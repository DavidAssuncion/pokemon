<?php

declare(strict_types=1);

namespace Tests\Feature\Mazmorras;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mazmorra por pisos en un hábitat: consulta de pisos (disponible/ganado/
 * cooldown) e inicio de combate 5v1 contra un jefe con stats ×10.
 */
class MazmorraApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]); // nivel 20
        $this->actingAs($this->user);
        $this->team = $this->crearEquipoJugador($this->user);
    }

    #[Test]
    public function test_consulta_pisos_sin_configuracion_devuelve_vacios(): void
    {
        $habitat = $this->crearHabitat(null);

        $response = $this->getJson("/api/habitats/{$habitat->id}/mazmorra");

        $response->assertOk();
        $this->assertSame([], $response->json('pisos'));
    }

    #[Test]
    public function test_primer_piso_disponible(): void
    {
        $habitat = $this->crearHabitat($this->configMazmorra(3));

        $response = $this->getJson("/api/habitats/{$habitat->id}/mazmorra");

        $response->assertOk();
        $pisos = $response->json('pisos');
        // Sin progreso solo es alcanzable el piso 1 (los futuros no se muestran).
        $this->assertCount(1, $pisos);
        $this->assertSame(1, $pisos[0]['piso']);
        $this->assertSame('disponible', $pisos[0]['estado']);
        $this->assertNull($pisos[0]['cooldown_hasta']);
    }

    #[Test]
    public function test_pisos_ganados_se_marcan_y_el_siguiente_disponible(): void
    {
        $habitat = $this->crearHabitat($this->configMazmorra(3));
        \App\Models\DungeonProgress::create([
            'user_id' => $this->user->id,
            'habitat_id' => $habitat->id,
            'current_floor' => 2,
        ]);

        $response = $this->getJson("/api/habitats/{$habitat->id}/mazmorra");

        $response->assertOk();
        $pisos = $response->json('pisos');
        $this->assertCount(2, $pisos);
        $this->assertSame(1, $pisos[0]['piso']);
        $this->assertSame('ganado', $pisos[0]['estado']);
        $this->assertSame(2, $pisos[1]['piso']);
        $this->assertSame('disponible', $pisos[1]['estado']);
    }

    #[Test]
    public function test_combate_primer_piso_crea_batalla_mazmorra(): void
    {
        $habitat = $this->crearHabitat($this->configMazmorra(2));
        $pokemonJefe = $this->crearPokemonJefe(25);

        $response = $this->postJson("/api/habitats/{$habitat->id}/mazmorra/1/combatir", [
            'team_id' => $this->team->id,
            'formacion' => [],
        ]);

        $response->assertOk();
        $battleId = $response->json('battle_id');
        $this->assertStringStartsWith('battle_mazmorra_', $battleId);

        // Meta de sesión: tipo mazmorra, piso y jefe ×10.
        $meta = session($battleId.'_meta');
        $this->assertSame('mazmorra', $meta['tipo']);
        $this->assertSame($habitat->id, $meta['habitat_id']);
        $this->assertSame(1, $meta['floor']);
        $this->assertSame($pokemonJefe->id, $meta['boss_species_id']);

        // El rival es 1 solo combatiente (5v1) y sus stats base están ×10.
        $payload = session($battleId);
        $battle = unserialize(explode('|', $payload, 2)[1]);
        $rivales = $battle->team2->combatants();
        $this->assertCount(1, $rivales);
        $this->assertCount(3, $battle->team1->combatants());

        // Jefe al nivel 20 del piso=1 de la config (nivel 20 jugador).
        $esperado = new \Src\Pokemon\Domain\Stats\BattleStats(
            stats: new \Src\Pokemon\Domain\Stats\StatsValue(490, 390, 290, 350, 400, 450),
            evs: new \Src\Pokemon\Domain\Stats\StatsValue(0, 0, 0, 0, 0, 0),
            nivel: 20,
        );
        $this->assertSame((float) $esperado->hp, $rivales[0]->hpActual());
        $this->assertSame((float) $esperado->defenseHp, $rivales[0]->defensaHpActual());
        $this->assertSame((float) $esperado->spDefenseHp, $rivales[0]->defensaEspHpActual());
        $this->assertSame((float) $esperado->attack, $rivales[0]->pokemon()->battleStats()->attack);
    }

    #[Test]
    public function test_no_se_puede_combatir_piso_no_disponible(): void
    {
        $habitat = $this->crearHabitat($this->configMazmorra(3));
        // Avanza el progreso: piso 1 ganado → el 2 es el disponible.
        \App\Models\DungeonProgress::create([
            'user_id' => $this->user->id,
            'habitat_id' => $habitat->id,
            'current_floor' => 2,
        ]);

        $this->postJson("/api/habitats/{$habitat->id}/mazmorra/1/combatir", [
            'team_id' => $this->team->id,
        ])->assertUnprocessable();

        $this->postJson("/api/habitats/{$habitat->id}/mazmorra/3/combatir", [
            'team_id' => $this->team->id,
        ])->assertUnprocessable();
    }

    #[Test]
    public function test_cooldown_de_una_hora_tras_derrota(): void
    {
        $habitat = $this->crearHabitat($this->configMazmorra(2));
        \App\Models\DungeonLog::create([
            'user_id' => $this->user->id,
            'habitat_id' => $habitat->id,
            'floor' => 1,
            'won' => false,
            'fought_at' => now()->subMinutes(30),
        ]);

        $response = $this->getJson("/api/habitats/{$habitat->id}/mazmorra");

        $response->assertOk();
        $pisos = $response->json('pisos');
        $this->assertSame(1, $pisos[0]['piso']);
        $this->assertSame('cooldown', $pisos[0]['estado']);
        $this->assertNotNull($pisos[0]['cooldown_hasta']);

        $this->postJson("/api/habitats/{$habitat->id}/mazmorra/1/combatir", [
            'team_id' => $this->team->id,
        ])->assertUnprocessable();
    }

    #[Test]
    public function test_derrota_antigua_no_bloquea_el_piso(): void
    {
        $habitat = $this->crearHabitat($this->configMazmorra(2));
        \App\Models\DungeonLog::create([
            'user_id' => $this->user->id,
            'habitat_id' => $habitat->id,
            'floor' => 1,
            'won' => false,
            'fought_at' => now()->subHours(2),
        ]);

        $response = $this->getJson("/api/habitats/{$habitat->id}/mazmorra");

        $response->assertOk();
        $pisos = $response->json('pisos');
        $this->assertSame(1, $pisos[0]['piso']);
        $this->assertSame('disponible', $pisos[0]['estado']);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function crearHabitat(?array $mazmorra): Habitat
    {
        $province = Province::create(['name' => 'Provincia Test']);

        return Habitat::create([
            'province_id' => $province->id,
            'name' => 'Hábitat Test',
            'pokemons' => [],
            'peligro' => 3,
            'mazmorra' => $mazmorra,
        ]);
    }

    /** @return array{pisos: list<array{piso: int, species_id: int}>} */
    private function configMazmorra(int $pisos): array
    {
        $lista = [];
        for ($i = 1; $i <= $pisos; $i++) {
            $lista[] = ['piso' => $i, 'species_id' => 24 + $i];
        }

        return ['pisos' => $lista];
    }

    private function crearPokemonJefe(int $id): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => 'jefe-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $stats = [
            [StatEnum::HP, 49],
            [StatEnum::ATTACK, 39],
            [StatEnum::DEFENSE, 29],
            [StatEnum::SPECIAL_ATTACK, 35],
            [StatEnum::SPECIAL_DEFENSE, 40],
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
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => TipoEnum::NORMAL, 'slot' => 1]);

        return $pokemon;
    }

    private function crearEquipoJugador(User $user): Team
    {
        $team = Team::create(['name' => 'Equipo Test', 'user_id' => $user->id]);

        foreach ([1, 2, 3] as $slot) {
            $pokemon = Pokemon::create([
                'id' => 1000 + $slot,
                'name' => 'jugador-'.$slot,
                'species_id' => 1000 + $slot,
                'capture_rate' => 45,
                'base_experience' => 64,
                'height' => 7,
                'weight' => 69,
            ]);

            foreach (StatEnum::cases() as $stat) {
                PokemonStat::create([
                    'pokemon_id' => $pokemon->id,
                    'stat' => $stat->value,
                    'base_stat' => 100,
                    'effort' => 0,
                ]);
            }

            PokemonType::create([
                'pokemon_id' => $pokemon->id,
                'type' => TipoEnum::NORMAL,
                'slot' => 1,
            ]);

            $reclutado = Reclutado::create([
                'user_id' => $user->id,
                'nombre' => 'jugador-'.$slot,
                'pokemon_id' => $pokemon->id,
                'exp' => ['exp' => 100],
                'obj_equipados' => [],
                'movimientos' => [],
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'pokemon_id' => $reclutado->id,
                'slot' => $slot,
                'behavior' => 'VANGUARDIA',
            ]);
        }

        return $team;
    }
}
