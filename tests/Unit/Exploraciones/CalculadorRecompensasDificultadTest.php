<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\CalculadorRecompensas;
use Src\Exploraciones\Domain\DificultadHabitat;
use Src\Exploraciones\Domain\EconomiaRecompensas;
use Src\Exploraciones\Domain\Recompensas\PokemonDerrotado;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Shared\Domain\MultiplicadoresDificultad;

/**
 * Vector base (un derrotado, nivel salvaje 5):
 * - expDerrota(750, 5) = intdiv(3750, 5) = 750 → Σe_t = 750 (un solo tipo).
 * - fase 3 → Σfase = 3.
 * - effort 3 en un stat → Σeffort = 3.
 * - M = 1, factor económico = 1.
 *
 * Sin dificultad: familia floor(3)=3, EV floor(3)=3, tipo floor(750×0.2/100)=1.
 * TopDos: familia floor(3×3.30)=9, EV floor(3×1.85)=5, tipo floor(1.5×1.90)=2.
 * Completo: familia floor(3×3.35)=10, EV floor(3×2.80)=8, tipo floor(1.5×2.40)=3.
 */
class CalculadorRecompensasDificultadTest extends TestCase
{
    private CalculadorRecompensas $calculador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculador = new CalculadorRecompensas();
    }

    public function test_sin_dificultad_mantiene_las_recompensas_base(): void
    {
        $resultado = $this->calcular();

        $this->assertSame(3, $resultado->caramelosFamilia->first()->cantidad);
        $this->assertSame(3, $resultado->caramelosEv->first()->cantidad);
        $this->assertSame(1, $resultado->caramelosTipo->first()->cantidad);
        $this->assertSame(375, $resultado->expTotal);
        $this->assertSame(125, $resultado->expPorMiembro);
    }

    public function test_top_dos_aplica_los_multiplicadores_de_dificultad(): void
    {
        $resultado = $this->calcular(DificultadHabitat::para(2));

        $this->assertSame(9, $resultado->caramelosFamilia->first()->cantidad);
        $this->assertSame(5, $resultado->caramelosEv->first()->cantidad);
        $this->assertSame(2, $resultado->caramelosTipo->first()->cantidad);
    }

    public function test_completo_aplica_los_multiplicadores_de_dificultad(): void
    {
        $resultado = $this->calcular(DificultadHabitat::para(3));

        $this->assertSame(10, $resultado->caramelosFamilia->first()->cantidad);
        $this->assertSame(8, $resultado->caramelosEv->first()->cantidad);
        $this->assertSame(3, $resultado->caramelosTipo->first()->cantidad);
    }

    public function test_la_dificultad_no_altera_la_exp(): void
    {
        $sinDificultad = $this->calcular();
        $topDos = $this->calcular(DificultadHabitat::para(2));
        $completo = $this->calcular(DificultadHabitat::para(3));

        $this->assertSame($sinDificultad->expTotal, $topDos->expTotal);
        $this->assertSame($sinDificultad->expPorMiembro, $topDos->expPorMiembro);
        $this->assertSame($sinDificultad->expTotal, $completo->expTotal);
        $this->assertSame($sinDificultad->expPorMiembro, $completo->expPorMiembro);
    }

    public function test_la_dificultad_se_compone_una_sola_vez_con_el_multiplicador_de_categoria(): void
    {
        // M = 0.5 y TopDos: familia floor(3×0.5×3.30)=4,
        // EV floor(3×0.5×1.85)=2, tipo floor(1.5×0.5×1.90)=1.
        $resultado = $this->calculador->calcular(
            $this->derrotados(),
            fn (): bool => false,
            5,
            multiplicador: 0.5,
            dificultad: DificultadHabitat::para(2),
        );

        $this->assertSame(4, $resultado->caramelosFamilia->first()->cantidad);
        $this->assertSame(2, $resultado->caramelosEv->first()->cantidad);
        $this->assertSame(1, $resultado->caramelosTipo->first()->cantidad);
    }

    public function test_el_factor_economico_se_aplica_una_sola_vez_con_la_dificultad(): void
    {
        // Economía individual (factor 0.5) y TopDos: los caramelos quedan
        // familia floor(3×0.5×3.30)=4, EV floor(3×0.5×1.85)=2,
        // tipo floor(1.5×0.5×1.90)=1; la EXP solo lleva el factor.
        $resultado = $this->calculador->calcular(
            $this->derrotados(),
            fn (): bool => false,
            5,
            economia: EconomiaRecompensas::individual(),
            dificultad: DificultadHabitat::para(2),
        );

        $this->assertSame(4, $resultado->caramelosFamilia->first()->cantidad);
        $this->assertSame(2, $resultado->caramelosEv->first()->cantidad);
        $this->assertSame(1, $resultado->caramelosTipo->first()->cantidad);
        $this->assertSame(187, $resultado->expTotal);
        $this->assertSame(188, $resultado->expPorMiembro);
    }

    /**
     * @return Collection<int, PokemonDerrotado>
     */
    private function derrotados(): Collection
    {
        return collect([
            new PokemonDerrotado(
                id: 1,
                baseExperience: 750,
                evolutionChainId: 1,
                speciesId: 1,
                captureRate: 255,
                tipos: ['Fuego'],
                stats: collect([['stat' => 2, 'effort' => 3]]),
                fase: 3,
            ),
        ]);
    }

    private function calcular(?MultiplicadoresDificultad $dificultad = null): ResultadoRecompensas
    {
        return $this->calculador->calcular(
            $this->derrotados(),
            fn (): bool => false,
            5,
            dificultad: $dificultad,
        );
    }
}
