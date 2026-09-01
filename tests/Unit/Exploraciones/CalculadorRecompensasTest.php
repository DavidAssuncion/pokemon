<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\RecompensaCaptura;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
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

    public function test_exp_total_suma_exp_derrota_con_el_nivel_salvaje(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 64),
            $this->derrotado(id: 2, baseExperience: 62),
            $this->derrotado(id: 3, baseExperience: 100),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 5);

        $esperado = NivelHelper::expDerrota(64, 5)
            + NivelHelper::expDerrota(62, 5)
            + NivelHelper::expDerrota(100, 5);

        $this->assertSame($esperado, $resultado->expTotal);
    }

    public function test_exp_por_miembro_reparte_el_80_entre_tres(): void
    {
        // D3: cada integrante += floor((T×0.8)/3) por derrota.
        // T(64, 5) = 64 → floor(51.2/3) = 17; T(62, 5) = 62 → floor(49.6/3) = 16.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 64),
            $this->derrotado(id: 2, baseExperience: 62),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 5);

        $this->assertSame(17 + 16, $resultado->expPorMiembro);
    }

    public function test_multiplicador_de_categoria_se_aplica_a_exp_y_caramelos(): void
    {
        // Categoría exito_parcial (0.7): exp y caramelos se multiplican con floor.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, evolutionChainId: 7, fase: 1, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100, 0.7);

        // T = 10000 → expTotal 7000; expPorMiembro floor(floor(10000×0.8/3)×0.7) = floor(2666×0.7) = 1866.
        $this->assertSame(7000, $resultado->expTotal);
        $this->assertSame(1866, $resultado->expPorMiembro);

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
            $resultado['caramelosFamilia']->first(),
        );
        $this->assertEquals(
            new RecompensaEv(stat: 2, cantidad: 1),
            $resultado['caramelosEv']->first(),
        );
        $this->assertEquals(
            new RecompensaTipo(tipo: 'Fuego', cantidad: 3),
            $resultado['caramelosTipo']->first(),
        );
    }

    public function test_calcular_hallazgos_ignora_hallazgos_sin_chain_conocida(): void
    {
        $hallazgos = collect([
            ['tipo' => 'hallazgo', 'subtype' => 'caramelo_familia', 'pokemon_id' => 99, 'cantidad' => 2],
        ]);

        $resultado = $this->calculador->calcularHallazgos($hallazgos, []);

        $this->assertTrue($resultado['caramelosFamilia']->isEmpty());
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
            $hallazgos['caramelosFamilia'],
            $hallazgos['caramelosEv'],
            $hallazgos['caramelosTipo'],
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
        // Doble tipo → intdiv(10000, 2) = 5000 → por miembro floor(5000×0.8/3) = 1333.
        // Tipo único → 10000 → por miembro floor(10000×0.8/3) = 2666.
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, tipos: ['Eléctrico', 'Fuego']),
            $this->derrotado(id: 2, baseExperience: 500, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100);

        $this->assertSame(1333, $resultado->expTipoPorMiembro['Eléctrico']);
        $this->assertSame(3999, $resultado->expTipoPorMiembro['Fuego']);
    }

    public function test_calcular_exp_tipo_por_miembro_aplica_multiplicador(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, baseExperience: 500, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 100, 2.0);

        // floor(2666 × 2.0) = 5332.
        $this->assertSame(5332, $resultado->expTipoPorMiembro['Fuego']);
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
}
