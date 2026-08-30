<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\PlayerInventory;
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
use Src\Exploraciones\App\FinalizarExploracionHandler;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\App\ProcesarExploracionCommand;
use Src\Exploraciones\App\ProcesarExploracionHandler;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Bus\UnitOfWork;
use Src\Shared\Domain\NivelHelper;
use Tests\TestCase;

class ExploracionesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $opciones
     * @return array{exploracion: ExploracionActiva, chainId: int, user: User, reclutado1: Reclutado, reclutado2: Reclutado}
     */
    private function crearContexto(array $opciones = []): array
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create(['id' => 1, 'name' => 'Bosque', 'province_id' => 1]);

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

        $pokemon2 = Pokemon::create([
            'id' => 2,
            'name' => 'charmander',
            'species_id' => 4,
            'capture_rate' => 255,
            'base_experience' => 62,
            'height' => 6,
            'weight' => 85,
            'hatch' => 10,
            'evolution_chain_id' => $chainId,
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

        $team = Team::create(['name' => 'Equipo Test', 'user_id' => $user->id]);

        $reclutado1 = Reclutado::create([
            'user_id' => $user->id,
            'pokemon_id' => 1,
            'nombre' => 'Bulbi',
            'exp' => ['total' => 0],
        ]);
        $reclutado2 = Reclutado::create([
            'user_id' => $user->id,
            'pokemon_id' => 2,
            'nombre' => 'Char',
            'exp' => ['total' => 0],
        ]);

        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado1->id, 'slot' => 1, 'behavior' => 'COMBATIENTE']);
        TeamMember::create(['team_id' => $team->id, 'pokemon_id' => $reclutado2->id, 'slot' => 2, 'behavior' => 'COMBATIENTE']);

        $exploracion = ExploracionActiva::create([
            'user_id' => $user->id,
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
            'chainId' => $chainId,
            'user' => $user,
            'reclutado1' => $reclutado1,
            'reclutado2' => $reclutado2,
        ];
    }

    /**
     * Vincula el handler de finalización con un proveedor de tirada de captura
     * determinista (seam de test). El bus resuelve el handler del container,
     * por lo que artisan('exploraciones:procesar') usará este aleatorio.
     */
    private function bindHandlerConAleatorio(callable $aleatorio): void
    {
        $this->app->instance(
            FinalizarExploracionHandler::class,
            new FinalizarExploracionHandler(
                app(UnitOfWork::class),
                app(CalculadorRecompensas::class),
                app(PersistirRecompensas::class),
                app(TransformadorResultadoExploracion::class),
                $aleatorio,
            ),
        );
    }

    /**
     * Vincula el handler del tick con un proveedor determinista (seam de test).
     * 0.3 → solo eventos "encuentro normal" → todas victorias → categoría exito
     * (multiplicador 1.0), para aserciones exactas sin depender del RNG.
     */
    private function bindProcesarConAleatorio(callable $aleatorio): void
    {
        $this->app->instance(
            ProcesarExploracionHandler::class,
            new ProcesarExploracionHandler(app(CommandBus::class), $aleatorio),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $bitacora
     * @return array<int, int>
     */
    private function conteoPorEspecie(array $bitacora): array
    {
        $conteos = [];
        foreach ($bitacora as $evento) {
            // Los encuentros resueltos (victoria) son los derrotados; los eventos
            // legacy 'pokemon' (sin resolucion) cuentan por retrocompat.
            if (in_array(($evento['tipo'] ?? null), ['encuentro', 'pokemon'], true)) {
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
        // Fuerza captura (aleatorio 0.0) para no depender del RNG: con la regla
        // cap-25 el capture_rate 255 ya no garantiza captura (máximo 9.8%).
        $this->bindHandlerConAleatorio(fn (): float => 0.0);
        // Tick determinista: solo encuentros normales (categoría exito, ×1.0).
        $this->bindProcesarConAleatorio(fn (): float => 0.3);

        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos['bitacora'];
        $derrotados = $ctx['exploracion']->eventos['derrotados'];
        $conteos = $this->conteoPorEspecie($bitacora);

        $this->assertNotEmpty($derrotados);
        $this->assertCount(count($derrotados), array_filter($bitacora, fn (array $e) => ($e['tipo'] ?? '') === 'encuentro'));

        // Pokedex: AVISTADO por cada especie derrotada
        foreach (array_keys($conteos) as $pokemonId) {
            $this->assertDatabaseHas('pokedex', ['pokemon_id' => $pokemonId, 'visto' => true]);
        }

        // Captura: capture_rate 255 → chance 1 → cada derrota genera un reclutable
        foreach ($conteos as $pokemonId => $cantidad) {
            $this->assertDatabaseHas('reclutables', ['pokemon_id' => $pokemonId, 'cantidad' => $cantidad]);
        }

        // Caramelos de familia: phase × count (bulbasaur phase 1, charmander phase 2),
        // persistidos en el inventario del dueño de la exploración.
        $familiaEsperada = ($conteos[1] ?? 0) * 1 + ($conteos[2] ?? 0) * 2;
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'familia:'.$ctx['chainId'],
            'cantidad' => $familiaEsperada,
        ]);

        // Caramelos EV: solo stats con effort > 0, con item_key 'ev:{stat}'
        $evEsperado = [
            1 => 2 * ($conteos[1] ?? 0),
            2 => 1 * ($conteos[2] ?? 0),
            3 => 1 * ($conteos[1] ?? 0),
        ];
        $filasEv = PlayerInventory::where('item_key', 'like', 'ev:%')->orderBy('item_key')->get();
        $this->assertCount(count(array_filter($evEsperado, fn (int $cantidad) => $cantidad > 0)), $filasEv);
        foreach ($evEsperado as $stat => $cantidad) {
            if ($cantidad > 0) {
                $this->assertDatabaseHas('player_inventory', [
                    'user_id' => $ctx['user']->id,
                    'item_key' => 'ev:'.$stat,
                    'cantidad' => $cantidad,
                ]);
            }
        }

        // EXP: user (nivel 5) recibe el 100 %; cada miembro floor((T×0.8)/3) por derrota.
        $expEsperado = ($conteos[1] ?? 0) * NivelHelper::expDerrota(64, 5)
            + ($conteos[2] ?? 0) * NivelHelper::expDerrota(62, 5);
        $expMiembroEsperado = ($conteos[1] ?? 0) * (int) floor(NivelHelper::expDerrota(64, 5) * 0.8 / 3)
            + ($conteos[2] ?? 0) * (int) floor(NivelHelper::expDerrota(62, 5) * 0.8 / 3);
        $this->assertSame(1_250 + $expEsperado, $ctx['user']->refresh()->experiencia);
        $this->assertSame($expMiembroEsperado, $ctx['reclutado1']->refresh()->exp->total());
        $this->assertSame($expMiembroEsperado, $ctx['reclutado2']->refresh()->exp->total());
    }

    public function test_indefinido_no_completa_hasta_recoger(): void
    {
        $ctx = $this->crearContexto(['indefinido' => true, 'inicio' => now()->subHours(3)]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $this->assertNull($ctx['exploracion']->regreso);
        $this->assertNotEmpty($ctx['exploracion']->eventos['bitacora']);

        // El recoger es una ruta autenticada: se pide como el dueño de la exploración.
        $this->actingAs($ctx['user'])->post("/exploraciones/{$ctx['exploracion']->id}/recoger")
            ->assertRedirect();

        $ctx['exploracion']->refresh();
        $this->assertNotNull($ctx['exploracion']->regreso);
        $this->assertNotEmpty($ctx['exploracion']->eventos['derrotados']);
    }

    public function test_recoger_responde_json_cuando_se_pide(): void
    {
        $ctx = $this->crearContexto(['indefinido' => true, 'inicio' => now()->subHours(3)]);

        $this->actingAs($ctx['user'])->postJson("/exploraciones/{$ctx['exploracion']->id}/recoger")
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

    public function test_tick_con_ultimo_procesado_hace_10_min_genera_tres_encuentros(): void
    {
        // Tras 10 min sin procesar, el tick divide 10 ÷ MINUTOS_POR_ENCUENTRO (3) = 3 encuentros.
        $ctx = $this->crearContexto(['indefinido' => true, 'inicio' => now()->subMinutes(30)]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [],
                'ultimo_procesado' => now()->subMinutes(10)->toIso8601String(),
            ],
        ]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos['bitacora'] ?? [];

        $this->assertCount(3, $bitacora);
    }

    public function test_exploracion_ya_completada_no_se_retoca(): void
    {
        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);
        $ctx['exploracion']->update(['regreso' => now(), 'eventos' => ['bitacora' => []]]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $this->assertSame([], $ctx['exploracion']->eventos['bitacora']);
        $this->assertDatabaseCount('player_inventory', 0);
    }

    public function test_hora_limite_pasada_completa_en_siguiente_tick(): void
    {
        // Hora fija para que now()->subHour() nunca cruce de día (ventana 00:00-01:00):
        // la hora_limite es el H:i de hoy a las 09:00 → hoy 09:00 < ahora 10:00 → completa.
        $this->travelTo(Carbon::parse('2026-08-28 10:00:00'));

        $ctx = $this->crearContexto([
            'hora_limite' => now()->subHour()->format('H:i'),
            'inicio' => now()->subHours(2),
        ]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $this->assertNotNull($ctx['exploracion']->refresh()->regreso);

        $this->travelBack();
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
        // El dueño no se resuelve (relación user → null; con la FK cascade no puede
        // faltar por la vía normal): nivel salvaje 1, exp a los miembros del equipo
        // y SIN caramelos/capturas (no hay dueño que los reciba), sin lanzar.
        $ctx = $this->crearExploracionParaFinalizar();
        $exploracion = $this->mockExploracionSinUsuario($ctx['exploracion']);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($exploracion));

        $this->assertNotNull($ctx['exploracion']->refresh()->regreso);

        // Nivel salvaje 1: exp de derrota del bulbasaur (base_experience 64) con nivel 1.
        // D3: cada integrante recibe floor((T×0.8)/3) = floor(12×0.8/3) = 3.
        $expPorMiembro = (int) floor(NivelHelper::expDerrota(64, 1) * 0.8 / 3);
        $this->assertSame($expPorMiembro, $ctx['reclutado1']->refresh()->exp->total());

        // El usuario no recibe exp ni caramelos (no hay dueño resuelto).
        $this->assertSame(1_250, $ctx['user']->refresh()->experiencia);
        $this->assertDatabaseCount('player_inventory', 0);
    }

    /**
     * Mock parcial que simula una exploración SIN dueño (relación user → null),
     * caso artificialmente imposible por la FK cascade (solo alcanzable si el
     * usuario desaparece a mitad de procesamiento).
     */
    private function mockExploracionSinUsuario(ExploracionActiva $real): ExploracionActiva
    {
        $mock = Mockery::mock(ExploracionActiva::class)->makePartial();
        $mock->shouldReceive('getAttribute')->with('user')->andReturn(null);
        $mock->shouldReceive('newQueryWithoutScopes')->andReturnUsing(fn (): mixed => $real->newQueryWithoutScopes());
        $mock->setRawAttributes($real->getAttributes());
        $mock->exists = true;

        return $mock;
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
        // Fuerza captura (aleatorio 0.0) para no depender del RNG (regla cap-25).
        $this->bindHandlerConAleatorio(fn (): float => 0.0);
        // Tick determinista: solo encuentros normales (categoría exito, ×1.0).
        $this->bindProcesarConAleatorio(fn (): float => 0.3);

        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $conteos = $this->conteoPorEspecie($ctx['exploracion']->eventos['bitacora']);
        $resultado = $ctx['exploracion']->eventos['resultado'];

        $this->assertIsArray($resultado);

        // Capturados: capture_rate 255 → chance 1 → todos los derrotados capturados
        $idsEsperados = array_keys($conteos);
        sort($idsEsperados);
        $idsCapturados = array_column($resultado['capturados'], 'pokemon_id');
        sort($idsCapturados);
        $this->assertSame($idsEsperados, $idsCapturados);
        foreach ($resultado['capturados'] as $capturado) {
            $this->assertSame($conteos[$capturado['pokemon_id']], $capturado['cantidad']);
        }

        // Caramelos de familia: una sola cadena, nombre base = bulbasaur, fase × conteo.
        // El pokemon_id del caramelo es el miembro de menor species_id (bulbasaur, species 1 < 4).
        // assertEquals: el orden de claves JSON de jsonb no es parte del contrato.
        $this->assertCount(1, $resultado['caramelos_familia']);
        $familia = $resultado['caramelos_familia'][0];
        $this->assertSame($ctx['chainId'], $familia['evolution_chain_id']);
        $this->assertSame('bulbasaur', $familia['nombre']);
        $this->assertSame(1, $familia['pokemon_id']);
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

    public function test_finalizacion_otorga_caramelos_de_tipo_desde_exp_tipada_d3(): void
    {
        // D3/RF-14: caramelos_tipo = floor((exp_tipo × 0.2)/100), con exp_tipo por
        // victoria (2 tipos → 50/50). Se sube la base_experience del charmander
        // para que la fórmula produzca caramelos no nulos.
        $ctx = $this->crearContexto(['duracion_horas' => 1, 'inicio' => now()->subHours(2)]);
        Pokemon::where('id', 2)->update(['base_experience' => 5000]);

        // Pool determinista: solo charmander (2 tipos) en el hábitat para que el
        // test no dependa del RNG de selección de especie.
        DB::table('pokemon_habitat')->where('pokemon_id', 1)->delete();

        PokemonType::create(['pokemon_id' => 2, 'type' => 13, 'slot' => 1]); // Eléctrico
        PokemonType::create(['pokemon_id' => 2, 'type' => 10, 'slot' => 2]); // Fuego

        // Tick determinista: solo encuentros normales (categoría exito, ×1.0).
        $this->bindProcesarConAleatorio(fn (): float => 0.3);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $ctx['exploracion']->refresh();
        $derrotados = $ctx['exploracion']->eventos['derrotados'];
        $conteos = $this->conteoPorEspecie($ctx['exploracion']->eventos['bitacora']);

        $this->assertNotEmpty($derrotados);
        $this->assertSame(0, $conteos[1] ?? 0);

        // T por derrota (nivel salvaje 5); 2 tipos → 50/50; caramelos = floor((T/2 × N × 0.2)/100).
        $T = NivelHelper::expDerrota(5000, 5);
        $expTipoPorDerrota = intdiv($T, 2);
        $caramelosEsperados = (int) floor($expTipoPorDerrota * ($conteos[2] ?? 0) * 0.2 / 100);

        $this->assertGreaterThan(0, $caramelosEsperados);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'tipo:electrico',
            'cantidad' => $caramelosEsperados,
        ]);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => $caramelosEsperados,
        ]);
        // Solo 2 filas de caramelos de TIPO (el inventario también tiene familia/EV
        // de la misma exploración: se filtra por item_key).
        $this->assertSame(2, PlayerInventory::where('item_key', 'like', 'tipo:%')->count());

        // Resumen en eventos: ksort por tipo (PHP 8.4: 'Eléctrico' < 'Fuego')
        $caramelosTipo = $ctx['exploracion']->eventos['resultado']['caramelos_tipo'];
        $this->assertSame(['Eléctrico', 'Fuego'], array_column($caramelosTipo, 'tipo'));
        $this->assertSame(['electrico', 'fuego'], array_column($caramelosTipo, 'slug'));
        foreach ($caramelosTipo as $caramelo) {
            $this->assertSame($caramelosEsperados, $caramelo['cantidad']);
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
        $this->assertDatabaseCount('player_inventory', 0);
        $this->assertNull($ctx['exploracion']->fresh()->regreso);
        // Los jobs post-commit NO se ejecutaron (no hubo commit)
        $this->assertDatabaseCount('pokedex', 0);
    }

    public function test_reintento_tras_fallo_no_duplica_recompensas(): void
    {
        // Fuerza NO captura (aleatorio 1.0): con la regla cap-25 el capture_rate 0
        // ya no garantiza ausencia de captura (usa la tasa base 25 → 9.8%).
        $this->bindHandlerConAleatorio(fn (): float => 1.0);

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

        // Caramelos de familia: fase 1 × 1 derrotado, UNA sola vez (no duplicado),
        // en el inventario del dueño de la exploración. El inventario puede tener
        // además EV/capturas: se filtra por item_key para la cuenta.
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'familia:'.$ctx['chainId'],
            'cantidad' => 1,
        ]);
        $this->assertSame(1, PlayerInventory::where('item_key', 'familia:'.$ctx['chainId'])->count());

        // capture_rate 0 → nunca captura → reclutables intactos
        $this->assertDatabaseCount('reclutables', 0);

        // Jobs post-commit ejecutados tras el commit del reintento
        $this->assertDatabaseHas('pokedex', ['pokemon_id' => 1, 'visto' => true]);
    }

    public function test_procesar_con_forzar_regreso_y_finalizar_fallando_hace_rollback_total(): void
    {
        // Bitacora vacía con ultimo_procesado hace 10 min: el tick GENERARÁ
        // encuentros (3) que deberán revertirse junto con las recompensas.
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
        $this->assertDatabaseCount('player_inventory', 0);
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
        $this->assertDatabaseCount('player_inventory', 0);
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

    public function test_finalizacion_con_chain_id_sin_tabla_evolution_chains_finaliza_ok(): void
    {
        // Regresión bug 23503: pokémon con evolution_chain_id 51 SIN fila en la tabla
        // evolution_chains (eliminada). La exploración debe finalizar correctamente:
        // caramelos_familia con evolution_chain_id = 51, pokemon_id = base (min species_id)
        // e inserto en caramelos SIN error de FK.
        $ctx = $this->crearContexto(['indefinido' => true]);
        // bulbasaur (species 1) y charmander (species 4) pasan a la cadena huérfana 51
        // (la cadena del contexto, 51, coincide: la fila de evolution_chains nunca existió).
        Pokemon::whereIn('id', [1, 2])->update(['evolution_chain_id' => 51]);

        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'pokemon', 'pokemon_id' => 1],
                    ['tipo' => 'pokemon', 'pokemon_id' => 1],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        $this->assertNotNull($ctx['exploracion']->fresh()->regreso);

        // Caramelos de familia: fase 1 (bulbasaur, min species_id 1) × 2 derrotas = 2
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'familia:51',
            'cantidad' => 2,
        ]);

        // Resultado: base = menor species_id de TODA la familia (bulbasaur, id 1).
        // assertEquals: el orden de claves JSON de jsonb no es parte del contrato.
        $resultado = $ctx['exploracion']->fresh()->eventos['resultado'];
        $this->assertEquals([
            [
                'evolution_chain_id' => 51,
                'nombre' => 'bulbasaur',
                'pokemon_id' => 1,
                'cantidad' => 2,
            ],
        ], $resultado['caramelos_familia']);
    }

    public function test_finalizacion_con_fase_dos_determinista_persiste_cantidad_por_fase(): void
    {
        // Bitácora controlada con 2 derrotas de charmander (id 2, species 4): en la cadena
        // {bulbasaur sp1, charmander sp4} charmander tiene fase 2 → caramelos = 2 × fase 2 = 4.
        // Si el mapa de miembros fallara (whereIn erróneo o select sin species_id) la fase
        // caería a 1 → cantidad 2 ≠ 4 → el test falla.
        $ctx = $this->crearContexto(['indefinido' => true]);
        // bulbasaur (species 1) y charmander (species 4) en la cadena 51 (la fila de
        // evolution_chains nunca existió).
        Pokemon::whereIn('id', [1, 2])->update(['evolution_chain_id' => 51]);

        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'pokemon', 'pokemon_id' => 2],
                    ['tipo' => 'pokemon', 'pokemon_id' => 2],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        $this->assertNotNull($ctx['exploracion']->fresh()->regreso);

        // Caramelos de familia: UNA fila para la cadena 51 con 2 derrotas × fase 2 = 4
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'familia:51',
            'cantidad' => 4,
        ]);
        $this->assertSame(1, PlayerInventory::where('item_key', 'familia:51')->count());

        // Resultado: base = menor species_id de TODA la familia (bulbasaur, id 1).
        // assertEquals: el orden de claves JSON de jsonb no es parte del contrato.
        $resultado = $ctx['exploracion']->fresh()->eventos['resultado'];
        $this->assertEquals([
            [
                'evolution_chain_id' => 51,
                'nombre' => 'bulbasaur',
                'pokemon_id' => 1,
                'cantidad' => 4,
            ],
        ], $resultado['caramelos_familia']);
    }

    // ==========================================
    // Iteración expediciones con riesgo (RF-05 a RF-14)
    // ==========================================

    public function test_retirada_por_desventaja_finaliza_y_conserva_recompensas(): void
    {
        // RF-09: retirada marca eventos['retirada'], finaliza anticipado y
        // conserva las recompensas ya obtenidas (multiplicador retirada = 1.0).
        $ctx = $this->crearContexto(['indefinido' => true]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'pokemon', 'pokemon_id' => 1], // victoria (retrocompat)
                    ['tipo' => 'retirada', 'resolucion' => 'retirada', 'reason' => 'grupo_enemigo', 'timestamp' => now()->toIso8601String()],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        $fresh = $ctx['exploracion']->fresh();
        $this->assertNotNull($fresh->regreso);
        $this->assertSame('retirada', $fresh->eventos->get('resultado')['resultado']);

        // La victoria previa se conserva: caramelo de familia fase 1 × 1.
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'familia:'.$ctx['chainId'],
            'cantidad' => 1,
        ]);
    }

    public function test_tick_con_equipo_debil_genera_retirada(): void
    {
        // Tick con aleatorio 0.15 → encuentro grupo; capacidad baja (< dificultad−30)
        // y roll < 0.5 → retirada → despacha FinalizarExploracionCommand.
        $ctx = $this->crearContexto(['indefinido' => true]);
        // Stats base mínimos (capacidad ~11) y rol sin bonus.
        PokemonStat::where('pokemon_id', 1)->update(['base_stat' => 1]);
        PokemonStat::where('pokemon_id', 2)->update(['base_stat' => 1]);
        TeamMember::where('team_id', $ctx['exploracion']->equipo_id)->update(['behavior' => 'RECOLECTOR']);

        $this->bindProcesarConAleatorio(fn (): float => 0.15);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $fresh = $ctx['exploracion']->fresh();
        $this->assertNotNull($fresh->regreso);
        $this->assertIsArray($fresh->eventos->get('retirada'));
        $this->assertSame('grupo_enemigo', $fresh->eventos->get('retirada')['reason']);
    }

    public function test_tick_acumula_tiempo_perdido_y_adelanta_ultimo_procesado(): void
    {
        // D2/RF-05: contratiempos (aleatorio 0.85 → bloqueo, sin vanguardia) acumulan
        // duration_loss y adelantan ultimo_procesado al futuro.
        $ctx = $this->crearContexto(['indefinido' => true, 'inicio' => now()->subMinutes(30)]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [],
                'ultimo_procesado' => now()->subMinutes(10)->toIso8601String(),
            ],
        ]);

        $this->bindProcesarConAleatorio(fn (): float => 0.85);

        $this->artisan('exploraciones:procesar')->assertSuccessful();

        $fresh = $ctx['exploracion']->fresh();
        $eventos = $fresh->eventos;
        $perdido = (int) $eventos->get('tiempo_perdido', 0);
        $this->assertGreaterThan(0, $perdido);

        // 10 min ÷ 3 = 3 slots; cada bloqueo cuesta 15 min → 45.
        $this->assertSame(45, $perdido);

        // ultimo_procesado = hasta + tiempo perdido (en el futuro).
        $ultimo = Carbon::parse($eventos->get('ultimo_procesado'));
        $this->assertTrue($ultimo->greaterThan(now()));

        // duration_real en el resultado final: nominal (30 min) − tiempo perdido → 0 (mín).
        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($fresh));
        $resultado = $fresh->refresh()->eventos->get('resultado');
        $this->assertSame(0, $resultado['duration_real']);
        $this->assertSame(45, $resultado['tiempo_perdido']);
        $this->assertSame(3, $resultado['incidentes']['contratiempos']);
    }

    public function test_hallazgos_otorgan_caramelos_de_familia_ev_y_tipo(): void
    {
        // D8: los eventos hallazgo entregan caramelos (familia/EV/tipo).
        $ctx = $this->crearContexto(['indefinido' => true]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 1, 'cantidad' => 2, 'timestamp' => now()->toIso8601String()],
                    ['tipo' => 'hallazgo', 'subtype' => 'caramelo_ev', 'stat' => 2, 'cantidad' => 1, 'timestamp' => now()->toIso8601String()],
                    ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'tipo_id' => 10, 'cantidad' => 1, 'timestamp' => now()->toIso8601String()],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'familia:'.$ctx['chainId'],
            'cantidad' => 2,
        ]);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'ev:2',
            'cantidad' => 1,
        ]);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => 1,
        ]);
    }

    public function test_emboscada_pokemon_ids_generan_avistados_aunque_no_derrotados(): void
    {
        // RF-07: los pokemon_ids de una emboscada son AVISTADOS; solo las
        // resoluciones victoria (o sin resolución) cuentan como derrotados.
        $ctx = $this->crearContexto(['indefinido' => true]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'emboscada', 'pokemon_ids' => [1, 2], 'resolucion' => 'superada', 'duration_loss' => 10, 'timestamp' => now()->toIso8601String()],
                    ['tipo' => 'pokemon', 'pokemon_id' => 1, 'timestamp' => now()->toIso8601String()],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        // Avistados: emboscada (1 y 2) + encuentro (1).
        $this->assertDatabaseHas('pokedex', ['pokemon_id' => 1, 'visto' => true]);
        $this->assertDatabaseHas('pokedex', ['pokemon_id' => 2, 'visto' => true]);

        // Derrotados: solo la victoria del encuentro → caramelo familia fase 1 × 1.
        $fresh = $ctx['exploracion']->fresh();
        $this->assertSame([1], $fresh->eventos->get('derrotados'));
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'familia:'.$ctx['chainId'],
            'cantidad' => 1,
        ]);
    }

    public function test_retrocompat_encuentro_sin_resolucion_es_victoria(): void
    {
        // RF-07: evento 'encuentro' sin resolucion (bitácoras antiguas) = victoria.
        $ctx = $this->crearContexto(['indefinido' => true]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'encuentro', 'subtype' => 'normal', 'pokemon_id' => 1, 'timestamp' => now()->toIso8601String()],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        $fresh = $ctx['exploracion']->fresh();
        $this->assertSame([1], $fresh->eventos->get('derrotados'));
        $this->assertDatabaseHas('pokedex', ['pokemon_id' => 1, 'visto' => true]);
        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $ctx['user']->id,
            'item_key' => 'familia:'.$ctx['chainId'],
            'cantidad' => 1,
        ]);
    }

    public function test_resultado_aditivo_incluye_categoria_duracion_e_incidentes(): void
    {
        // RF-10: contrato aditivo del resultado.
        $ctx = $this->crearContexto(['indefinido' => true]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'encuentro', 'subtype' => 'normal', 'pokemon_id' => 1, 'resolucion' => 'victoria', 'timestamp' => now()->toIso8601String()],
                    ['tipo' => 'contratiempo', 'subtype' => 'clima', 'resolucion' => 'mitigado', 'duration_loss' => 10, 'timestamp' => now()->toIso8601String()],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        $resultado = $ctx['exploracion']->fresh()->eventos->get('resultado');
        $this->assertSame('exito', $resultado['resultado']);
        $this->assertArrayHasKey('duration_real', $resultado);
        $this->assertArrayHasKey('tiempo_perdido', $resultado);
        // assertEquals: el orden de claves JSON de jsonb no es parte del contrato.
        $this->assertEquals(['encuentros' => 1, 'victorias' => 1, 'huidas' => 0, 'emboscadas' => 0, 'contratiempos' => 1], $resultado['incidentes']);
    }

    public function test_categoria_fracaso_aplica_multiplicador_reducido(): void
    {
        // RF-08: fracaso (0.25) reduce exp y caramelos (floor).
        $ctx = $this->crearContexto(['indefinido' => true]);
        Pokemon::where('id', 1)->update(['base_experience' => 5000]);
        $ctx['exploracion']->update([
            'eventos' => [
                'bitacora' => [
                    ['tipo' => 'encuentro', 'subtype' => 'normal', 'pokemon_id' => 1, 'resolucion' => 'victoria', 'timestamp' => now()->toIso8601String()],
                    ['tipo' => 'encuentro', 'subtype' => 'normal', 'pokemon_id' => 1, 'resolucion' => 'derrota', 'duration_loss' => 10, 'timestamp' => now()->toIso8601String()],
                    ['tipo' => 'encuentro', 'subtype' => 'normal', 'pokemon_id' => 1, 'resolucion' => 'derrota', 'duration_loss' => 10, 'timestamp' => now()->toIso8601String()],
                    ['tipo' => 'encuentro', 'subtype' => 'normal', 'pokemon_id' => 1, 'resolucion' => 'derrota', 'duration_loss' => 10, 'timestamp' => now()->toIso8601String()],
                ],
                'ultimo_procesado' => now()->toIso8601String(),
            ],
        ]);

        app(CommandBus::class)->dispatch(new FinalizarExploracionCommand($ctx['exploracion']));

        $resultado = $ctx['exploracion']->fresh()->eventos->get('resultado');
        $this->assertSame('fracaso', $resultado['resultado']);

        // T = expDerrota(5000, 5) = 5000; 1 derrota × 0.25 = 1250 exp.
        $this->assertSame(1250, $resultado['exp']);
        $this->assertSame(1_250 + 1250, $ctx['user']->refresh()->experiencia);
    }
}
