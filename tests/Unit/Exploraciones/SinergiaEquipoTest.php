<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\RolExploracion;
use Src\Exploraciones\Domain\SinergiaEquipo;

class SinergiaEquipoTest extends TestCase
{
    public function test_par_vanguardia_combatiente_es_asalto(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([RolExploracion::VANGUARDIA, RolExploracion::COMBATIENTE]);

        $this->assertNotNull($sinergia);
        $this->assertSame('asalto', $sinergia['nombre']);
        $this->assertSame(0.5, $sinergia['multiplicadorRetirada']);
    }

    public function test_par_vanguardia_rastreador_es_patrulla_con_deteccion(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([RolExploracion::VANGUARDIA, RolExploracion::RASTREADOR]);

        $this->assertNotNull($sinergia);
        $this->assertSame('patrulla', $sinergia['nombre']);
        $this->assertTrue($sinergia['detectaEmboscadas']);
    }

    public function test_par_combatiente_rastreador_es_caceria_con_menos_huidas(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([RolExploracion::COMBATIENTE, RolExploracion::RASTREADOR]);

        $this->assertNotNull($sinergia);
        $this->assertSame('caceria', $sinergia['nombre']);
        $this->assertSame(0.5, $sinergia['multiplicadorHuidas']);
    }

    public function test_par_recolector_rastreador_es_prospeccion_con_mas_caramelos(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([RolExploracion::RECOLECTOR, RolExploracion::RASTREADOR]);

        $this->assertNotNull($sinergia);
        $this->assertSame('prospeccion', $sinergia['nombre']);
        $this->assertSame(1.5, $sinergia['multiplicadorCaramelos']);
    }

    public function test_par_vanguardia_recolector_es_avance_seguro(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([RolExploracion::VANGUARDIA, RolExploracion::RECOLECTOR]);

        $this->assertNotNull($sinergia);
        $this->assertSame('avance_seguro', $sinergia['nombre']);
        $this->assertTrue($sinergia['reduccionTiempo']);
    }

    public function test_par_combatiente_recolector_es_escolta(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([RolExploracion::COMBATIENTE, RolExploracion::RECOLECTOR]);

        $this->assertNotNull($sinergia);
        $this->assertSame('escolta', $sinergia['nombre']);
        $this->assertSame(0.5, $sinergia['multiplicadorRetirada']);
    }

    public function test_trio_vcc_es_dominio_del_combate(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::VANGUARDIA,
            RolExploracion::COMBATIENTE,
            RolExploracion::COMBATIENTE,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('dominio_combate', $sinergia['nombre']);
        $this->assertSame(15, $sinergia['bonusResolucion']);
    }

    public function test_trio_vct_es_caceria(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::VANGUARDIA,
            RolExploracion::COMBATIENTE,
            RolExploracion::RASTREADOR,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('caceria', $sinergia['nombre']);
    }

    public function test_trio_rrc_es_recoleccion_segura(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::RECOLECTOR,
            RolExploracion::RECOLECTOR,
            RolExploracion::COMBATIENTE,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('recoleccion_segura', $sinergia['nombre']);
        $this->assertSame(1.75, $sinergia['multiplicadorCaramelos']);
    }

    public function test_trio_vrt_es_reconocimiento(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::VANGUARDIA,
            RolExploracion::RECOLECTOR,
            RolExploracion::RASTREADOR,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('reconocimiento', $sinergia['nombre']);
        $this->assertSame(10, $sinergia['bonusCapacidad']);
    }

    public function test_trio_vrc_es_expedicion_equilibrada(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::VANGUARDIA,
            RolExploracion::RECOLECTOR,
            RolExploracion::COMBATIENTE,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('expedicion_equilibrada', $sinergia['nombre']);
    }

    public function test_negativa_vvv_es_exploracion_agresiva(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::VANGUARDIA,
            RolExploracion::VANGUARDIA,
            RolExploracion::VANGUARDIA,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('exploracion_agresiva', $sinergia['nombre']);
        $this->assertSame(-10, $sinergia['bonusCapacidad']);
    }

    public function test_negativa_ccc_es_fuerza_bruta(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::COMBATIENTE,
            RolExploracion::COMBATIENTE,
            RolExploracion::COMBATIENTE,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('fuerza_bruta', $sinergia['nombre']);
        $this->assertSame(0.5, $sinergia['multiplicadorCaramelos']);
    }

    public function test_negativa_rrr_es_especialistas(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::RECOLECTOR,
            RolExploracion::RECOLECTOR,
            RolExploracion::RECOLECTOR,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('especialistas', $sinergia['nombre']);
        $this->assertSame(-10, $sinergia['bonusResolucion']);
    }

    public function test_negativa_ttt_es_rastreo_intensivo(): void
    {
        $sinergia = SinergiaEquipo::sinergiaPara([
            RolExploracion::RASTREADOR,
            RolExploracion::RASTREADOR,
            RolExploracion::RASTREADOR,
        ]);

        $this->assertNotNull($sinergia);
        $this->assertSame('rastreo_intensivo', $sinergia['nombre']);
        $this->assertSame(-5, $sinergia['bonusCapacidad']);
    }

    public function test_sin_sinergia_devuelve_null(): void
    {
        // Pares sin entrada: COMBATIENTE+COMBATIENTE, RECOLECTOR+RECOLECTOR, etc.
        $this->assertNull(SinergiaEquipo::sinergiaPara([RolExploracion::COMBATIENTE, RolExploracion::COMBATIENTE]));
        $this->assertNull(SinergiaEquipo::sinergiaPara([RolExploracion::RECOLECTOR, RolExploracion::RECOLECTOR]));
        $this->assertNull(SinergiaEquipo::sinergiaPara([]));
    }

    public function test_orden_de_miembros_no_afecta_la_sinergia(): void
    {
        $a = SinergiaEquipo::sinergiaPara([RolExploracion::VANGUARDIA, RolExploracion::COMBATIENTE]);
        $b = SinergiaEquipo::sinergiaPara([RolExploracion::COMBATIENTE, RolExploracion::VANGUARDIA]);

        $this->assertSame($a, $b);
        $this->assertSame('asalto', $b['nombre']);
    }

    public function test_modificadores_de_rol(): void
    {
        $this->assertSame(1.3, RolExploracion::VANGUARDIA->multiplicadorEncuentros());
        $this->assertSame(0.7, RolExploracion::RECOLECTOR->multiplicadorEncuentros());
        $this->assertSame(1.25, RolExploracion::COMBATIENTE->multiplicadorExp());
        $this->assertSame(0.8, RolExploracion::RECOLECTOR->multiplicadorExp());
        $this->assertSame(1.5, RolExploracion::RECOLECTOR->multiplicadorCaramelosHallazgo());
        $this->assertSame(0.5, RolExploracion::RASTREADOR->multiplicadorHuidas());
        $this->assertTrue(RolExploracion::VANGUARDIA->detectaEmboscadas());
        $this->assertFalse(RolExploracion::COMBATIENTE->detectaEmboscadas());
        $this->assertTrue(RolExploracion::VANGUARDIA->mitigaContratiempo('terreno'));
        $this->assertTrue(RolExploracion::COMBATIENTE->mitigaContratiempo('clima'));
        $this->assertFalse(RolExploracion::RECOLECTOR->mitigaContratiempo('bloqueo'));
    }
}
