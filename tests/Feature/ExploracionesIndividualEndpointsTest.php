<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\Favorito;
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

class ExploracionesIndividualEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(['experiencia' => 10 * 10 ** 3]);
        $this->actingAs($this->usuario);
    }

    private function crearPokemonConStats(int $id, array $stats, TipoEnum $tipo): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => 'pokemon-'.$id,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 50 + $id,
        ]);

        $mapa = [
            'hp' => StatEnum::HP,
            'atk' => StatEnum::ATTACK,
            'def' => StatEnum::DEFENSE,
            'spAtk' => StatEnum::SPECIAL_ATTACK,
            'spDef' => StatEnum::SPECIAL_DEFENSE,
            'speed' => StatEnum::SPEED,
        ];
        foreach ($mapa as $clave => $stat) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat,
                'base_stat' => $stats[$clave],
                'effort' => 0,
            ]);
        }

        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => $tipo, 'slot' => 1]);

        return $pokemon;
    }

    private function crearReclutado(int $pokemonId, string $nombre = 'Reclutado'): Reclutado
    {
        return Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemonId,
            'nombre' => $nombre,
            'exp' => ['total' => 10 * 5 ** 3],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);
    }

    private function crearHabitat(int $id, int $peligro = 1): Habitat
    {
        $province = Province::create(['id' => 100 + $id, 'name' => 'Provincia-'.$id]);

        return Habitat::create(['id' => $id, 'name' => 'Habitat-'.$id, 'province_id' => $province->id, 'peligro' => $peligro]);
    }

    #[Test]
    public function test_toggle_favorito_reclutado_alterna(): void
    {
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = $this->crearReclutado($pokemon->id);

        // Global (habitat_id null).
        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => null]);
        $response->assertOk()->assertJson(['favorito' => true, 'count' => 1]);

        $this->assertDatabaseHas('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => null,
        ]);

        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => null]);
        $response->assertOk()->assertJson(['favorito' => false, 'count' => 0]);

        $this->assertDatabaseMissing('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
        ]);
    }

    #[Test]
    public function test_toggle_favorito_reclutado_ajeno_404(): void
    {
        $otro = User::factory()->create();
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = Reclutado::create([
            'id' => 2,
            'user_id' => $otro->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Ajeno',
            'exp' => ['total' => 0],
        ]);

        $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito")->assertNotFound();
    }

    #[Test]
    public function test_favoritos_lista_solo_favoritos_del_usuario(): void
    {
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutadoFav = $this->crearReclutado($pokemon->id, 'Fav');
        $this->postJson("/api/reclutados/{$reclutadoFav->id}/toggle-favorito", ['habitat_id' => null])->assertOk();
        // Un favorito por hábitat NO debe aparecer en la lista global.
        $habitat = $this->crearHabitat(1);
        $pokemon2 = $this->crearPokemonConStats(2, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutadoHab = $this->crearReclutado($pokemon2->id, 'NoGlobal');
        $this->postJson("/api/reclutados/{$reclutadoHab->id}/toggle-favorito", ['habitat_id' => $habitat->id])->assertOk();

        $response = $this->getJson('/api/reclutados/favoritos');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame('Fav', $data[0]['nombre']);
        $this->assertArrayHasKey('nivel', $data[0]);
        $this->assertArrayHasKey('stats', $data[0]);
    }

    #[Test]
    public function test_capacidades_devuelve_todas_las_capacidades(): void
    {
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = $this->crearReclutado($pokemon->id);

        $response = $this->getJson("/api/reclutado/{$reclutado->id}/capacidades");

        $response->assertOk()
            ->assertJsonStructure(['combate', 'deteccion', 'recoleccion', 'supervivencia', 'exploracion', 'movilidad']);
    }

    #[Test]
    public function test_capacidades_reclutado_ajeno_404(): void
    {
        $otro = User::factory()->create();
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = Reclutado::create([
            'id' => 2,
            'user_id' => $otro->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Ajeno',
            'exp' => ['total' => 0],
        ]);

        $this->getJson("/api/reclutado/{$reclutado->id}/capacidades")->assertNotFound();
    }

    #[Test]
    public function test_toggle_favorito_habitat_anade_y_elimina(): void
    {
        $habitat = $this->crearHabitat(1);
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = $this->crearReclutado($pokemon->id);

        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id]);
        $response->assertOk()->assertJson(['favorito' => true, 'count' => 1]);

        $this->assertDatabaseHas('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
        ]);

        $response = $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id]);
        $response->assertOk()->assertJson(['favorito' => false, 'count' => 0]);

        $this->assertDatabaseMissing('favoritos', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
        ]);
    }

    #[Test]
    public function test_toggle_favorito_habitat_limite_6(): void
    {
        $habitat = $this->crearHabitat(1);
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);

        for ($i = 1; $i <= 6; $i++) {
            $this->crearPokemonConStats(100 + $i, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
            $reclutado = $this->crearReclutado(100 + $i, 'P'.$i);
            $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id])->assertOk();
        }

        $reclutado7 = $this->crearReclutado($pokemon->id, 'P7');
        $this->postJson("/api/reclutados/{$reclutado7->id}/toggle-favorito", ['habitat_id' => $habitat->id])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function test_toggle_favorito_habitat_repetido_no_suma_mas_de_6(): void
    {
        $habitat = $this->crearHabitat(1);
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = $this->crearReclutado($pokemon->id);

        // Marcar y desmarcar el mismo no debe contar doble
        $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id])->assertOk();
        $this->postJson("/api/reclutados/{$reclutado->id}/toggle-favorito", ['habitat_id' => $habitat->id])->assertOk();

        $this->assertSame(0, Favorito::where('user_id', $this->usuario->id)->count());
    }

    #[Test]
    public function test_store_individual_crea_exploracion_con_reclutado(): void
    {
        $habitat = $this->crearHabitat(1);
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = $this->crearReclutado($pokemon->id);

        $response = $this->postJson('/api/exploraciones/store-individual', [
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
            'duracion_horas' => 2,
        ]);

        $response->assertStatus(201)->assertJson(['ok' => true]);

        $this->assertDatabaseHas('exploraciones_activas', [
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
        ]);
    }

    #[Test]
    public function test_store_individual_rechaza_reclutado_en_exploracion_activa(): void
    {
        $habitat = $this->crearHabitat(1);
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = $this->crearReclutado($pokemon->id);

        ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'indefinido' => false,
        ]);

        $response = $this->postJson('/api/exploraciones/store-individual', [
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function test_store_individual_rechaza_reclutado_ajeno(): void
    {
        $otro = User::factory()->create();
        $habitat = $this->crearHabitat(1);
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutadoAjeno = Reclutado::create([
            'id' => 2,
            'user_id' => $otro->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Ajeno',
            'exp' => ['total' => 0],
        ]);

        $response = $this->postJson('/api/exploraciones/store-individual', [
            'reclutado_id' => $reclutadoAjeno->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function test_store_individual_rechaza_si_equipo_del_reclutado_esta_explorando(): void
    {
        $habitat = $this->crearHabitat(1);
        $pokemon = $this->crearPokemonConStats(1, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado = $this->crearReclutado($pokemon->id);
        $team = Team::create(['name' => 'Equipo', 'user_id' => $this->usuario->id]);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado->id, 'slot' => 1, 'behavior' => 'COMBATIENTE']);

        // Otro reclutado del mismo equipo ya está explorando.
        $pokemon2 = $this->crearPokemonConStats(2, ['hp' => 100, 'atk' => 80, 'def' => 70, 'spAtk' => 90, 'spDef' => 60, 'speed' => 50], TipoEnum::NORMAL);
        $reclutado2 = $this->crearReclutado($pokemon2->id);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);
        ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado2->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 2,
            'indefinido' => false,
        ]);

        $response = $this->postJson('/api/exploraciones/store-individual', [
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'El equipo del reclutado está en una exploración activa.']);
    }
}
