<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\EconomiaRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;
use Src\Shared\Domain\NivelHelper;

class CalculadorRecompensasTest extends TestCase
{
    private CalculadorRecompensas $calculador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculador = new CalculadorRecompensas();
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<array{stat: int, effort: int}>  $stats
     */
    private function derrotado(
        int $id,
        int $baseExperience = 100,
        ?int $evolutionChainId = 1,
        int $speciesId = 1,
        int $captureRate = 255,
        array $tipos = ['Fuego'],
        array $stats = [['stat' => 2, 'effort' => 1]],
        int $fase = 1,
    ): PokemonDerrotado {
        return new PokemonDerrotado(
            id: $id,
            baseExperience: $baseExperience,
            evolutionChainId: $evolutionChainId,
            speciesId: $speciesId,
            captureRate: $captureRate,
            tipos: $tipos,
            stats: collect($stats),
            fase: $fase,
        );
    }

    public function test_capturas_agrupa_por_pokemon_id_con_el_conteo_de_tiradas_exitosas(): void
    {
        // Charmander (id 2) capturado 2 veces, Squirtle (id 3) nunca, Bulbasaur (id 1) una vez.
        $derrotados = collect([
            $this->derrotado(id: 1, captureRate: 255),
            $this->derrotado(id: 2, captureRate: 255),
            $this->derrotado(id: 2, captureRate: 255),
            $this->derrotado(id: 3, captureRate: 0),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (PokemonDerrotado $p): bool => $p->captureRate > 0, 1);

        $capturas = $resultado->capturas
            ->sortBy('pokemonId')
            ->values();

        $this->assertCount(2, $capturas);
        $this->assertEquals(new RecompensaCaptura(pokemonId: 1, cantidad: 1), $capturas->get(0));
        $this->assertEquals(new RecompensaCaptura(pokemonId: 2, cantidad: 2), $capturas->get(1));
    }

    public function test_capturas_usa_el_capture_rate_de_cada_pokemon_en_la_tirada(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, captureRate: 10),
            $this->derrotado(id: 2, captureRate: 250),
        ]);

        $tiradas = [];
        $resultado = $this->calculador->calcular($derrotados, function (PokemonDerrotado $p) use (&$tiradas): bool {
            $tiradas[] = $p->captureRate;

            return false;
        }, 1);

        $this->assertSame([10, 250], $tiradas);
        $this->assertTrue($resultado->capturas->isEmpty());
    }

    public function test_caramelos_familia_suman_fase_por_cadena_evolutiva(): void
    {
        // 2 charmander (fase 1) + 1 charmeleon (fase 2) en la cadena 7 → 2×1 + 1×2 = 4
        $derrotados = collect([
            $this->derrotado(id: 1, evolutionChainId: 7, speciesId: 4, fase: 1),
            $this->derrotado(id: 2, evolutionChainId: 7, speciesId: 4, fase: 1),
            $this->derrotado(id: 3, evolutionChainId: 7, speciesId: 5, fase: 2),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1);

        $this->assertCount(1, $resultado->caramelosFamilia);
        $this->assertEquals(
            new RecompensaFamilia(evolutionChainId: 7, cantidad: 4),
            $resultado->caramelosFamilia->first(),
        );
    }

    public function test_caramelos_familia_separan_por_cadena(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, evolutionChainId: 1, fase: 1),
            $this->derrotado(id: 2, evolutionChainId: 2, fase: 3),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1);

        $this->assertCount(2, $resultado->caramelosFamilia);
        $this->assertSame([1, 2], $resultado->caramelosFamilia->sortBy('evolutionChainId')->pluck('evolutionChainId')->all());
        $this->assertSame([1, 3], $resultado->caramelosFamilia->sortBy('evolutionChainId')->pluck('cantidad')->all());
    }

    public function test_pokemon_sin_cadena_evolutiva_no_genera_caramelo_familia(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, evolutionChainId: null),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1);

        $this->assertTrue($resultado->caramelosFamilia->isEmpty());
    }

    public function test_caramelos_ev_suman_el_effort_por_stat(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, stats: [
                ['stat' => 1, 'effort' => 2],
                ['stat' => 2, 'effort' => 0],
                ['stat' => 3, 'effort' => 1],
            ]),
            $this->derrotado(id: 2, stats: [
                ['stat' => 1, 'effort' => 1],
                ['stat' => 4, 'effort' => 1],
            ]),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1);

        $caramelos = $resultado->caramelosEv->sortBy('stat')->values();
        $this->assertCount(3, $caramelos);
        $this->assertEquals(new RecompensaEv(stat: 1, cantidad: 3), $caramelos->get(0));
        $this->assertEquals(new RecompensaEv(stat: 3, cantidad: 1), $caramelos->get(1));
        $this->assertEquals(new RecompensaEv(stat: 4, cantidad: 1), $caramelos->get(2));
    }

    public function test_caramelos_ev_ignoran_effort_cero(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, stats: [['stat' => 1, 'effort' => 0]]),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1);

        $this->assertTrue($resultado->caramelosEv->isEmpty());
    }

    public function test_caramelos_tipo_derivan_de_la_exp_tipada_d3(): void
    {
        // D3/RF-14: exp_tipo por victoria (1 tipo 100 %, 2 tipos 50/50);
        // caramelos_tipo = floor((exp_tipo × 0.2)/100).
        // T = expDerrota(500, 100) = intdiv(50000, 5) = 10000.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, tipos: ['Eléctrico', 'Fuego']),
            $this->derrotado(id: 2, baseExperience: 500, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100);

        // exp_tipo: Eléctrico 5000 → floor(5000×0.2/100)=10; Fuego 15000 → floor(15000×0.2/100)=30.
        $caramelos = $resultado->caramelosTipo->sortBy('tipo')->values();
        $this->assertCount(2, $caramelos);
        $this->assertEquals(new RecompensaTipo(tipo: 'Eléctrico', cantidad: 10), $caramelos->get(0));
        $this->assertEquals(new RecompensaTipo(tipo: 'Fuego', cantidad: 30), $caramelos->get(1));
    }

    public function test_caramelos_tipo_un_solo_tipo_recibe_el_cien_por_ciento(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, tipos: ['Agua']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100);

        // T = 10000 (1 tipo) → floor(10000×0.2/100) = 20.
        $this->assertEquals(new RecompensaTipo(tipo: 'Agua', cantidad: 20), $resultado->caramelosTipo->first());
    }

    public function test_caramelos_tipo_sin_exp_suficiente_no_genera_carameloss(): void
    {
        // T pequeña (nivel 1): floor((20 × 0.2)/100) = 0 → sin caramelos.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 100, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1);

        $this->assertTrue($resultado->caramelosTipo->isEmpty());
    }

    public function test_exp_total_es_la_fraccion_de_cuenta_del_pool_con_el_nivel_salvaje(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 64),
            $this->derrotado(id: 2, baseExperience: 62),
            $this->derrotado(id: 3, baseExperience: 100),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 5);

        $pool = NivelHelper::expDerrota(64, 5)
            + NivelHelper::expDerrota(62, 5)
            + NivelHelper::expDerrota(100, 5);

        // pool 226 → cuenta floor(226×0.5) = 113; destino intdiv(113, 3) = 37.
        $this->assertSame(113, $resultado->expTotal);
        $this->assertSame(37, $resultado->expPorMiembro);
        $this->assertSame(226, $pool);
    }

    public function test_exp_por_miembro_reparte_la_mitad_entre_tres(): void
    {
        // Legado: pool = T (64 + 62 = 126); cuenta 63; intdiv(63, 3) = 21.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 64),
            $this->derrotado(id: 2, baseExperience: 62),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 5);

        $this->assertSame(63, $resultado->expTotal);
        $this->assertSame(21, $resultado->expPorMiembro);
        $this->assertSame(126, $resultado->expTotal + $resultado->expPorMiembro * 3);
    }

    public function test_multiplicador_de_categoria_se_aplica_a_exp_y_caramelos(): void
    {
        // Categoría exito_parcial (0.7): exp y caramelos se multiplican con floor.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, evolutionChainId: 7, fase: 1, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100, 0.7);

        // T = 10000 → pool 7000; cuenta 3500; intdiv(3500, 3) = 1166.
        $this->assertSame(3500, $resultado->expTotal);
        $this->assertSame(1166, $resultado->expPorMiembro);
        $this->assertSame(6998, $resultado->expTotal + $resultado->expPorMiembro * 3);

        // Caramelo familia: 1 (fase 1) × 0.7 → 0 (floor 0.7).
        $this->assertSame(0, $resultado->caramelosFamilia->sum('cantidad'));
        // Caramelo tipo: floor(10000×0.2/100 × 0.7) = floor(20×0.7) = 14.
        $this->assertSame(14, $resultado->caramelosTipo->first()->cantidad);
    }

    public function test_calcular_hallazgos_suma_caramelos_de_familia_ev_y_tipo(): void
    {
        $hallazgos = collect([
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 1, 'cantidad' => 2],
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_ev', 'stat' => 2, 'cantidad' => 1],
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'tipo_id' => 10, 'cantidad' => 3],
        ]);

        $resultado = $this->calculador->calcularHallazgos($hallazgos, [1 => 51]);

        $this->assertEquals(
            new RecompensaFamilia(evolutionChainId: 51, cantidad: 2),
            $resultado->caramelosFamilia->first(),
        );
        $this->assertEquals(
            new RecompensaEv(stat: 2, cantidad: 1),
            $resultado->caramelosEv->first(),
        );
        $this->assertEquals(
            new RecompensaTipo(tipo: 'Fuego', cantidad: 3),
            $resultado->caramelosTipo->first(),
        );
    }

    public function test_hallazgo_con_multiplicador_medio_conserva_al_menos_un_caramelo_por_clase(): void
    {
        $eventos = ColeccionEventosExploracion::desdeArray([
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 1, 'cantidad' => 1],
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_ev', 'stat' => 2, 'cantidad' => 1],
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'tipo_id' => 10, 'cantidad' => 1],
        ]);

        $resultado = $this->calculador->calcularHallazgosDeEventos($eventos, [1 => 51], 0.5);

        $this->assertSame(1, $resultado->caramelosFamilia->first()->cantidad);
        $this->assertSame(1, $resultado->caramelosEv->first()->cantidad);
        $this->assertSame(1, $resultado->caramelosTipo->first()->cantidad);
    }

    public function test_dos_hallazgos_de_la_misma_familia_con_multiplicador_medio_dan_dos(): void
    {
        $eventos = ColeccionEventosExploracion::desdeArray([
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 1, 'cantidad' => 1],
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 1, 'cantidad' => 1],
        ]);

        $resultado = $this->calculador->calcularHallazgosDeEventos($eventos, [1 => 51], 0.5);

        // Base 1 + 1 = 2 → floor(2×0.5) = 1; dos hallazgos → mínimo 2.
        $this->assertCount(1, $resultado->caramelosFamilia);
        $this->assertSame(2, $resultado->caramelosFamilia->first()->cantidad);
    }

    public function test_calcular_hallazgos_ignora_hallazgos_sin_chain_conocida(): void
    {
        $hallazgos = collect([
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 99, 'cantidad' => 2],
        ]);

        $resultado = $this->calculador->calcularHallazgos($hallazgos, []);

        $this->assertTrue($resultado->caramelosFamilia->isEmpty());
    }

    public function test_calcular_hallazgos_aplica_bonus_de_caramelos_del_rango_recolector(): void
    {
        $hallazgos = collect([
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 1, 'cantidad' => 2],
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_ev', 'stat' => 2, 'cantidad' => 1],
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'tipo_id' => 10, 'cantidad' => 3],
        ]);

        // Rango Recolector bonus 3 → cada hallazgo rinde ×(1 + 3) = ×4.
        $resultado = $this->calculador->calcularHallazgos($hallazgos, [1 => 51], 1.0, 3);

        $this->assertSame(8, $resultado->caramelosFamilia->first()->cantidad);
        $this->assertSame(4, $resultado->caramelosEv->first()->cantidad);
        $this->assertSame(12, $resultado->caramelosTipo->first()->cantidad);
    }

    public function test_sumar_hallazgos_a_resultado_existente_agrupa_cantidades(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, evolutionChainId: 51, tipos: ['Fuego']),
        ]);

        $recompensas = $this->calculador->calcular($derrotados, fn (): bool => false, 100);

        $hallazgos = $this->calculador->calcularHallazgos(collect([
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 1, 'cantidad' => 3],
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_tipo', 'tipo_id' => 10, 'cantidad' => 1],
        ]), [1 => 51]);

        $combinado = $recompensas->sumarHallazgos(
            $hallazgos->caramelosFamilia,
            $hallazgos->caramelosEv,
            $hallazgos->caramelosTipo,
        );

        // familia: 1 (derrota) + 3 (hallazgo) = 4; tipo: 20 + 1 = 21.
        $this->assertSame(4, $combinado->caramelosFamilia->first()->cantidad);
        $this->assertSame(21, $combinado->caramelosTipo->first()->cantidad);
    }

    public function test_coleccion_vacia_devuelve_recompensas_vacias(): void
    {
        $resultado = $this->calculador->calcular(new Collection(), fn (): bool => true, 1);

        $this->assertTrue($resultado->capturas->isEmpty());
        $this->assertTrue($resultado->caramelosFamilia->isEmpty());
        $this->assertTrue($resultado->caramelosEv->isEmpty());
        $this->assertTrue($resultado->caramelosTipo->isEmpty());
        $this->assertSame(0, $resultado->expTotal);
        $this->assertSame(0, $resultado->expPorMiembro);
        $this->assertSame([], $resultado->expTipoPorMiembro);
    }

    public function test_calcular_incluye_exp_tipo_por_miembro(): void
    {
        // exp = expDerrota(500, 100) = intdiv(50000, 5) = 10000.
        // Eléctrico: 5000 → cuenta 2500, destino intdiv(2500, 3) = 833.
        // Fuego agregado: 5000 + 10000 = 15000 → cuenta 7500, destino intdiv(7500, 3) = 2500.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, tipos: ['Eléctrico', 'Fuego']),
            $this->derrotado(id: 2, baseExperience: 500, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100);

        $this->assertSame(833, $resultado->expTipoPorMiembro['Eléctrico']);
        $this->assertSame(2500, $resultado->expTipoPorMiembro['Fuego']);
        $this->assertSame(2500, $resultado->expTipoCuenta['Eléctrico']);
        $this->assertSame(7500, $resultado->expTipoCuenta['Fuego']);
    }

    public function test_calcular_exp_tipo_por_miembro_aplica_multiplicador(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100, 2.0);

        // poolTipo 20000 → cuenta 10000; intdiv(10000, 3) = 3333.
        $this->assertSame(3333, $resultado->expTipoPorMiembro['Fuego']);
    }

    public function test_calcular_exp_tipo_por_miembro_ignora_pokemon_sin_tipos(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, tipos: []),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100);

        $this->assertSame([], $resultado->expTipoPorMiembro);
    }

    public function test_sumar_hallazgos_conserva_exp_tipo_por_miembro(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, tipos: ['Fuego']),
        ]);
        $recompensas = $this->calculador->calcular($derrotados, fn (): bool => false, 100);

        $combinado = $recompensas->sumarHallazgos(collect(), collect(), collect());

        $this->assertSame($recompensas->expTipoPorMiembro, $combinado->expTipoPorMiembro);
    }

    public function test_economia_individual_aplica_el_factor_y_reparte_la_exp_50_50(): void
    {
        // T = expDerrota(5000, 1) = 1000 → pool floor(1000×1×0.5) = 500.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 5000),
        ]);

        $resultado = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            1,
            1.0,
            EconomiaRecompensas::individual(),
        );

        $this->assertSame(250, $resultado->expTotal);
        $this->assertSame(250, $resultado->expPorMiembro);
        $this->assertSame(500, $resultado->expTotal + $resultado->expPorMiembro);
        // EXP de tipo: un tipo → pool 500, cuenta 250 / destino 250.
        $this->assertSame(['Fuego' => 250], $resultado->expTipoCuenta);
        $this->assertSame(['Fuego' => 250], $resultado->expTipoPorMiembro);
    }

    public function test_economia_individual_con_pool_impar_da_el_resto_al_reclutado(): void
    {
        // T = expDerrota(15, 1) = 3 → pool floor(3×0.5) = 1; cuenta 0, reclutado 1.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 15, tipos: []),
        ]);

        $resultado = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            1,
            1.0,
            EconomiaRecompensas::individual(),
        );

        $this->assertSame(0, $resultado->expTotal);
        $this->assertSame(1, $resultado->expPorMiembro);
        $this->assertSame(1, $resultado->expTotal + $resultado->expPorMiembro);
        $this->assertSame([], $resultado->expTipoCuenta);
        $this->assertSame([], $resultado->expTipoPorMiembro);
    }

    public function test_economia_individual_aplica_el_multiplicador_de_categoria_sobre_el_agregado(): void
    {
        // T = 1000, categoría 0.7 → pool floor(1000×0.7×0.5) = 350.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 5000, tipos: []),
        ]);

        $resultado = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            1,
            0.7,
            EconomiaRecompensas::individual(),
        );

        $this->assertSame(175, $resultado->expTotal);
        $this->assertSame(175, $resultado->expPorMiembro);
        $this->assertSame(350, $resultado->expTotal + $resultado->expPorMiembro);
    }

    public function test_economia_individual_reduce_los_caramelos_al_50_por_ciento(): void
    {
        // Familia: fase 10 → floor(10×0.5) = 5. EV: effort 10 → 5.
        $derrotados = collect([
            $this->derrotado(
                id: 1,
                baseExperience: 100,
                evolutionChainId: 7,
                tipos: [],
                stats: [['stat' => 1, 'effort' => 10]],
                fase: 10,
            ),
        ]);

        $resultado = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            1,
            1.0,
            EconomiaRecompensas::individual(),
        );

        $this->assertSame(5, $resultado->caramelosFamilia->first()->cantidad);
        $this->assertSame(5, $resultado->caramelosEv->first()->cantidad);
    }

    public function test_economia_individual_reparte_la_exp_de_cada_tipo_50_50(): void
    {
        // T por derrota = 1000; 2 tipos → 500 por tipo; tipo único → 1000.
        // Agregado: Eléctrico 500, Fuego 500 + 1000 = 1500.
        // Pools: 250 y 750; cuentas 125 y 375; destinos 125 y 375.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 5000, tipos: ['Eléctrico', 'Fuego']),
            $this->derrotado(id: 2, baseExperience: 5000, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            1,
            1.0,
            EconomiaRecompensas::individual(),
        );

        $this->assertSame(500, $resultado->expTotal);
        $this->assertSame(500, $resultado->expPorMiembro);

        $this->assertSame(125, $resultado->expTipoCuenta['Eléctrico']);
        $this->assertSame(375, $resultado->expTipoCuenta['Fuego']);
        $this->assertSame(125, $resultado->expTipoPorMiembro['Eléctrico']);
        $this->assertSame(375, $resultado->expTipoPorMiembro['Fuego']);
    }

    public function test_la_rama_legada_reparte_la_mitad_entre_la_cuenta_y_tres_miembros(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 5000, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            1,
            1.0,
            EconomiaRecompensas::legado(),
        );

        // T = 1000 → pool 1000; cuenta 500; intdiv(500, 3) = 166.
        $this->assertSame(500, $resultado->expTotal);
        $this->assertSame(166, $resultado->expPorMiembro);
        $this->assertSame(166, $resultado->expTipoPorMiembro['Fuego']);
        $this->assertSame(['Fuego' => 500], $resultado->expTipoCuenta);
        $this->assertSame(998, $resultado->expTotal + $resultado->expPorMiembro * 3);
    }

    public function test_sin_economia_se_usa_la_politica_legada(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 5000, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1);

        $this->assertSame(500, $resultado->expTotal);
        $this->assertSame(166, $resultado->expPorMiembro);
        $this->assertSame(['Fuego' => 500], $resultado->expTipoCuenta);
    }

    public function test_sumar_hallazgos_conserva_exp_tipo_de_la_cuenta(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 5000, tipos: ['Fuego']),
        ]);
        $recompensas = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            1,
            1.0,
            EconomiaRecompensas::individual(),
        );

        $combinado = $recompensas->sumarHallazgos(collect(), collect(), collect());

        $this->assertSame(['Fuego' => 250], $combinado->expTipoCuenta);
        $this->assertSame(['Fuego' => 250], $combinado->expTipoPorMiembro);
    }
    public function test_legado_reparte_el_pool_entre_la_cuenta_y_tres_miembros(): void
    {
        // 3 derrotados con expDerrota(400, 5) = 400 cada uno → pool 1200.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 400, tipos: ['Fuego']),
            $this->derrotado(id: 2, baseExperience: 400, tipos: ['Fuego']),
            $this->derrotado(id: 3, baseExperience: 400, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 5);

        // Cuenta floor(1200×0.5) = 600; destino intdiv(600, 3) = 200.
        $this->assertSame(600, $resultado->expTotal);
        $this->assertSame(200, $resultado->expPorMiembro);
        $this->assertSame(1200, $resultado->expTotal + $resultado->expPorMiembro * 3);
        $this->assertSame(['Fuego' => 600], $resultado->expTipoCuenta);
        $this->assertSame(['Fuego' => 200], $resultado->expTipoPorMiembro);
    }

    public function test_mazmorra_separa_el_multiplicador_de_exp_y_caramelos(): void
    {
        // 3 derrotados con expDerrota(250, 5) = 250 cada uno → Σe = 750.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 250, evolutionChainId: 7, tipos: ['Fuego'], stats: [['stat' => 1, 'effort' => 1]]),
            $this->derrotado(id: 2, baseExperience: 250, evolutionChainId: 7, tipos: ['Fuego'], stats: [['stat' => 1, 'effort' => 1]]),
            $this->derrotado(id: 3, baseExperience: 250, evolutionChainId: 7, tipos: ['Fuego'], stats: [['stat' => 1, 'effort' => 1]]),
        ]);

        // Mazmorra: caramelos ×70, EXP ×10 (la EXP no hereda el 70).
        $resultado = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            5,
            multiplicador: 70.0,
            economia: EconomiaRecompensas::legado(),
            multiplicadorExp: 10.0,
        );

        // pool = floor(750×10×1.0) = 7500; cuenta 3750; intdiv(3750, 3) = 1250.
        $this->assertSame(3750, $resultado->expTotal);
        $this->assertSame(1250, $resultado->expPorMiembro);
        $this->assertSame(7500, $resultado->expTotal + $resultado->expPorMiembro * 3);
        // Caramelos: familia 3×70 = 210; EV 3×70 = 210.
        $this->assertSame(210, $resultado->caramelosFamilia->sum('cantidad'));
        $this->assertSame(210, $resultado->caramelosEv->sum('cantidad'));
    }

    public function test_pool_impar_con_tres_miembros_deja_el_resto_de_intdiv(): void
    {
        // expDerrota(1003, 5) = 1003 → pool 1003; cuenta 501; resto 502.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 1003, tipos: []),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 5);

        // intdiv(502, 3) = 167; 501 + 167×3 = 1002, sobra 1.
        $this->assertSame(501, $resultado->expTotal);
        $this->assertSame(167, $resultado->expPorMiembro);
        $this->assertSame(1002, $resultado->expTotal + $resultado->expPorMiembro * 3);
        $this->assertSame(1, 1003 - ($resultado->expTotal + $resultado->expPorMiembro * 3));
    }

    public function test_caramelos_familia_suman_fases_reales_1_2_3(): void
    {
        // Pichu/Pikachu/Raichu con etapas reales 1/2/3 → 6 × multiplicador.
        $derrotados = collect([
            $this->derrotado(id: 172, evolutionChainId: 10, speciesId: 172, fase: 1),
            $this->derrotado(id: 25, evolutionChainId: 10, speciesId: 25, fase: 2),
            $this->derrotado(id: 26, evolutionChainId: 10, speciesId: 26, fase: 3),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1, 2.0);

        $this->assertCount(1, $resultado->caramelosFamilia);
        $this->assertEquals(
            new RecompensaFamilia(evolutionChainId: 10, cantidad: 12),
            $resultado->caramelosFamilia->first(),
        );
    }
}
