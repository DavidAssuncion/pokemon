<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\CombateEntrenadores\App\MapeadorPokemonBatalla;
use Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo;
use Src\Exploraciones\App\CombateExploracion;
use Src\Exploraciones\App\FinalizarExploracionHandler;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\App\ProcesarExploracionHandler;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\UnitOfWork;
use Src\Shared\Domain\EscaladorNivelRival;
use Tests\TestCase;

class ExploracionesAutoResolucionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private User $otroUsuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create(['experiencia' => 1_250]);
        $this->otroUsuario = User::factory()->create(['experiencia' => 1_250]);
        $this->actingAs($this->usuario);
    }

    /**
     * Crea una exploración activa del usuario autenticado (+ pokémon en el
     * hábitat) con handlers deterministas para que los ticks sean predecibles.
     *
     * @param  array<string, mixed>  $opciones
     * @return array{exploracion: ExploracionActiva, chainId: int}
     */
    private function crearContexto(array $opciones = []): array
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1, 'peligro' => 1]);

        $chainId = 51;

        $pokemon1 = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 255,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => 10,
            'evolution_chain_id' => $chainId,
        ]);

        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => 1, 'level' => 1],
        ]);

        PokemonStat::create(['pokemon_id' => 1, 'stat' => 1, 'base_stat' => 45, 'effort' => 2]);
        PokemonStat::create(['pokemon_id' => 1, 'stat' => 2, 'base_stat' => 49, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => 1, 'stat' => 3, 'base_stat' => 49, 'effort' => 1]);

        $team = Team::create(['name' => 'Equipo Test', 'user_id' => $this->usuario->id]);

        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => 1,
            'nombre' => 'Bulbi',
            'exp' => ['total' => 0],
        ]);

        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado->id, 'slot' => 1, 'behavior' => 'COMBATIENTE']);

        $exploracion = ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => $opciones['duracion_horas'] ?? null,
            'indefinido' => $opciones['indefinido'] ?? false,
            'inicio_exploracion' => $opciones['inicio'] ?? now()->subMinutes(30),
        ]);

        return [
            'exploracion' => $exploracion,
            'chainId' => $chainId,
        ];
    }

    /**
     * Crea una exploración activa de OTRO usuario (sin pokémon en hábitat).
     */
    private function crearExploracionAjena(array $opciones = []): ExploracionActiva
    {
        $province = Province::create(['id' => 2, 'name' => 'Johto']);
        $habitat = Habitat::create(['id' => 2, 'name' => 'Cueva', 'province_id' => 2]);
        $team = Team::create(['name' => 'Equipo Ajeno', 'user_id' => $this->otroUsuario->id]);
        $pokemonAjeno = Pokemon::create([
            'id' => 2,
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 45,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
            'hatch' => 10,
            'evolution_chain_id' => 2,
        ]);
        $reclutadoAjeno = Reclutado::create([
            'user_id' => $this->otroUsuario->id,
            'pokemon_id' => $pokemonAjeno->id,
            'nombre' => 'Ajeno',
            'exp' => ['total' => 0],
        ]);

        return ExploracionActiva::create([
            'user_id' => $this->otroUsuario->id,
            'reclutado_id' => $reclutadoAjeno->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => $opciones['duracion_horas'] ?? null,
            'indefinido' => $opciones['indefinido'] ?? false,
            'inicio_exploracion' => $opciones['inicio'] ?? now()->subMinutes(30),
        ]);
    }

    /**
     * Vincula handlers deterministas para que el pipeline sea predecible.
     * 0.3 → solo encuentros normales (victoria), categoría exito ×1.0.
     */
    private function bindHandlersDeterministas(): void
    {
        $this->app->instance(
            ProcesarExploracionHandler::class,
            new ProcesarExploracionHandler(
                app(CommandBus::class),
                new CombateExploracion(new MapeadorPokemonBatalla(new GeneradorMovimientosTipo())),
                new EscaladorNivelRival(),
                fn (): float => 0.3,
            ),
        );
        $this->app->instance(
            FinalizarExploracionHandler::class,
            new FinalizarExploracionHandler(
                app(UnitOfWork::class),
                app(CalculadorRecompensas::class),
                app(PersistirRecompensas::class),
                app(TransformadorResultadoExploracion::class),
                fn (): float => 0.0,
            ),
        );
    }

    public function test_index_procesa_exploraciones_activas_al_cargar(): void
    {
        // Crear exploración activa que ya pasó su duración (2h de duración,
        // inicio hace 3h) → el tick debe finalizarla.
        $this->bindHandlersDeterministas();
        $ctx = $this->crearContexto(['duracion_horas' => 2, 'inicio' => now()->subHours(3)]);

        $this->get('/exploraciones')->assertOk();

        $ctx['exploracion']->refresh();
        $this->assertNotNull($ctx['exploracion']->regreso, 'La exploración debió finalizarse.');
        $this->assertNotEmpty($ctx['exploracion']->eventos['bitacora'], 'La bitácora no debe estar vacía.');
        $this->assertNotEmpty($ctx['exploracion']->eventos['derrotados'] ?? [], 'Debe haber derrotados.');
    }

    public function test_index_no_reprocesa_exploracion_ya_finalizada(): void
    {
        $this->bindHandlersDeterministas();
        $ctx = $this->crearContexto(['duracion_horas' => 2, 'inicio' => now()->subHours(3)]);
        // Finalizar manualmente con bitácora previa de 2 eventos
        $ctx['exploracion']->eventos = [
            'bitacora' => [
                ['tipo' => 'pokemon', 'pokemon_id' => 1, 'timestamp' => '2026-08-30T10:00:00Z'],
                ['tipo' => 'caramelo_familia', 'pokemon_id' => 1, 'cantidad' => 2, 'timestamp' => '2026-08-30T10:05:00Z'],
            ],
            'ultimo_procesado' => now()->toIso8601String(),
        ];
        $ctx['exploracion']->regreso = now()->subMinutes(10);
        $ctx['exploracion']->save();

        $this->get('/exploraciones')->assertOk();

        $ctx['exploracion']->refresh();
        // La bitácora debe seguir intacta (sin duplicados)
        $this->assertCount(2, $ctx['exploracion']->eventos['bitacora']);
    }

    public function test_index_no_procesa_exploraciones_de_otro_usuario(): void
    {
        $this->bindHandlersDeterministas();
        $ajena = $this->crearExploracionAjena(['duracion_horas' => 2, 'inicio' => now()->subHours(3)]);

        $this->get('/exploraciones')->assertOk();

        $ajena->refresh();
        $this->assertNull($ajena->regreso, 'La exploración ajena no debe ser procesada.');
    }

    public function test_index_no_bloquea_si_un_tick_falla(): void
    {
        // Mock del CommandBus que lanza excepción en el dispatch
        $busMock = $this->createMock(CommandBus::class);
        $busMock->method('dispatch')
            ->willThrowException(new \RuntimeException('Fallo simulado'));
        $this->app->instance(CommandBus::class, $busMock);

        // Handler que usa el bus mockeado
        $this->app->instance(
            ProcesarExploracionHandler::class,
            new ProcesarExploracionHandler($busMock, fn (): float => 0.3),
        );

        $ctx = $this->crearContexto(['duracion_horas' => 2, 'inicio' => now()->subHours(3)]);

        try {
            $this->get('/exploraciones')->assertOk();
        } catch (\Throwable $e) {
            $this->fail('La página no debe bloquearse aunque falle un tick: '.$e->getMessage());
        }

        // La exploración sigue activa (no se procesó)
        $ctx['exploracion']->refresh();
        $this->assertNull($ctx['exploracion']->regreso);
    }

    public function test_index_no_reaplica_auto_resolucion_si_no_hay_activas(): void
    {
        $this->bindHandlersDeterministas();

        // Solo exploración ya finalizada (no activa)
        $ctx = $this->crearContexto(['duracion_horas' => 2, 'inicio' => now()->subHours(3)]);
        $ctx['exploracion']->regreso = now()->subMinutes(5);
        $ctx['exploracion']->save();

        // No debe lanzar error
        $this->get('/exploraciones')->assertOk();
    }
}
