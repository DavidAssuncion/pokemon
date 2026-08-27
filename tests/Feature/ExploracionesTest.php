<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CarameloEv;
use App\Models\EvolutionChain;
use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Src\Exploraciones\App\FinalizarExploracionCommand;
use Src\Exploraciones\App\ProcesarExploracionCommand;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Domain\NivelHelper;
use Tests\TestCase;

class ExploracionesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $opciones
     * @return array{exploracion: ExploracionActiva, chain: EvolutionChain, user: User, reclutado1: Reclutado, reclutado2: Reclutado}
     */
    private function crearContexto(array $opciones = []): array
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);

        $chain = EvolutionChain::create(['data' => '{"stages": 3}']);

        $pokemon1 = Pokemon::create([
            'id' => 1,
            'name' => 'bulbasaur',
            'species_id' => 1,
            'capture_rate' => 255,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'hatch' => 10,
            'evolution_chain_id' => $chain->id,
        ]);

        $pokemon2 = Pokemon::create([
            'id' => 2,
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 255,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
            'hatch' => 10,
            'evolution_chain_id' => $chain->id,
        ]);

        DB::table('pokemon_habitat')->insert([
            ['pokemon_id' => 1, 'habitat_id' => 1, 'level' => 1],
            ['pokemon_id' => 2, 'habitat_id' => 1, 'level' => 1],
        ]);

        PokemonStat::create(['pokemon_id' => 1, 'stat' => 1, 'base_stat' => 45, 'effort' => 2]);
        PokemonStat::create(['pokemon_id' => 1, 'stat' => 2, 'base_stat' => 49, 'effort' => 0]);
        PokemonStat::create(['pokemon_id' => 1, 'stat' => 3, 'base_stat' => 49, 'effort' => 1]);
        PokemonStat::create(['pokemon_id' => 2, 'stat' => 2, 'base_stat' => 52, 'effort' => 1]);
        PokemonStat::create(['pokemon_id' => 2, 'stat' => 4, 'base_stat' => 60, 'effort' => 0]);

        $user = User::factory()->create(['experiencia' => 1_250]); // nivel 5 (10 × 5³)

        $team = Team::create(['name' => 'Equipo Test']);

        $reclutado1 = Reclutado::create([
            'pokemon_id' => 1,
            'nombre' => 'Bulbi',
            'exp' => ['total' => 0],
        ]);
        $reclutado2 = Reclutado::create([
            'pokemon_id' => 2,
            'nombre' => 'Char',
            'exp' => ['total' => 0],
        ]);

        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado1->id, 'slot' => 1, 'behavior' => 'COMBATIENTE']);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);

        $exploracion = ExploracionActiva::create([
            'equipo_id' => $team->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => $opciones['duracion_horas'] ?? null,
            'hora_limite' => $opciones['hora_limite'] ?? null,
            'indefinido' => $opciones['indefinido'] ?? false,
            'inicio_exploracion' => $opciones['inicio'] ?? now()->subMinutes(30),
        ]);

        return [
            'exploracion' => $exploracion,
            'chain' => $chain,
            'user' => $user,
            'reclutado1' => $reclutado1,
            'reclutado2' => $reclutado2,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $bitacora
     * @return array<int, int>
     */
    private function conteoPorEspecie(array $bitacora): array
    {
        $conteos = [];
        foreach ($bitacora as $evento) {
            if (($evento['tipo'] ?? null) === 'pokemon') {
                $id = (int) $evento['pokemon_id'];
                $conteos[$id] = ($conteos[$id] ?? 0) + 1;
            }
        }

        return $conteos;
    }

    public function test_comando_marca_regreso_tras_pasar_la_duracion(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $this->assertNotNull($ctx['exploracion']->regreso);
        $this->assertNotEmpty($ctx['exploracion']->eventos['bitacora']);
        $this->assertNotEmpty($ctx['exploracion']->eventos['derrotados']);
    }

    public function test_comando_no_cierra_dentro_de_la_duracion(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 4, 'inicio' => now()->subHour()]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $this->assertNull($ctx['exploracion']->regreso);
        $this->assertNotEmpty($ctx['exploracion']->eventos['bitacora']);
        $this->assertEmpty($ctx['exploracion']->eventos['derrotados'] ?? []);
    }

    public function test_encuentros_se_detienen_en_inicio_vuelta(): void
    {
        $inicio = now()->subHours(2);
        $ctx = $this->crearContexto(['duracion_horas' => 4, 'inicio' => $inicio]);
        // fin = inicio + 4h, vuelta = fin - 1h = inicio + 3h (dentro de 1h)

        $this->artisan('exploraciones:procesar')->assertSuccessful();
        $ctx['exploracion']->refresh();
        $this->assertNull($ctx['exploracion']->regreso);

        $vuelta = $inicio->copy()->addHours(3);
        $this->travelTo($vuelta->copy()->addHour()); // ahora = fin de exploración

        $this->artisan('exploraciones:procesar')->assertSuccessful();
        $ctx['exploracion']->refresh();
        $this->assertNotNull($ctx['exploracion']->regreso);

        /** @var array<int, array<string, mixed>> $bitacora */
        $bitacora = $ctx['exploracion']->eventos['bitacora'];
        $timestamps = collect($bitacora)->map(
            fn (array $evento) => Carbon::parse($evento['timestamp'])
        );
        $this->assertTrue($timestamps->every(fn (Carbon $ts) => $ts->lessThanOrEqualTo($vuelta)));

        $this->travelBack();
    }

    public function test_servicio_reparte_todas_las_recompensas(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos['bitacora'];
        $derrotados = $ctx['exploracion']->eventos['derrotados'];
        $conteos = $this->conteoPorEspecie($bitacora);

        $this->assertNotEmpty($derrotados);
        $this->assertCount(count($derrotados), array_filter($bitacora, fn (array $e) => ($e['tipo'] ?? '') === 'pokemon'));

        // Pokedex: AVISTADO por cada especie derrotada
        foreach (array_keys($conteos) as $pokemonId) {
            $this->assertDatabaseHas('pokedex', ['pokemon_id' => $pokemonId, 'visto' => true]);
        }

        // Captura: capture_rate 255 → chance 1 → cada derrota genera un reclutable
        foreach ($conteos as $pokemonId => $cantidad) {
            $this->assertDatabaseHas('reclutables', ['pokemon_id' => $pokemonId, 'cantidad' => $cantidad]);
        }

        // Caramelos de familia: phase × count (bulbasaur phase 1, charmander phase 2)
        $familiaEsperada = ($conteos[1] ?? 0) * 1 + ($conteos[2] ?? 0) * 2;
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => $ctx['chain']->id,
            'cantidad' => $familiaEsperada,
        ]);

        // Caramelos EV: solo stats con effort > 0
        $evEsperado = [
            1 => 2 * ($conteos[1] ?? 0),
            2 => 1 * ($conteos[2] ?? 0),
            3 => 1 * ($conteos[1] ?? 0),
        ];
        $filasEv = CarameloEv::orderBy('stat')->get();
        $this->assertCount(count(array_filter($evEsperado, fn (int $cantidad) => $cantidad > 0)), $filasEv);
        foreach ($evEsperado as $stat => $cantidad) {
            if ($cantidad > 0) {
                $this->assertDatabaseHas('caramelos_ev', ['stat' => $stat, 'cantidad' => $cantidad]);
            }
        }

        // EXP: user (nivel 5) + cada miembro del equipo con el total completo
        $expEsperado = ($conteos[1] ?? 0) * NivelHelper::expDerrota(64, 5)
            + ($conteos[2] ?? 0) * NivelHelper::expDerrota(62, 5);
        $this->assertSame(1_250 + $expEsperado, $ctx['user']->refresh()->experiencia);
        $this->assertSame($expEsperado, $ctx['reclutado1']->refresh()->exp['total']);
        $this->assertSame($expEsperado, $ctx['reclutado2']->refresh()->exp['total']);
    }

    public function test_indefinido_no_completa_hasta_recoger(): void
    {
        $ctx = $this->crearContexto(['indefinido' => true, 'inicio' => now()->subHours(3)]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $this->assertNull($ctx['exploracion']->regreso);
        $this->assertNotEmpty($ctx['exploracion']->eventos['bitacora']);

        $this->post("/exploraciones/{$ctx['exploracion']->id}/recoger")->assertRedirect();

        $ctx['exploracion']->refresh();
        $this->assertNotNull($ctx['exploracion']->regreso);
        $this->assertNotEmpty($ctx['exploracion']->eventos['derrotados']);
    }

    public function test_recoger_responde_json_cuando_se_pide(): void
    {
        $ctx = $this->crearContexto(['indefinido' => true, 'inicio' => now()->subHours(3)]);

        $this->postJson("/exploraciones/{$ctx['exploracion']->id}/recoger")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($ctx['exploracion']->refresh()->regreso);
    }

    public function test_ticks_repetidos_no_duplican_encuentros(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 4, 'inicio' => now()->subHour()]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();
        $primeraPasada = count($ctx['exploracion']->refresh()->eventos['bitacora']);

        $this->artisan('exploraciones:procesar')->assertSuccessful();
        $segundaPasada = count($ctx['exploracion']->refresh()->eventos['bitacora']);

        $this->assertSame($primeraPasada, $segundaPasada);
    }

    public function test_exploracion_ya_completada_no_se_retoca(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);
        $ctx['exploracion']->update(['regreso' => now(), 'eventos' => ['bitacora' => []]]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $this->assertSame([], $ctx['exploracion']->eventos['bitacora']);
        $this->assertDatabaseCount('caramelos', 0);
        $this->assertDatabaseCount('caramelos_ev', 0);
    }

    public function test_hora_limite_pasada_completa_en_siguiente_tick(): void
    {
        $ctx = $this->crearContexto([
            'hora_limite' => now()->subHour()->format('H:i'),
            'inicio' => now()->subHours(2),
        ]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $this->assertNotNull($ctx['exploracion']->refresh()->regreso);
    }

    public function test_hora_limite_futura_no_completa(): void
    {
        $this->travelTo(Carbon::parse('2026-08-28 10:00:00'));

        $ctx = $this->crearContexto([
            'hora_limite' => now()->addHours(2)->format('H:i'),
            'inicio' => now()->subMinutes(30),
        ]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $this->assertNull($ctx['exploracion']->refresh()->regreso);

        $this->travelBack();
    }

    public function test_sin_usuario_usa_nivel_uno_y_no_falla(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);
        User::query()->delete();

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $this->assertNotNull($ctx['exploracion']->regreso);

        $conteos = $this->conteoPorEspecie($ctx['exploracion']->eventos['bitacora']);
        $expEsperado = ($conteos[1] ?? 0) * NivelHelper::expDerrota(64, 1)
            + ($conteos[2] ?? 0) * NivelHelper::expDerrota(62, 1);
        $this->assertSame($expEsperado, $ctx['reclutado1']->refresh()->exp['total']);
    }

    public function test_nivel_de_usuario_deriva_de_la_experiencia(): void
    {
        $user = User::factory()->create(['experiencia' => 1_250]);

        $this->assertSame(5, $user->nivel());
        $this->assertSame(1, User::factory()->create(['experiencia' => 0])->nivel());
    }

    public function test_comando_sin_exploraciones_activas_termina_ok(): void
    {
        $this->artisan('exploraciones:procesar')->assertSuccessful();
    }

    public function test_comando_procesa_sincronamente_sin_worker(): void
    {
        // Reproduce la configuración de producción: QUEUE_CONNECTION=database sin worker.
        // Si el comando volviera a despachar un job, este quedaría en `jobs` y la bitácora vacía.
        config()->set('queue.default', 'database');

        $ctx = $this->crearContexto(['duracion_horas' => 4, 'inicio' => now()->subHour()]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        // Sin worker no debe quedar NINGÚN job en la cola.
        $this->assertDatabaseCount('jobs', 0);

        // La bitácora se genera síncronamente, sin depender de un worker.
        $ctx['exploracion']->refresh();
        $this->assertNotEmpty($ctx['exploracion']->eventos['bitacora']);
    }

    public function test_servicio_guarda_resumen_de_resultado_en_eventos(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $conteos = $this->conteoPorEspecie($ctx['exploracion']->eventos['bitacora']);
        $resultado = $ctx['exploracion']->eventos['resultado'];

        $this->assertIsArray($resultado);

        // Avistados: todas las especies derrotadas, ordenadas por pokemon_id
        $idsAvistados = array_column($resultado['avistados'], 'pokemon_id');
        $idsEsperados = array_keys($conteos);
        sort($idsAvistados);
        sort($idsEsperados);
        $this->assertSame($idsEsperados, $idsAvistados);
        foreach ($resultado['avistados'] as $avistado) {
            $this->assertSame(
                Pokemon::findOrFail($avistado['pokemon_id'])->name,
                $avistado['nombre'],
            );
        }

        // Capturados: capture_rate 255 → chance 1 → todos los derrotados capturados
        $idsCapturados = array_column($resultado['capturados'], 'pokemon_id');
        sort($idsCapturados);
        $this->assertSame($idsEsperados, $idsCapturados);
        foreach ($resultado['capturados'] as $capturado) {
            $this->assertSame($conteos[$capturado['pokemon_id']], $capturado['cantidad']);
        }

        // Caramelos de familia: una sola cadena, nombre base = bulbasaur, fase × conteo
        $this->assertCount(1, $resultado['caramelos_familia']);
        $familia = $resultado['caramelos_familia'][0];
        $this->assertSame($ctx['chain']->id, $familia['evolution_chain_id']);
        $this->assertSame('bulbasaur', $familia['nombre']);
        $this->assertSame(
            ($conteos[1] ?? 0) * 1 + ($conteos[2] ?? 0) * 2,
            $familia['cantidad'],
        );

        // Caramelos EV: solo stats con effort > 0, agrupados por stat
        $evEsperado = [
            1 => 2 * ($conteos[1] ?? 0),
            2 => 1 * ($conteos[2] ?? 0),
            3 => 1 * ($conteos[1] ?? 0),
        ];
        $evEsperado = array_filter($evEsperado, fn (int $cantidad) => $cantidad > 0);
        $this->assertSame(
            array_keys($evEsperado),
            array_column($resultado['caramelos_ev'], 'stat'),
        );
        foreach ($resultado['caramelos_ev'] as $caramelo) {
            $this->assertSame($evEsperado[$caramelo['stat']], $caramelo['cantidad']);
        }

        // EXP: mismo total que el incremento del usuario (nivel 5)
        $expEsperado = ($conteos[1] ?? 0) * NivelHelper::expDerrota(64, 5)
            + ($conteos[2] ?? 0) * NivelHelper::expDerrota(62, 5);
        $this->assertSame($expEsperado, $resultado['exp']);
    }

    public function test_finalizacion_otorga_caramelos_de_tipo_por_derrotado(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);

        // Pool determinista: solo charmander (2 tipos) en el hábitat para que el
        // test no dependa del RNG de selección de especie.
        DB::table('pokemon_habitat')->where('pokemon_id', 1)->delete();

        PokemonType::create(['pokemon_id' => 2, 'type' => 13, 'slot' => 1]); // Eléctrico
        PokemonType::create(['pokemon_id' => 2, 'type' => 10, 'slot' => 2]); // Fuego

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $derrotados = $ctx['exploracion']->eventos['derrotados'];
        $conteos = $this->conteoPorEspecie($ctx['exploracion']->eventos['bitacora']);

        $this->assertNotEmpty($derrotados);
        $this->assertSame(0, $conteos[1] ?? 0);

        // 1 caramelo por cada tipo del pokemon derrotado, por derrota
        $this->assertDatabaseHas('caramelos_tipo', ['tipo' => 'Eléctrico', 'cantidad' => $conteos[2]]);
        $this->assertDatabaseHas('caramelos_tipo', ['tipo' => 'Fuego', 'cantidad' => $conteos[2]]);
        $this->assertDatabaseCount('caramelos_tipo', 2);

        // Resumen en eventos: ksort por tipo (PHP 8.4: 'Eléctrico' < 'Fuego')
        $caramelosTipo = $ctx['exploracion']->eventos['resultado']['caramelos_tipo'];
        $this->assertSame(['Eléctrico', 'Fuego'], array_column($caramelosTipo, 'tipo'));
        $this->assertSame(['electrico', 'fuego'], array_column($caramelosTipo, 'slug'));
        foreach ($caramelosTipo as $caramelo) {
            $this->assertSame($conteos[2], $caramelo['cantidad']);
        }
    }

    // ==========================================
    // CommandBus: rollback transaccional de FinalizarExploracion
    // ==========================================

    /**
     * Crea una exploración con bitacora CONTROLADA (1 derrotado determinista)
     * lista para finalizar sin depender del tick/RNG.
     */
    private function crearExploracionParaFinalizar(int $captureRate = 255): array
    {
        $ctx = $this->crearContexto(['indefinido' => true]);

        if ($captureRate !== 255) {
            Pokemon::where('id', 1)->update(['capture_rate' => $captureRate]);
        }

        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [['tipo' => 'pokemon', 'pokemon_id' => 1]],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        return $ctx;
    }

    /**
     * Mock de clase de ExploracionActiva (no final) que delega al modelo real
     * todo excepto el update que marca `regreso` (lanza a mitad del flujo,
     * tras persistir recompensas). Compatible con el refresh() del handler.
     */
    private function mockExploracionQueFallaAlMarcarRegreso(ExploracionActiva $real): ExploracionActiva
    {
        $mock = Mockery::mock(ExploracionActiva::class)->makePartial();
        $mock->shouldReceive('update')->andReturnUsing(
            function (array $attributes) use ($real): bool {
                if (array_key_exists('regreso', $attributes)) {
                    throw new RuntimeException('fallo forzado al marcar regreso');
                }

                return $real->update($attributes);
            }
        );
        $mock->shouldReceive('newQueryWithoutScopes')->andReturnUsing(fn (): mixed => $real->newQueryWithoutScopes());
        $mock->setRawAttributes($real->getAttributes());
        $mock->exists = true;

        return $mock;
    }

    public function test_finalizar_fallo_a_mitad_hace_rollback_total(): void
    {
        $ctx = $this->crearExploracionParaFinalizar();
        $bus = app(CommandBus::class);
        $exploracion = $this->mockExploracionQueFallaAlMarcarRegreso($ctx['exploracion']);

        try {
            $bus->dispatch(new FinalizarExploracionCommand($exploracion));
            $this->fail('Se esperaba una excepción');
        } catch (RuntimeException) {
            // esperado: fallo A MITAD, tras persistir las recompensas reales
        }

        // Rollback total: nada de lo escrito antes del fallo queda persistido
        $this->assertDatabaseCount('reclutables', 0);
        $this->assertDatabaseCount('caramelos', 0);
        $this->assertDatabaseCount('caramelos_ev', 0);
        $this->assertDatabaseCount('caramelos_tipo', 0);
        $this->assertNull($ctx['exploracion']->fresh()->regreso);
        // Los jobs post-commit NO se ejecutaron (no hubo commit)
        $this->assertDatabaseCount('pokedex', 0);
    }

    public function test_reintento_tras_fallo_no_duplica_recompensas(): void
    {
        $ctx = $this->crearExploracionParaFinalizar(captureRate: 0);
        $bus = app(CommandBus::class);
        $exploracion = $this->mockExploracionQueFallaAlMarcarRegreso($ctx['exploracion']);

        try {
            $bus->dispatch(new FinalizarExploracionCommand($exploracion));
            $this->fail('Se esperaba una excepción en la primera llamada');
        } catch (RuntimeException) {
            // esperado
        }

        // Reintento con el modelo real: las recompensas NO se duplican
        $bus->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        $exploracionFinal = $ctx['exploracion']->fresh();
        $this->assertNotNull($exploracionFinal->regreso);

        // Caramelos de familia: fase 1 × 1 derrotado, UNA sola vez (no duplicado)
        $this->assertDatabaseHas('caramelos', [
            'evolution_chain_id' => $ctx['chain']->id,
            'cantidad' => 1,
        ]);
        $this->assertDatabaseCount('caramelos', 1);

        // capture_rate 0 → nunca captura → reclutables intactos
        $this->assertDatabaseCount('reclutables', 0);

        // Jobs post-commit ejecutados tras el commit del reintento
        $this->assertDatabaseHas('pokedex', ['pokemon_id' => 1, 'visto' => true]);
    }

    public function test_procesar_con_forzar_regreso_y_finalizar_fallando_hace_rollback_total(): void
    {
        // Bitacora vacía con ultimo_procesado hace 10 min: el tick GENERARÁ
        // encuentros (2) que deberán revertirse junto con las recompensas.
        $ctx = $this->crearContexto(['indefinido' => true, 'inicio' => now()->subMinutes(30)]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [],
                'ultimo_procesado' => now()->subMinutes(10)->toIso8601String(),
            ],
        ]);

        $exploracion = $this->mockExploracionQueFallaAlMarcarRegreso($ctx['exploracion']);

        try {
            app(CommandBus::class)->dispatch(new ProcesarExploracionCommand($exploracion, forzarRegreso: true));
            $this->fail('Se esperaba una excepción');
        } catch (RuntimeException) {
            // esperado: el Finalizar (anidado) persiste recompensas y lanza al marcar regreso
        }

        // Rollback TOTAL del dispatch anidado: ni el tick ni las recompensas persisten
        $exploracionFinal = $ctx['exploracion']->fresh();
        $this->assertSame([], $exploracionFinal->eventos['bitacora'] ?? []);
        $this->assertNull($exploracionFinal->regreso);
        $this->assertDatabaseCount('reclutables', 0);
        $this->assertDatabaseCount('caramelos', 0);
        $this->assertDatabaseCount('caramelos_ev', 0);
        $this->assertDatabaseCount('caramelos_tipo', 0);
        $this->assertDatabaseCount('pokedex', 0);
    }

    public function test_finalizar_exploracion_ya_finalizada_es_no_op(): void
    {
        $ctx = $this->crearExploracionParaFinalizar();
        // Se marca regreso en DB (el modelo en memoria queda stale)
        DB::table('exploraciones_activas')
            ->where('id', $ctx['exploracion']->id)
            ->update(['regreso' => now()]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        // No-op: no se duplican recompensas
        $this->assertDatabaseCount('reclutables', 0);
        $this->assertDatabaseCount('caramelos', 0);
        $this->assertDatabaseCount('pokedex', 0);
    }

    public function test_jobs_pokedex_se_ejecutan_tras_el_commit(): void
    {
        $ctx = $this->crearExploracionParaFinalizar();

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        // QUEUE_CONNECTION=sync + afterCommit: el job corre DESPUÉS del commit
        $this->assertDatabaseHas('pokedex', ['pokemon_id' => 1, 'visto' => true]);
        $this->assertNotNull($ctx['exploracion']->fresh()->regreso);
    }
}
