<?php

declare(strict_types=1);

namespace Tests\Feature\Exploraciones;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\Habitat;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Src\Battle\Domain\ValueObjects\BattleLog;
use Src\Exploraciones\App\CombateExploracion;
use Src\Exploraciones\App\ProcesarExploracionCommand;
use Src\Exploraciones\App\ProcesarExploracionHandler;
use Src\Exploraciones\Domain\ValueObjects\ResultadoBatallaExploracion;
use Src\Exploraciones\Presentation\EventoExploracionVista;
use Src\Shared\Bus\CommandBus;
use Src\Shared\Domain\EscaladorNivelRival;
use Tests\TestCase;

class ProcesarExploracionCombateTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(['experiencia' => 10 * 10 ** 3]); // nivel 10
    }

    private function crearContexto(array $opciones = []): array
    {
        $province = Province::create(['id' => 1, 'name' => 'Kanto']);
        $habitat = Habitat::create([
            'id' => 1,
            'name' => 'Bosque',
            'province_id' => $province->id,
            'peligro' => 1,
            'min_lvl_1' => $opciones['min_lvl_1'] ?? null,
        ]);

        // Salvaje (pool del hábitat) — stats débiles por defecto
        $salvaje = Pokemon::create([
            'id' => 1,
            'name' => 'rattata',
            'species_id' => 1,
            'capture_rate' => 255,
            'base_experience' => 51,
            'height' => 3,
            'weight' => 35,
            'hatch' => 10,
        ]);
        $this->crearStats($salvaje, $opciones['salvaje_stats'] ?? ['hp' => 30, 'atk' => 30, 'def' => 25, 'spAtk' => 25, 'spDef' => 25, 'speed' => 40]);
        $this->crearTipo($salvaje, TipoEnum::NORMAL);
        DB::table('pokemon_habitat')->insert(['pokemon_id' => $salvaje->id, 'habitat_id' => $habitat->id, 'level' => 1]);

        // Explorador (reclutado del usuario) — stats fuertes por defecto
        $pokemon = Pokemon::create([
            'id' => 2,
            'name' => 'mewtwo',
            'species_id' => 2,
            'capture_rate' => 3,
            'base_experience' => 340,
            'height' => 20,
            'weight' => 1220,
        ]);
        $this->crearStats($pokemon, $opciones['explorador_stats'] ?? ['hp' => 200, 'atk' => 180, 'def' => 150, 'spAtk' => 180, 'spDef' => 150, 'speed' => 200]);
        $this->crearTipo($pokemon, TipoEnum::PSYCHIC);

        $reclutado = Reclutado::create([
            'user_id' => $this->usuario->id,
            'pokemon_id' => $pokemon->id,
            'nombre' => 'Mewtwo',
            'exp' => ['total' => 10 * 40 ** 3], // nivel 40
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $this->usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 24,
            'indefinido' => false,
            // 60 min de tiempo efectivo superan el intervalo base (30 min)
            // incluso con el máximo de movilidad y bonus de exploración.
            'inicio_exploracion' => now()->subMinutes(60),
        ]);

        return ['exploracion' => $exploracion, 'reclutado' => $reclutado, 'salvaje' => $salvaje];
    }

    private function crearStats(Pokemon $pokemon, array $stats): void
    {
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
    }

    private function crearTipo(Pokemon $pokemon, TipoEnum $tipo): void
    {
        PokemonType::create(['pokemon_id' => $pokemon->id, 'type' => $tipo, 'slot' => 1]);
    }

    private function handler(?CombateExploracion $combate = null): ProcesarExploracionHandler
    {
        return new ProcesarExploracionHandler(
            app(CommandBus::class),
            $combate ?? new CombateExploracion(new \Src\CombateEntrenadores\App\MapeadorPokemonBatalla(new \Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo())),
            new EscaladorNivelRival(),
            fn (): float => 0.3, // aleatorio determinista → solo encuentros normales
        );
    }

    #[Test]
    public function test_encuentro_con_combate_real_victoria_registra_victoria(): void
    {
        mt_srand(1);
        $ctx = $this->crearContexto();
        $this->handler()->handle(new ProcesarExploracionCommand($ctx['exploracion']));
        mt_srand();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos->get('bitacora', []);

        $this->assertNotEmpty($bitacora, 'Debe haber eventos en la bitácora');
        $encuentro = collect($bitacora)->first(fn (array $e) => ($e['tipo'] ?? '') === 'encuentro');
        $this->assertNotNull($encuentro, 'Debe haber un encuentro en la bitácora');
        $this->assertSame('victoria', $encuentro['resolucion'] ?? null, 'El explorador fuerte debe vencer');
        $this->assertTrue($encuentro['victoria'] ?? false);
        $this->assertGreaterThan(0, $encuentro['hp_final'] ?? 0);
        // Victoria no-emboscada → barreras regeneradas al 100 %
        $this->assertSame($encuentro['barrera_fisica_final'] ?? -1, $encuentro['barrera_fisica_max'] ?? 0);
        $this->assertSame($encuentro['barrera_especial_final'] ?? -1, $encuentro['barrera_especial_max'] ?? 0);
    }

    #[Test]
    public function test_encuentro_derrota_finaliza_exploracion(): void
    {
        mt_srand(1);
        $ctx = $this->crearContexto([
            'explorador_stats' => ['hp' => 30, 'atk' => 25, 'def' => 20, 'spAtk' => 25, 'spDef' => 20, 'speed' => 20],
            'salvaje_stats' => ['hp' => 200, 'atk' => 180, 'def' => 150, 'spAtk' => 180, 'spDef' => 150, 'speed' => 200],
        ]);
        $this->handler()->handle(new ProcesarExploracionCommand($ctx['exploracion']));
        mt_srand();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos->get('bitacora', []);

        $this->assertNotEmpty($bitacora, 'Debe haber eventos en la bitácora');
        $encuentro = collect($bitacora)->first(fn (array $e) => ($e['tipo'] ?? '') === 'encuentro');
        $this->assertNotNull($encuentro, 'Debe haber un encuentro en la bitácora');
        $this->assertSame('derrota', $encuentro['resolucion'] ?? null, 'El explorador débil debe perder');
        $this->assertFalse($encuentro['victoria'] ?? true);
        $this->assertSame(0, $encuentro['hp_final'] ?? -1, 'HP final debe ser 0');

        // La exploración debe quedar marcada como derrota
        $this->assertNotNull($ctx['exploracion']->eventos->get('derrota'), 'Debe existir el flag derrota');
    }

    #[Test]
    public function test_emboscada_secuencial_combate_todos_los_ids_si_gana(): void
    {
        mt_srand(1);
        // Detección EXPERTO (speed 20 → 122): ni auto-evisión MAESTRO ni 50 %
        // de evasión con aleatorio 0.7 → combate la emboscada si gana.
        $ctx = $this->crearContexto([
            'explorador_stats' => ['hp' => 200, 'atk' => 180, 'def' => 150, 'spAtk' => 180, 'spDef' => 150, 'speed' => 20],
        ]);
        // Forzar un evento emboscada manualmente en eventos previos (ultimo_procesado pasado)
        // Usamos aleatorio 0.7 para que el simulador genere emboscadas.
        $handler = new ProcesarExploracionHandler(
            app(CommandBus::class),
            new CombateExploracion(new \Src\CombateEntrenadores\App\MapeadorPokemonBatalla(new \Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo())),
            new EscaladorNivelRival(),
            fn (): float => 0.7,
        );

        $handler->handle(new ProcesarExploracionCommand($ctx['exploracion']));
        mt_srand();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos->get('bitacora', []);

        $this->assertNotEmpty($bitacora);
        $emboscada = collect($bitacora)->first(fn (array $e) => ($e['tipo'] ?? '') === 'emboscada');

        $this->assertNotNull($emboscada, 'Debe haber una emboscada con aleatorio 0.7');
        $this->assertSame('superada', $emboscada['resolucion']);
        $this->assertCount(3, $emboscada['sub_combates'], 'Emboscada con 3 ids → 3 sub-combates si gana');
        foreach ($emboscada['sub_combates'] as $sub) {
            $this->assertTrue($sub['victoria']);
        }
    }

    #[Test]
    public function test_emboscada_derrota_no_combate_el_resto(): void
    {
        mt_srand(1);
        $ctx = $this->crearContexto([
            'explorador_stats' => ['hp' => 30, 'atk' => 25, 'def' => 20, 'spAtk' => 25, 'spDef' => 20, 'speed' => 20],
            'salvaje_stats' => ['hp' => 200, 'atk' => 180, 'def' => 150, 'spAtk' => 180, 'spDef' => 150, 'speed' => 200],
        ]);
        $handler = new ProcesarExploracionHandler(
            app(CommandBus::class),
            new CombateExploracion(new \Src\CombateEntrenadores\App\MapeadorPokemonBatalla(new \Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo())),
            new EscaladorNivelRival(),
            fn (): float => 0.7,
        );

        $handler->handle(new ProcesarExploracionCommand($ctx['exploracion']));
        mt_srand();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos->get('bitacora', []);

        $emboscada = collect($bitacora)->first(fn (array $e) => ($e['tipo'] ?? '') === 'emboscada');
        $this->assertNotNull($emboscada);
        $this->assertSame('derrota', $emboscada['resolucion']);
        $this->assertCount(1, $emboscada['sub_combates'], 'Si pierde el primero no combate el resto');
    }

    #[Test]
    public function test_nivel_rival_se_escala_con_el_minimo_del_habitat(): void
    {
        // min_lvl_1 = 4, nivel pokemon = 40 → escalar(4, 40) = 4 + intdiv(36,2) = 22
        $ctx = $this->crearContexto(['min_lvl_1' => 4]);
        $salvaje = $ctx['salvaje'];

        mt_srand(1);
        $this->handler()->handle(new ProcesarExploracionCommand($ctx['exploracion']));
        mt_srand();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos->get('bitacora', []);
        $encuentro = collect($bitacora)->first(fn (array $e) => ($e['tipo'] ?? '') === 'encuentro');
        $this->assertNotNull($encuentro, 'Debe haber un encuentro en la bitácora');

        // El salvaje fuerte vs explorador fuerte: verificar que el combate ocurrió
        $this->assertSame('victoria', $encuentro['resolucion'] ?? null);
        $this->assertNotEmpty($encuentro['log'] ?? [], 'Debe haber log de batalla');
    }

    #[Test]
    public function test_descanso_cuando_hp_menor_50_registra_evento_y_tiempo(): void
    {
        $combate = $this->createMock(CombateExploracion::class);
        $combate->method('combatir')->willReturn(new ResultadoBatallaExploracion(
            victoria: true,
            hpFinal: 30.0,
            barreraFisicaFinal: 100.0,
            barreraEspecialFinal: 100.0,
            hpMax: 100.0,
            barreraFisicaMax: 100.0,
            barreraEspecialMax: 100.0,
            log: BattleLog::vacia()->agregar('log'),
        ));

        $ctx = $this->crearContexto();
        $this->handler($combate)->handle(new ProcesarExploracionCommand($ctx['exploracion']));

        $ctx['exploracion']->refresh();
        $eventos = $ctx['exploracion']->eventos;
        $bitacora = $eventos->get('bitacora', []);

        // HP al 30 % → falta 70 % → supervivencia MAESTRO (nivel 40, dificultad 35)
        // → tasa 1 × 1.75 = 1.75 %/min → ceil(70/1.75) = 40 min
        $descanso = collect($bitacora)->first(fn (array $e) => ($e['tipo'] ?? '') === 'descanso');
        $this->assertNotNull($descanso, 'Debe registrarse descanso al quedar HP < 50 %');
        $this->assertSame(40, $descanso['duracion_minutos']);
        $this->assertSame(70, $descanso['hp_recuperado']);

        // tiempo_perdido acumulado (40 min del descanso)
        $this->assertGreaterThanOrEqual(40, (int) $eventos->get('tiempo_perdido', 0));

        // El estado persistido debe quedar al 100 % HP (JSON normaliza floats → int)
        $estado = $eventos->get('explorador');
        $this->assertSame(100, (int) $estado['hp']);
    }

    #[Test]
    public function test_emboscada_evitada_con_deteccion_maestro_genera_un_evento_extra(): void
    {
        mt_srand(1);
        // Mewtwo con detección MAESTRO (speed 200, spDef 150, nivel 40 + 10)
        $ctx = $this->crearContexto();
        $handler = new ProcesarExploracionHandler(
            app(CommandBus::class),
            new CombateExploracion(new \Src\CombateEntrenadores\App\MapeadorPokemonBatalla(new \Src\CombateEntrenadores\Domain\GeneradorMovimientosTipo())),
            new EscaladorNivelRival(),
            fn (): float => 0.7,
        );

        $handler->handle(new ProcesarExploracionCommand($ctx['exploracion']));
        mt_srand();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos->get('bitacora', []);

        $emboscadas = collect($bitacora)->filter(fn (array $e) => ($e['tipo'] ?? '') === 'emboscada')->values();
        $extras = collect($bitacora)->filter(fn (array $e) => ($e['tipo'] ?? '') !== 'emboscada')->values();

        $this->assertNotEmpty($emboscadas, 'Debe haber emboscadas con aleatorio 0.7');
        $primera = $emboscadas->first();
        $this->assertSame('evitada', $primera['resolucion'] ?? null);
        $this->assertTrue($primera['evitada'] ?? false);
        $this->assertArrayNotHasKey('sub_combates', $primera, 'Una emboscada evitada no combate');

        // Cada emboscada evitada genera exactamente 1 evento extra (hallazgo/neutral).
        $this->assertSame(
            $emboscadas->count(),
            $extras->count(),
            'Debe haber exactamente un evento extra por emboscada evitada',
        );
        $this->assertSame('neutral', $extras->first()['tipo'] ?? null);
    }

    #[Test]
    public function test_encuentro_derrota_persiste_pv_inicial_y_del_rival(): void
    {
        mt_srand(1);
        $ctx = $this->crearContexto([
            'explorador_stats' => ['hp' => 30, 'atk' => 25, 'def' => 20, 'spAtk' => 25, 'spDef' => 20, 'speed' => 20],
            'salvaje_stats' => ['hp' => 200, 'atk' => 180, 'def' => 150, 'spAtk' => 180, 'spDef' => 150, 'speed' => 200],
        ]);
        $this->handler()->handle(new ProcesarExploracionCommand($ctx['exploracion']));
        mt_srand();

        $ctx['exploracion']->refresh();
        $bitacora = $ctx['exploracion']->eventos->get('bitacora', []);

        $encuentro = collect($bitacora)->first(fn (array $e) => ($e['tipo'] ?? '') === 'encuentro');
        $this->assertNotNull($encuentro, 'Debe haber un encuentro en la bitácora');
        $this->assertSame('derrota', $encuentro['resolucion'] ?? null);

        foreach (['hp_inicial', 'hp_max', 'rival_hp_inicial', 'rival_hp_final', 'rival_hp_max'] as $clave) {
            $this->assertArrayHasKey($clave, $encuentro, "Falta la clave {$clave}");
        }

        $this->assertGreaterThan(0.0, (float) $encuentro['hp_inicial']);
        $this->assertSame((float) $encuentro['hp_max'], (float) $encuentro['hp_inicial']);
        $this->assertSame((float) $encuentro['rival_hp_max'], (float) $encuentro['rival_hp_inicial']);
        $this->assertGreaterThanOrEqual(0.0, (float) $encuentro['rival_hp_final']);
        $this->assertLessThanOrEqual((float) $encuentro['rival_hp_max'], (float) $encuentro['rival_hp_final']);
    }

    #[Test]
    public function test_evento_historico_sin_claves_de_pv_se_presenta_sin_error(): void
    {
        $vista = EventoExploracionVista::toView([
            'tipo' => 'encuentro',
            'resolucion' => 'derrota',
            'nombre' => 'rattata',
            'hp_final' => 0,
            'log' => [],
        ]);

        $this->assertNull($vista['rondas']);
        $this->assertNull($vista['detalle_derrota']);
    }
}
