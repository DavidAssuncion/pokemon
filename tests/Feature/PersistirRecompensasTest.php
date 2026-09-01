<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pokemon;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Tests\TestCase;

class PersistirRecompensasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
    }

    /**
     * @return array{equipo: Team, reclutado: Reclutado}
     */
    private function crearEquipoConReclutado(): array
    {
        $pokemon = Pokemon::create([
            'id' => 25,
            'name' => 'pikachu',
            'species_id' => 25,
            'capture_rate' => 190,
            'base_experience' => 112,
            'height' => 4,
            'weight' => 60,
        ]);

        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'nombre' => 'Pika',
            'pokemon_id' => $pokemon->id,
            'exp' => ['total' => 1250, 'tipos' => ['Fuego' => 100]],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $equipo = Team::create(['name' => 'Equipo Test', 'user_id' => $this->usuario->id]);
        TeamMember::create([
            'team_id' => $equipo->id,
            'pokemon_id' => $reclutado->id,
            'slot' => 1,
            'behavior' => 'VANGUARDIA',
        ]);

        return ['equipo' => $equipo, 'reclutado' => $reclutado];
    }

    /**
     * @param  array<string, int>  $expTipoPorMiembro
     */
    private function resultado(int $expTotal, int $expPorMiembro, array $expTipoPorMiembro = []): ResultadoRecompensas
    {
        return new ResultadoRecompensas(
            capturas: new Collection(),
            caramelosFamilia: new Collection(),
            caramelosEv: new Collection(),
            caramelosTipo: new Collection(),
            expTotal: $expTotal,
            expPorMiembro: $expPorMiembro,
            expTipoPorMiembro: $expTipoPorMiembro,
        );
    }

    public function test_persistir_acumula_exp_total_y_exp_tipo_en_el_reclutado(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        (new PersistirRecompensas())->persistir(
            $this->resultado(expTotal: 500, expPorMiembro: 300, expTipoPorMiembro: ['Veneno' => 500]),
            $ctx['equipo'],
            $this->usuario,
        );

        $reclutado = $ctx['reclutado']->fresh();
        // El total del reclutado acumula la parte de miembro (no el 100 %).
        $this->assertSame(1250 + 300, $reclutado->exp->total());
        // La exp de tipo se acumula en exp.tipos.
        $this->assertSame(500, $reclutado->exp->expTipo('Veneno'));
        // Los tipos no implicados quedan intactos.
        $this->assertSame(100, $reclutado->exp->expTipo('Fuego'));
    }

    public function test_persistir_no_crea_exp_tipo_de_tipos_ausentes(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        (new PersistirRecompensas())->persistir(
            $this->resultado(expTotal: 100, expPorMiembro: 60, expTipoPorMiembro: ['Veneno' => 500]),
            $ctx['equipo'],
            $this->usuario,
        );

        $reclutado = $ctx['reclutado']->fresh();
        $this->assertSame(500, $reclutado->exp->expTipo('Veneno'));
        $this->assertSame(0, $reclutado->exp->expTipo('Eléctrico'));
    }

    public function test_persistir_sin_exp_tipo_por_miembro_no_anade_tipos(): void
    {
        $ctx = $this->crearEquipoConReclutado();

        (new PersistirRecompensas())->persistir(
            $this->resultado(expTotal: 100, expPorMiembro: 60),
            $ctx['equipo'],
            $this->usuario,
        );

        $reclutado = $ctx['reclutado']->fresh();
        $this->assertSame(1250 + 60, $reclutado->exp->total());
        $this->assertSame(100, $reclutado->exp->expTipo('Fuego'));
        $this->assertSame(0, $reclutado->exp->expTipo('Veneno'));
    }
}
