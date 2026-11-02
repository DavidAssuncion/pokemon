<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Senda aplicada a los caramelos de tipo.
 *
 * Base (sin Senda): caramelos = floor(expTipo × 0.2 / 100 × M).
 * Con Senda:       caramelos = floor(expTipo × 0.2 / 100 × M × (1 + bono)).
 */
class CalculadorRecompensasSendaTest extends TestCase
{
    private CalculadorRecompensas $calculador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculador = new CalculadorRecompensas();
    }

    /**
     * @param  list<string>  $tipos
     */
    private function derrotado(
        int $id,
        int $baseExperience,
        array $tipos,
        ?int $evolutionChainId = 1,
        int $fase = 1,
    ): PokemonDerrotado {
        return new PokemonDerrotado(
            id: $id,
            baseExperience: $baseExperience,
            evolutionChainId: $evolutionChainId,
            speciesId: $id,
            captureRate: 255,
            tipos: $tipos,
            stats: collect(),
            fase: $fase,
        );
    }

    /**
     * @param  Collection<int, PokemonDerrotado>  $derrotados
     */
    private function calcular(Collection $derrotados, ?MapaSendaJugador $senda): Collection
    {
        $resultado = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            5,
            senda: $senda,
        );

        return $resultado->caramelosTipo->keyBy(fn (RecompensaTipo $tipo): string => $tipo->tipo);
    }

    public function test_senda_cinco_del_tipo_incrementa_el_caramelo_de_ese_tipo(): void
    {
        // baseExperience 1300, nivel 5 → expDerrota = 1300, un tipo.
        // Sin Senda: floor(1300 × 0.2 / 100) = floor(2.6) = 2.
        // N5 Fuego:  floor(2.6 × 1.2) = floor(3.12) = 3.
        $derrotados = collect([$this->derrotado(1, 1300, ['Fuego'])]);
        $senda = MapaSendaJugador::desdeArray([TipoPokemon::FUEGO->value => 5]);

        $conSenda = $this->calcular($derrotados, $senda);
        $sinSenda = $this->calcular($derrotados, null);

        $this->assertSame(3, $conSenda->get('Fuego')->cantidad);
        $this->assertSame(2, $sinSenda->get('Fuego')->cantidad);
    }

    public function test_cada_tipo_de_un_dual_aplica_su_propio_bono_de_senda(): void
    {
        // baseExperience 2600, nivel 5 → expDerrota = 2600; dual → 1300 por tipo.
        // Sin bono: floor(2.6) = 2. Fuego N5 (×1.2) → floor(3.12) = 3.
        // Agua N4 (×1.1) → floor(2.86) = 2.
        $derrotados = collect([$this->derrotado(1, 2600, ['Fuego', 'Agua'])]);
        $senda = MapaSendaJugador::desdeArray([
            TipoPokemon::FUEGO->value => 5,
            TipoPokemon::AGUA->value => 4,
        ]);

        $caramelos = $this->calcular($derrotados, $senda);

        $this->assertSame(3, $caramelos->get('Fuego')->cantidad);
        $this->assertSame(2, $caramelos->get('Agua')->cantidad);
    }

    public function test_un_tipo_sin_senda_registrada_mantiene_su_valor_base(): void
    {
        // El mapa solo trae Fuego: Agua se considera N1 (sin pasivos).
        $derrotados = collect([$this->derrotado(1, 2600, ['Fuego', 'Agua'])]);
        $senda = MapaSendaJugador::desdeArray([TipoPokemon::FUEGO->value => 5]);

        $caramelos = $this->calcular($derrotados, $senda);

        $this->assertSame(3, $caramelos->get('Fuego')->cantidad);
        $this->assertSame(2, $caramelos->get('Agua')->cantidad);
    }

    public function test_sin_senda_el_resultado_es_identico_al_actual(): void
    {
        $derrotados = collect([
            $this->derrotado(1, 1300, ['Fuego']),
            $this->derrotado(2, 2600, ['Agua', 'Planta']),
        ]);

        $porDefecto = $this->calculador->calcular($derrotados, fn (): bool => false, 5);
        $explicitaVacia = $this->calculador->calcular(
            $derrotados,
            fn (): bool => false,
            5,
            senda: MapaSendaJugador::vacio(),
        );

        $this->assertEquals(
            $porDefecto->caramelosTipo->values()->all(),
            $explicitaVacia->caramelosTipo->values()->all(),
        );
    }

    public function test_el_floor_se_aplica_una_sola_vez_tras_el_bono(): void
    {
        // Si se truncara antes de aplicar el bono: floor(2.6) × 1.2 → floor(2.4) = 2.
        // Con un único floor al final: floor(2.6 × 1.2) = floor(3.12) = 3.
        $derrotados = collect([$this->derrotado(1, 1300, ['Fuego'])]);
        $senda = MapaSendaJugador::desdeArray([TipoPokemon::FUEGO->value => 5]);

        $caramelos = $this->calcular($derrotados, $senda);

        $this->assertSame(3, $caramelos->get('Fuego')->cantidad);
    }
}
