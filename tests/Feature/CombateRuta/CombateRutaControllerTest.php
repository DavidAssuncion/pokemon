<?php

declare(strict_types=1);

namespace Tests\Feature\CombateRuta;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CombateRutaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Habitat $habitat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['experiencia' => 10 * 20 ** 3]);
        $this->habitat = $this->crearHabitat('Ruta Sur');
        $this->actingAs($this->user);

        for ($id = 1; $id <= 5; $id++) {
            $this->crearPokemonDelPool($id, captureRate: 200);
        }
    }

    #[Test]
    public function inicia_combate_con_team_y_formation_valida(): void
    {
        $team = $this->crearEquipo(5);

        $response = $this->postJson("/api/habitats/{$this->habitat->id}/ruta/iniciar", [
            'team_id' => $team->id,
            'formacion' => [1 => 'vanguardia', 2 => 'retaguardia'],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['battle_id', 'redirect'])
            ->assertJsonFragment(['redirect' => url('/combate?battle_id='.$response->json('battle_id'))]);
    }

    #[Test]
    public function team_ajeno_no_puede_iniciar(): void
    {
        $otro = User::factory()->create();
        $team = $this->crearEquipo(5, $otro);

        $this->postJson("/api/habitats/{$this->habitat->id}/ruta/iniciar", [
            'team_id' => $team->id,
        ])->assertUnprocessable();
    }

    #[Test]
    public function formacion_invalida_rechaza(): void
    {
        $team = $this->crearEquipo(5);

        $this->postJson("/api/habitats/{$this->habitat->id}/ruta/iniciar", [
            'team_id' => $team->id,
            'formacion' => [1 => 'volando'],
        ])->assertUnprocessable();
    }

    #[Test]
    public function rivales_del_panel_devuelve_cinco_salvajes(): void
    {
        $response = $this->getJson("/api/habitats/{$this->habitat->id}/ruta/rivales");

        $response->assertOk();
        $this->assertCount(5, $response->json('rivales'));
        foreach ($response->json('rivales') as $rival) {
            $this->assertArrayHasKey('id', $rival);
            $this->assertArrayHasKey('nombre', $rival);
            $this->assertArrayHasKey('posicion', $rival);
        }
    }

    private function crearHabitat(string $nombre): Habitat
    {
        $province = Province::create(['name' => 'Kanto']);

        return Habitat::create(['province_id' => $province->id, 'name' => $nombre, 'peligro' => 1]);
    }

    private function crearPokemonDelPool(int $id, int $captureRate): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => 'salvaje-'.$id,
            'species_id' => $id,
            'capture_rate' => $captureRate,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => 10,
        ]);

        foreach (StatEnum::cases() as $stat) {
            $pokemon->stats()->create([
                'stat' => $stat->value,
                'base_stat' => 80,
                'effort' => 0,
            ]);
        }

        $pokemon->types()->create([
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

        $this->habitat->pokemon()->attach($pokemon->id, ['level' => 1]);

        return $pokemon;
    }

    private function crearEquipo(int $miembros, ?User $dueno = null): Team
    {
        $dueno ??= $this->user;
        $team = Team::create(['name' => 'Equipo Ruta', 'user_id' => $dueno->id]);

        for ($slot = 1; $slot <= $miembros; $slot++) {
            $pokemon = Pokemon::create([
                'id' => 9100 + $slot,
                'name' => 'jugador-'.$slot,
                'species_id' => 9100 + $slot,
                'capture_rate' => 45,
                'base_experience' => 64,
                'height' => 7,
                'weight' => 69,
            ]);

            foreach (StatEnum::cases() as $stat) {
                $pokemon->stats()->create([
                    'stat' => $stat->value,
                    'base_stat' => 100,
                    'effort' => 0,
                ]);
            }

            $pokemon->types()->create([
                'type' => TipoEnum::NORMAL,
                'slot' => 1,
            ]);

            $reclutado = Reclutado::create([
                'user_id' => $dueno->id,
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
