<?php

declare(strict_types=1);

namespace Tests\Feature\Gimnasios;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GimnasioApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_index_lista_los_18_gimnasios_con_estado(): void
    {
        $user = $this->crearUsuarioNivel(10);
        $this->actingAs($user);

        $response = $this->getJson('/api/gimnasios');

        $response->assertOk()
            ->assertJsonCount(18)
            ->assertJsonFragment(['slug' => 'bug', 'medalla' => 'Medalla Bicho', 'estado' => 'disponible'])
            ->assertJsonFragment(['slug' => 'poison', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'normal', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'grass', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'flying', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'rock', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'electric', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'ice', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'fire', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'water', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'ground', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'psychic', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'dark', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'ghost', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'fighting', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'fairy', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'steel', 'estado' => 'bloqueado'])
            ->assertJsonFragment(['slug' => 'dragon', 'estado' => 'bloqueado']);
    }

    #[Test]
    public function test_index_marca_completado_el_gimnasio_con_etapa_5(): void
    {
        $user = $this->crearUsuarioNivel(10);
        $this->actingAs($user);

        $repositorio = $this->app->make(\Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface::class);
        $repositorio->registrarVictoria((int) $user->id, 'bug', 4);

        $response = $this->getJson('/api/gimnasios');

        $response->assertOk();
        $this->assertSame('completado', $response->json()[0]['estado']);
        $this->assertSame(5, $response->json()[0]['etapa_actual']);
    }

    #[Test]
    public function test_show_devuelve_detalle_sin_preview_ni_nivel_rival(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');

        $response = $this->getJson('/api/gimnasios/bug');

        $response->assertOk()
            ->assertJsonPath('medalla', 'Medalla Bicho')
            ->assertJsonPath('tipo', 7)
            ->assertJsonPath('nivel_minimo', 10)
            ->assertJsonPath('nivel_jugador', 20)
            ->assertJsonPath('slug', 'bug')
            ->assertJsonPath('etapa_actual', 1)
            ->assertJsonPath('estado', 'disponible')
            ->assertJsonCount(4, 'etapas')
            ->assertJsonMissing(['nivel_rival']);

        $etapa1 = $response->json('etapas.0');
        $this->assertSame('Entrenador 1', $etapa1['nombre']);
        $this->assertSame(['etapa', 'nombre'], array_keys($etapa1));

        foreach ($response->json('etapas') as $etapa) {
            $this->assertArrayNotHasKey('pokemon', $etapa);
            $this->assertSame(['etapa', 'nombre'], array_keys($etapa));
        }
    }

    #[Test]
    public function test_show_no_expone_pokemon_rivales_ni_nivel_rival(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');

        $response = $this->getJson('/api/gimnasios/bug');

        $response->assertOk();
        $this->assertArrayNotHasKey('nivel_rival', $response->json());

        $etapas = $response->json('etapas');
        foreach ($etapas as $etapa) {
            $this->assertArrayNotHasKey('pokemon', $etapa);
            $this->assertSame(['etapa', 'nombre'], array_keys($etapa));
        }
    }

    #[Test]
    public function test_show_404_con_slug_desconocido(): void
    {
        $user = $this->crearUsuarioNivel(10);
        $this->actingAs($user);

        $response = $this->getJson('/api/gimnasios/no-existe');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_combatir_inicia_batalla_y_guarda_meta(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');
        $team = $this->crearEquipoJugador($user);

        $response = $this->postJson('/api/gimnasios/bug/combatir', [
            'team_id' => $team->id,
            'formacion' => ['1' => 'vanguardia', '2' => 'retaguardia', '3' => 'vanguardia'],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['battle_id', 'redirect']);

        $battleId = $response->json('battle_id');
        $this->assertStringStartsWith('battle_gimnasio_', $battleId);

        $meta = session($battleId.'_meta');
        $this->assertSame('gimnasio', $meta['tipo']);
        $this->assertSame('bug', $meta['gym_id']);
        $this->assertSame(1, $meta['stage']);
        $this->assertSame(15, $meta['nivel_rival']);
        $this->assertSame((int) $user->id, $meta['user_id']);
        $this->assertSame((int) $team->id, $meta['team_id']);
    }

    #[Test]
    public function test_combatir_bloqueado_por_nivel_minimo(): void
    {
        $user = $this->crearUsuarioNivel(5);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');
        $team = $this->crearEquipoJugador($user);

        $response = $this->postJson('/api/gimnasios/bug/combatir', [
            'team_id' => $team->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Aún no tienes nivel suficiente. Necesitas nivel 10.');
    }

    #[Test]
    public function test_combatir_bloqueado_por_gimnasio_completado(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');
        $team = $this->crearEquipoJugador($user);

        $repositorio = $this->app->make(\Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface::class);
        $repositorio->registrarVictoria((int) $user->id, 'bug', 4);

        $response = $this->postJson('/api/gimnasios/bug/combatir', [
            'team_id' => $team->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Ya has completado este gimnasio.');
    }

    #[Test]
    public function test_combatir_404_con_slug_desconocido(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $team = $this->crearEquipoJugador($user);

        $response = $this->postJson('/api/gimnasios/no-existe/combatir', [
            'team_id' => $team->id,
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function test_combatir_valida_team_id_pertenece_al_usuario(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');

        $otroUsuario = User::factory()->create();
        $teamAjeno = $this->crearEquipoJugador($otroUsuario);

        $response = $this->postJson('/api/gimnasios/bug/combatir', [
            'team_id' => $teamAjeno->id,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function test_combatir_valida_formacion(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');
        $team = $this->crearEquipoJugador($user);

        $response = $this->postJson('/api/gimnasios/bug/combatir', [
            'team_id' => $team->id,
            'formacion' => ['1' => 'lateral'],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function test_combatir_escala_stats_jugador_al_nivel_del_usuario(): void
    {
        $user = $this->crearUsuarioNivel(17);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');
        $team = $this->crearEquipoJugadorConStats($user, ['hp' => 35, 'atk' => 40, 'def' => 45, 'spAtk' => 50, 'spDef' => 55, 'speed' => 60]);

        $response = $this->postJson('/api/gimnasios/bug/combatir', [
            'team_id' => $team->id,
            'formacion' => ['1' => 'vanguardia', '2' => 'retaguardia', '3' => 'vanguardia'],
        ]);

        $response->assertOk();
        $battleId = $response->json('battle_id');

        // Leer batalla de sesión y verificar que team1 (jugador) tiene stats escaladas
        $raw = session($battleId);
        $this->assertNotNull($raw, 'La batalla debe estar en sesión');
        $serialized = substr($raw, strpos($raw, '|') + 1);
        $battle = unserialize($serialized);

        $combatants = $battle->team1->combatants();
        $this->assertCount(3, $combatants);

        // HP base 35, nivel 17 → BattleStats::calcularHp(35,0,17) = floor((2*35*17/100)+17+10) = 38
        $this->assertSame(38, (int) $combatants[0]->pokemon()->battleStats()->hp);
        // atk base 40, nivel 17 → floor((2*40*17/100)+5) = 18
        $this->assertSame(18, (int) $combatants[0]->pokemon()->battleStats()->attack);
        // speed base 60, nivel 17 → floor((2*60*17/100)+5) = 25
        $this->assertSame(25, (int) $combatants[0]->pokemon()->battleStats()->speed);
    }

    #[Test]
    public function test_combatir_etapa_entrenador_aplica_evs_64_64(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');
        $team = $this->crearEquipoJugador($user);

        $response = $this->postJson('/api/gimnasios/bug/combatir', [
            'team_id' => $team->id,
            'formacion' => ['1' => 'vanguardia', '2' => 'retaguardia', '3' => 'vanguardia'],
        ]);

        $response->assertOk();
        $battle = $this->batallaDeSesion($response->json('battle_id'));

        // Etapa 1-3 → gimnasio 64/64: todos los EVs del rival a 64
        $rivales = $battle->team2->combatants();
        $this->assertNotEmpty($rivales);

        foreach ($rivales as $rival) {
            $evs = $rival->pokemon()->evs();
            $this->assertSame(64.0, $evs->hp, 'hp debe ser 64 en etapa entrenador');
            $this->assertSame(64.0, $evs->attack, 'attack debe ser 64 en etapa entrenador');
            $this->assertSame(64.0, $evs->defense, 'defense debe ser 64 en etapa entrenador');
            $this->assertSame(64.0, $evs->spAtk, 'spAtk debe ser 64 en etapa entrenador');
            $this->assertSame(64.0, $evs->spDef, 'spDef debe ser 64 en etapa entrenador');
            $this->assertSame(64.0, $evs->speed, 'speed debe ser 64 en etapa entrenador');
        }
    }

    #[Test]
    public function test_combatir_lider_aplica_evs_128_64(): void
    {
        $user = $this->crearUsuarioNivel(20);
        $this->actingAs($user);
        $this->crearPokemonsGym('bug');
        $team = $this->crearEquipoJugador($user);

        // Avanza hasta el líder (etapa 4)
        $repositorio = $this->app->make(\Src\Gimnasios\Domain\Repositories\GymProgressRepositoryInterface::class);
        $repositorio->registrarVictoria((int) $user->id, 'bug', 1);
        $repositorio->registrarVictoria((int) $user->id, 'bug', 2);
        $repositorio->registrarVictoria((int) $user->id, 'bug', 3);

        $response = $this->postJson('/api/gimnasios/bug/combatir', [
            'team_id' => $team->id,
            'formacion' => ['1' => 'vanguardia', '2' => 'retaguardia', '3' => 'vanguardia'],
        ]);

        $response->assertOk();
        $battle = $this->batallaDeSesion($response->json('battle_id'));

        // Etapa 4 (líder) → 128 para los 2 mejores stats base, 64 para el resto.
        // Los pokémon de prueba tienen atk=60 y spAtk=60 (los más altos), el resto 50/55.
        $rivales = $battle->team2->combatants();
        $this->assertNotEmpty($rivales);

        foreach ($rivales as $rival) {
            $evs = $rival->pokemon()->evs();
            $this->assertSame(64.0, $evs->hp, 'hp debe ser 64 en líder');
            $this->assertSame(128.0, $evs->attack, 'attack debe ser 128 en líder (stat alto)');
            $this->assertSame(64.0, $evs->defense, 'defense debe ser 64 en líder');
            $this->assertSame(128.0, $evs->spAtk, 'spAtk debe ser 128 en líder (stat alto)');
            $this->assertSame(64.0, $evs->spDef, 'spDef debe ser 64 en líder');
            $this->assertSame(64.0, $evs->speed, 'speed debe ser 64 en líder');
        }
    }

    private function batallaDeSesion(string $battleId): \Src\Battle\Domain\AgregadoBatalla
    {
        $raw = session($battleId);
        $this->assertNotNull($raw, 'La batalla debe estar en sesión');
        $serialized = substr($raw, strpos($raw, '|') + 1);
        $battle = unserialize($serialized);
        $this->assertInstanceOf(\Src\Battle\Domain\AgregadoBatalla::class, $battle);

        return $battle;
    }

    private function crearUsuarioNivel(int $nivel): User
    {
        $experiencia = 10 * $nivel ** 3;

        return User::factory()->create(['experiencia' => $experiencia]);
    }

    /** Crea los pokémon del gimnasio con stats y tipos (una fila por species_id único). */
    private function crearPokemonsGym(string $gymSlug): void
    {
        $gimnasio = $this->app->make(\Src\Gimnasios\Domain\CatalogoGimnasios::class)->porSlug($gymSlug);
        $this->assertNotNull($gimnasio);

        foreach ($gimnasio->equipos as $equipo) {
            foreach ($equipo->todos() as $speciesId) {
                $this->crearPokemonCompleto($speciesId);
            }
        }
    }

    private function crearPokemonCompleto(int $speciesId): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $speciesId,
            'name' => 'pokemon-'.$speciesId,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $stats = [
            StatEnum::HP->value => 50,
            StatEnum::ATTACK->value => 60,
            StatEnum::DEFENSE->value => 50,
            StatEnum::SPECIAL_ATTACK->value => 60,
            StatEnum::SPECIAL_DEFENSE->value => 50,
            StatEnum::SPEED->value => 55,
        ];

        foreach ($stats as $statId => $valor) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $statId,
                'base_stat' => $valor,
                'effort' => 0,
            ]);
        }

        PokemonType::create([
            'pokemon_id' => $pokemon->id,
            'type' => TipoEnum::NORMAL,
            'slot' => 1,
        ]);

        return $pokemon;
    }

    private function crearEquipoJugador(User $user): Team
    {
        return $this->crearEquipoJugadorConStats($user, ['hp' => 100, 'atk' => 100, 'def' => 100, 'spAtk' => 100, 'spDef' => 100, 'speed' => 100]);
    }

    /**
     * @param  array{hp: int, atk: int, def: int, spAtk: int, spDef: int, speed: int}  $stats
     */
    private function crearEquipoJugadorConStats(User $user, array $stats): Team
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

            $statMap = [
                StatEnum::HP->value => 'hp',
                StatEnum::ATTACK->value => 'atk',
                StatEnum::DEFENSE->value => 'def',
                StatEnum::SPECIAL_ATTACK->value => 'spAtk',
                StatEnum::SPECIAL_DEFENSE->value => 'spDef',
                StatEnum::SPEED->value => 'speed',
            ];
            foreach ($statMap as $statId => $statKey) {
                PokemonStat::create([
                    'pokemon_id' => $pokemon->id,
                    'stat' => $statId,
                    'base_stat' => $stats[$statKey],
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
