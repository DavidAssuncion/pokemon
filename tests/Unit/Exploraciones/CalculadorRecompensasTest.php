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

    public function test_caramelos_tipo_dan_uno_por_tipo_y_agrupan_repeticiones(): void
    {
        $derrotados = collect([
            $this->derrotado(id: 1, tipos: ['Eléctrico', 'Fuego']),
            $this->derrotado(id: 2, tipos: ['Fuego']),
        ]);

        $resultado = $this->calculador->calcular($derrotados, fn (): bool => false, 1);

        $caramelos = $resultado->caramelosTipo->sortBy('tipo')->values();
        $this->assertCount(2, $caramelos);
        $this->assertEquals(new RecompensaTipo(tipo: 'Eléctrico', cantidad: 1), $caramelos->get(0));
        $this->assertEquals(new RecompensaTipo(tipo: 'Fuego', cantidad: 2), $caramelos->get(1));
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

    public function test_coleccion_vacia_devuelve_recompensas_vacias(): void
    {
        $resultado = $this->calculador->calcular(new Collection(), fn (): bool => true, 1);

        $this->assertTrue($resultado->capturas->isEmpty());
        $this->assertTrue($resultado->caramelosFamilia->isEmpty());
        $this->assertTrue($resultado->caramelosEv->isEmpty());
        $this->assertTrue($resultado->caramelosTipo->isEmpty());
        $this->assertSame(0, $resultado->expTotal);
    }
}
