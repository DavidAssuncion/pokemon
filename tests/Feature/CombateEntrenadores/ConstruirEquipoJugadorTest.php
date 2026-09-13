<?php

declare(strict_types=1);

namespace Tests\Feature\CombateEntrenadores;

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
use Src\Battle\Domain\Posicion;
use Src\CombateEntrenadores\App\ConstruirEquipoJugador;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateRuta\Domain\ClasificadorOfensivaDefensiva;
use Tests\TestCase;

class ConstruirEquipoJugadorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function prioridad_es_popor_persistida_y_luego_auto(): void
    {
        $pokemonOfensivo = $this->crearPokemon(801, 'ofensivo', atk: 120, def: 20, speed: 100);
        $pokemonDefensivo = $this->crearPokemon(802, 'defensivo', atk: 20, def: 120);
        $team = $this->crearEquipoCon($pokemonDefensivo, $pokemonOfensivo);

        $construir = new ConstruirEquipoJugador(
            app(MapeadorPokemonBatalla::class),
            new ClasificadorOfensivaDefensiva(),
        );

        // Sin persistida ni popup → auto: slot 1 es defensivo (vanguardia), slot 2 ofensivo (retaguardia)
        $sinPrioridad = $construir->desdeEquipo($team, []);
        $this->assertSame(Posicion::VANGUARDIA, $sinPrioridad[0]->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $sinPrioridad[1]->posicion);

        // Persistida gana a auto: invertimos la formación en el Team.
        $team->update(['formacion' => [1 => 'retaguardia', 2 => 'vanguardia']]);
        $conPersistida = $construir->desdeEquipo($team, []);
        $this->assertSame(Posicion::RETAGUARDIA, $conPersistida[0]->posicion);
        $this->assertSame(Posicion::VANGUARDIA, $conPersistida[1]->posicion);

        // Popup gana a persistida.
        $conPopup = $construir->desdeEquipo($team, [1 => 'vanguardia', 2 => 'retaguardia']);
        $this->assertSame(Posicion::VANGUARDIA, $conPopup[0]->posicion);
        $this->assertSame(Posicion::RETAGUARDIA, $conPopup[1]->posicion);
    }

    #[Test]
    public function endpoint_guarda_formacion_persistida(): void
    {
        $pokemonA = $this->crearPokemon(911, 'a', atk: 100, def: 30);
        $pokemonB = $this->crearPokemon(912, 'b', atk: 30, def: 100);
        $team = $this->crearEquipoCon($pokemonA, $pokemonB);

        $this->actingAs($this->user)
            ->patchJson("/teams/{$team->id}/formacion", [
                'formacion' => [1 => 'retaguardia', 2 => 'vanguardia'],
            ])->assertOk();

        $this->assertSame([1 => 'retaguardia', 2 => 'vanguardia'], $team->fresh()->formacion);
    }

    #[Test]
    public function endpoint_rechaza_posiciones_invalidas_y_equipos_ajenos(): void
    {
        $pokemonA = $this->crearPokemon(921, 'a', atk: 100, def: 30);
        $team = $this->crearEquipoCon($pokemonA);

        $this->actingAs($this->user)
            ->patchJson("/teams/{$team->id}/formacion", [
                'formacion' => [1 => 'volando'],
            ])->assertUnprocessable();

        $otro = User::factory()->create();
        $this->actingAs($otro)
            ->patchJson("/teams/{$team->id}/formacion", [
                'formacion' => [1 => 'vanguardia'],
            ])->assertNotFound();
    }

    private function crearPokemon(int $id, string $nombre, int $atk, int $def, int $speed = 60): Pokemon
    {
        $pokemon = Pokemon::create([
            'id' => $id,
            'name' => $nombre,
            'species_id' => $id,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
        ]);

        $stats = [StatEnum::HP->value => 100, StatEnum::ATTACK->value => $atk, StatEnum::DEFENSE->value => $def, StatEnum::SPECIAL_ATTACK->value => 60, StatEnum::SPECIAL_DEFENSE->value => 60, StatEnum::SPEED->value => $speed];
        foreach ($stats as $stat => $valor) {
            PokemonStat::create([
                'pokemon_id' => $pokemon->id,
                'stat' => $stat,
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

    private function crearEquipoCon(Pokemon ...$pokemons): Team
    {
        $team = Team::create(['name' => 'Equipo Formación', 'user_id' => $this->user->id]);

        foreach ($pokemons as $index => $pokemon) {
            $reclutado = Reclutado::create([
                'user_id' => $this->user->id,
                'nombre' => $pokemon->name,
                'pokemon_id' => $pokemon->id,
                'exp' => ['exp' => 100],
                'obj_equipados' => [],
                'movimientos' => [],
            ]);

            TeamMember::create([
                'team_id' => $team->id,
                'pokemon_id' => $reclutado->id,
                'slot' => $index + 1,
                'behavior' => 'VANGUARDIA',
            ]);
        }

        return $team;
    }
}
