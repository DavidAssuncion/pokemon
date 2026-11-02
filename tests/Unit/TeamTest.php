<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La exploración es individual por reclutado: el equipo ya no expone estado
 * de exploración propio. Se cubre `Reclutado::estaEnExploracion()`.
 */
class TeamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un equipo con un miembro (reclutado) y devuelve [team, reclutado].
     *
     * @return array{0: Team, 1: Reclutado}
     */
    private function crearEquipoConReclutado(): array
    {
        $usuario = User::factory()->create();
        $team = Team::create(['name' => 'Alpha', 'user_id' => $usuario->id]);
        $pokemon = Pokemon::firstOrCreate(['id' => 9200], [
            'name' => 'recluta-9200',
            'species_id' => 1,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => 51,
        ]);
        $reclutado = Reclutado::create([
            'user_id' => $usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Miembro',
            'exp' => ['total' => 0],
        ]);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado->id, 'slot' => 1]);

        return [$team, $reclutado];
    }

    private function crearHabitat(): Habitat
    {
        $province = Province::firstOrCreate(['id' => 1], ['name' => 'Kanto']);

        return Habitat::firstOrCreate(['id' => 1], ['name' => 'Bosque', 'province_id' => $province->id]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearExploracion(Reclutado $reclutado, array $atributos = []): ExploracionActiva
    {
        return ExploracionActiva::create(array_merge([
            'user_id' => $reclutado->user_id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $this->crearHabitat()->id,
            'nivel' => 1,
        ], $atributos));
    }

    public function test_reclutado_no_esta_en_exploracion_sin_exploraciones(): void
    {
        [, $reclutado] = $this->crearEquipoConReclutado();

        $this->assertFalse($reclutado->estaEnExploracion());
    }

    public function test_reclutado_esta_en_exploracion_con_exploracion_activa(): void
    {
        [, $reclutado] = $this->crearEquipoConReclutado();
        $this->crearExploracion($reclutado);

        $this->assertTrue($reclutado->estaEnExploracion());
    }

    public function test_reclutado_no_esta_en_exploracion_tras_regreso(): void
    {
        [, $reclutado] = $this->crearEquipoConReclutado();
        $exploracion = $this->crearExploracion($reclutado);
        $exploracion->update(['regreso' => now()]);

        $this->assertFalse($reclutado->estaEnExploracion());
    }

    public function test_reclutado_esta_en_exploracion_si_una_de_varias_sigue_activa(): void
    {
        [, $reclutado] = $this->crearEquipoConReclutado();

        $completada = $this->crearExploracion($reclutado);
        $completada->update(['regreso' => now()]);
        $this->crearExploracion($reclutado, ['nivel' => 2]);

        $this->assertTrue($reclutado->estaEnExploracion());
    }
}
