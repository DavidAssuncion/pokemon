<?php

declare(strict_types=1);

namespace Tests\Feature\Exploraciones;

use App\Enums\StatEnum;
use App\Enums\TipoEnum;
use App\Models\ExploracionActiva;
use App\Models\Granja;
use App\Models\Habitat;
use App\Models\PlayerTypePath;
use App\Models\Pokemon;
use App\Models\PokemonStat;
use App\Models\PokemonType;
use App\Models\Province;
use App\Models\Reclutado;
use App\Models\Refugio;
use App\Models\User;
use App\Support\SendaProgreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Src\Exploraciones\App\FinalizarExploracionCommand;
use Src\Exploraciones\App\FinalizarExploracionHandler;
use Src\Exploraciones\App\PersistirRecompensas;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;
use Src\Shared\Bus\UnitOfWork;
use Tests\TestCase;

/**
 * Senda aplicada a la captura de exploración: el factor de refugio y el de
 * Senda se componen en un único factor que se aplica una sola vez.
 *
 * Rival con capture_rate 255 → pBase = 25/255 ≈ 0.0980.
 * Refugio nivel 2 → ×1.5 ≈ 0.1471. Senda N5 (Normal) → ×1.2 ≈ 0.1765.
 */
class FinalizarExploracionSendaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(['experiencia' => 0]); // nivel 1
    }

    #[Test]
    public function refugio_y_senda_se_componen_en_un_unico_factor(): void
    {
        $this->crearProgresoSenda(TipoEnum::NORMAL->value, 5);
        $exploracion = $this->crearExploracionConRefugio(2);

        // 0.15 ≤ 0.1471 × 1.2 = 0.1765 → captura solo con ambos factores.
        $this->handler(0.15)->handle(new FinalizarExploracionCommand($exploracion));

        $this->assertCount(1, $this->capturados($exploracion));
    }

    #[Test]
    public function sin_senda_se_mantiene_el_factor_de_refugio_anterior(): void
    {
        $exploracion = $this->crearExploracionConRefugio(2);

        // 0.14 ≤ 0.1471 (solo refugio) → captura.
        $this->handler(0.14)->handle(new FinalizarExploracionCommand($exploracion));

        $this->assertCount(1, $this->capturados($exploracion));
    }

    #[Test]
    public function senda_uno_no_altera_el_factor_de_refugio(): void
    {
        $this->crearProgresoSenda(TipoEnum::NORMAL->value, 1);
        $exploracion = $this->crearExploracionConRefugio(2);

        // N1 no tiene pasivos: 0.15 > 0.1471 → no captura.
        $this->handler(0.15)->handle(new FinalizarExploracionCommand($exploracion));

        $this->assertCount(0, $this->capturados($exploracion));
    }

    #[Test]
    public function el_factor_de_senda_no_se_aplica_dos_veces(): void
    {
        $this->crearProgresoSenda(TipoEnum::NORMAL->value, 5);
        $exploracion = $this->crearExploracionConRefugio(2);

        // Factor correcto 1.5 × 1.2 = 1.8 → 0.1765; con doble Senda sería
        // 1.5 × 1.2 × 1.2 = 2.16 → 0.2117 y 0.19 capturaría.
        $this->handler(0.19)->handle(new FinalizarExploracionCommand($exploracion));

        $this->assertCount(0, $this->capturados($exploracion));
    }

    #[Test]
    public function la_senda_aumenta_los_caramelos_de_tipo_de_la_exploracion(): void
    {
        // base_experience 25000, nivel salvaje 1 → expTipo 5000.
        // Economía individual (×0.5): base floor(5000×0.2/100×0.5) = 5.
        // N5 Normal: floor(5×1.2) = 6.
        $conSenda = $this->crearExploracionConRefugio(0, 25000);
        $this->crearProgresoSenda(TipoEnum::NORMAL->value, 5);
        $this->handler(0.0)->handle(new FinalizarExploracionCommand($conSenda));

        // Otro jugador sin Senda (el primero ya subió de nivel con la EXP).
        $otro = User::factory()->create(['experiencia' => 0]);
        $sinSenda = $this->crearExploracionConRefugio(0, 25000, 1, $otro);
        $this->handler(0.0)->handle(new FinalizarExploracionCommand($sinSenda));

        $this->assertSame(6, $this->caramelosTipo($conSenda)['cantidad']);
        $this->assertSame(5, $this->caramelosTipo($sinSenda)['cantidad']);
    }

    private function crearProgresoSenda(int $tipoId, int $nivel): PlayerTypePath
    {
        return PlayerTypePath::query()->create([
            'user_id' => $this->usuario->id,
            'type_id' => $tipoId,
            'exp' => SendaProgreso::umbral($nivel),
            'level' => $nivel,
        ]);
    }

    /**
     * @return list<array{pokemon_id: int, nombre: string, cantidad: int}>
     */
    private function capturados(ExploracionActiva $exploracion): array
    {
        return $exploracion->refresh()->eventos->get('resultado')['capturados'] ?? [];
    }

    /**
     * @return array{tipo: string, slug: string, cantidad: int}
     */
    private function caramelosTipo(ExploracionActiva $exploracion): array
    {
        return $exploracion->refresh()->eventos->get('resultado')['caramelos_tipo'][0];
    }

    private function handler(float $roll): FinalizarExploracionHandler
    {
        return new FinalizarExploracionHandler(
            app(UnitOfWork::class),
            app(CalculadorRecompensas::class),
            app(PersistirRecompensas::class),
            app(TransformadorResultadoExploracion::class),
            fn (): float => $roll,
        );
    }

    /**
     * Exploración con un único rival derrotado (Normal, cadena 51). Si
     * `$refugioNivel` > 0 se crea granja y refugio de esa familia.
     */
    private function crearExploracionConRefugio(
        int $refugioNivel = 0,
        int $baseExperience = 51,
        int $offset = 0,
        ?User $usuario = null,
    ): ExploracionActiva {
        $usuario ??= $this->usuario;

        $provinceId = 1 + $offset;
        $habitatId = 1 + $offset;
        $salvajeId = 1 + $offset * 10;
        $exploradorId = 2 + $offset * 10;

        $province = Province::create(['id' => $provinceId, 'name' => 'Kanto-'.$offset]);
        $habitat = Habitat::create([
            'id' => $habitatId,
            'name' => 'Bosque-'.$offset,
            'province_id' => $province->id,
            'peligro' => 1,
        ]);

        $salvaje = Pokemon::create([
            'id' => $salvajeId,
            'name' => 'rattata',
            'species_id' => $salvajeId,
            'capture_rate' => 255,
            'base_experience' => $baseExperience,
            'height' => 3,
            'weight' => 35,
            'evolution_chain_id' => 51,
        ]);
        $this->crearStats($salvaje, ['hp' => 30, 'atk' => 30, 'def' => 25, 'spAtk' => 25, 'spDef' => 25, 'speed' => 40]);
        PokemonType::create(['pokemon_id' => $salvaje->id, 'type' => TipoEnum::NORMAL, 'slot' => 1]);
        DB::table('pokemon_habitat')->insert([
            'pokemon_id' => $salvaje->id,
            'habitat_id' => $habitat->id,
            'level' => 1,
        ]);

        if ($refugioNivel > 0) {
            $granja = Granja::create([
                'user_id' => $usuario->id,
                'habitat_id' => $habitat->id,
            ]);
            Refugio::query()->create([
                'farm_id' => $granja->id,
                'evolution_chain_id' => 51,
                'level' => $refugioNivel,
                'unhealthy_conditions' => false,
            ]);
        }

        $explorador = Pokemon::create([
            'id' => $exploradorId,
            'name' => 'mewtwo',
            'species_id' => $exploradorId,
            'capture_rate' => 3,
            'base_experience' => 340,
            'height' => 20,
            'weight' => 1220,
            'evolution_chain_id' => 52,
        ]);
        $this->crearStats($explorador, ['hp' => 200, 'atk' => 180, 'def' => 150, 'spAtk' => 180, 'spDef' => 150, 'speed' => 200]);
        PokemonType::create(['pokemon_id' => $explorador->id, 'type' => TipoEnum::PSYCHIC, 'slot' => 1]);

        $reclutado = Reclutado::create([
            'user_id' => $usuario->id,
            'pokemon_id' => $explorador->id,
            'nombre' => 'Mewtwo',
            'exp' => ['total' => 10 * 40 ** 3],
            'es_shiny' => false,
            'obj_equipados' => [],
            'movimientos' => [],
        ]);

        $exploracion = ExploracionActiva::create([
            'user_id' => $usuario->id,
            'reclutado_id' => $reclutado->id,
            'habitat_id' => $habitat->id,
            'nivel' => 1,
            'duracion_horas' => 24,
            'indefinido' => false,
            'inicio_exploracion' => now()->subHours(2),
        ]);

        $exploracion->eventos = [
            'bitacora' => [
                [
                    'tipo' => 'encuentro',
                    'subtype' => 'normal',
                    'pokemon_id' => $salvaje->id,
                    'timestamp' => '2026-09-01T10:00:00Z',
                    'resolucion' => 'victoria',
                    'victoria' => true,
                ],
            ],
            'ultimo_procesado' => now()->toIso8601String(),
        ];
        $exploracion->save();

        return $exploracion;
    }

    /**
     * @param  array<string, int>  $stats
     */
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
                'effort' => 1,
            ]);
        }
    }
}
