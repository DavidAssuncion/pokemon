<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Presentation\FraseAnimo;

final class FraseAnimoTest extends TestCase
{
    public function test_sin_datos_devuelve_frase_neutra(): void
    {
        $frase = FraseAnimo::para(null, null, null);

        $this->assertSame('Explorando a su ritmo.', $frase['texto']);
        $this->assertSame('neutro', $frase['tono']);
    }

    public function test_hp_critico_tiene_prioridad_sobre_rol_y_categoria(): void
    {
        $frase = FraseAnimo::para('RECOLECTOR', 10, 'exito_excepcional');

        $this->assertSame('alerta', $frase['tono']);
        $this->assertStringContainsString('debilitado', $frase['texto']);
    }

    public function test_hp_tocado_devuelve_tono_negativo(): void
    {
        $frase = FraseAnimo::para('COMBATIENTE', 45, null);

        $this->assertSame('negativo', $frase['tono']);
        $this->assertStringContainsString('daño', $frase['texto']);
    }

    public function test_categoria_fracaso_devuelve_tono_negativo(): void
    {
        $frase = FraseAnimo::para('VANGUARDIA', 100, 'fracaso');

        $this->assertSame('negativo', $frase['tono']);
        $this->assertStringContainsString('no salió', $frase['texto']);
    }

    public function test_categoria_retirada_devuelve_tono_negativo(): void
    {
        $frase = FraseAnimo::para('RASTREADOR', 100, 'retirada');

        $this->assertSame('negativo', $frase['tono']);
        $this->assertStringContainsString('Volver', $frase['texto']);
    }

    public function test_exito_excepcional_devuelve_tono_positivo(): void
    {
        $frase = FraseAnimo::para('RECOLECTOR', 100, 'exito_excepcional');

        $this->assertSame('positivo', $frase['tono']);
        $this->assertStringContainsString('éxito excepcional', $frase['texto']);
    }

    public function test_cada_rol_tiene_frase_propia_y_determinista(): void
    {
        $frases = [];
        foreach (['RECOLECTOR', 'VANGUARDIA', 'RASTREADOR', 'COMBATIENTE'] as $rol) {
            $primera = FraseAnimo::para($rol, 100, 'exito');
            $segunda = FraseAnimo::para($rol, 100, 'exito');

            $this->assertSame($primera, $segunda);
            $this->assertSame('neutro', $primera['tono']);
            $frases[$rol] = $primera['texto'];
        }

        $this->assertCount(4, array_unique($frases));
    }

    public function test_rol_desconocido_cae_en_neutro(): void
    {
        $frase = FraseAnimo::para('SOPORTE', 100, 'exito');

        $this->assertSame('neutro', $frase['tono']);
        $this->assertSame('Explorando a su ritmo.', $frase['texto']);
    }
}
