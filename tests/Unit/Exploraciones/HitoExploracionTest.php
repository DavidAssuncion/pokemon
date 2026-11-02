<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Presentation\HitoExploracion;

final class HitoExploracionTest extends TestCase
{
    public function test_resultado_vacio_devuelve_lista_vacia(): void
    {
        $this->assertSame([], HitoExploracion::desde([]));
    }

    public function test_emboscada_superada_requiere_emboscada_y_victorias(): void
    {
        $hitos = HitoExploracion::desde([
            'incidentes' => ['emboscadas' => 1, 'victorias' => 2, 'contratiempos' => 1],
        ]);

        $this->assertContains('emboscada_superada', $this->codigos($hitos));
    }

    public function test_emboscada_sin_victorias_no_genera_hito(): void
    {
        $hitos = HitoExploracion::desde([
            'incidentes' => ['emboscadas' => 1, 'victorias' => 0, 'contratiempos' => 1],
        ]);

        $this->assertNotContains('emboscada_superada', $this->codigos($hitos));
    }

    public function test_veterano_desde_cinco_victorias(): void
    {
        $hitos = HitoExploracion::desde([
            'incidentes' => ['encuentros' => 5, 'victorias' => 5, 'contratiempos' => 1],
        ]);

        $this->assertContains('veterano', $this->codigos($hitos));
    }

    public function test_recolector_cuando_hay_caramelos_de_cualquier_tipo(): void
    {
        $hitos = HitoExploracion::desde([
            'incidentes' => ['encuentros' => 1, 'victorias' => 1, 'contratiempos' => 1],
            'caramelos_ev' => [['stat' => 1, 'cantidad' => 2]],
        ]);

        $this->assertContains('recolector', $this->codigos($hitos));
    }

    public function test_recolector_no_aparece_con_cantidades_cero(): void
    {
        $hitos = HitoExploracion::desde([
            'incidentes' => ['encuentros' => 1, 'victorias' => 1, 'contratiempos' => 1],
            'caramelos_familia' => [['evolution_chain_id' => 1, 'cantidad' => 0]],
        ]);

        $this->assertNotContains('recolector', $this->codigos($hitos));
    }

    public function test_sin_contratiempos_con_actividad_y_cero_contratiempos(): void
    {
        $hitos = HitoExploracion::desde([
            'incidentes' => ['encuentros' => 3, 'victorias' => 2, 'huidas' => 1, 'emboscadas' => 0, 'contratiempos' => 0],
        ]);

        $this->assertContains('sin_contratiempos', $this->codigos($hitos));
    }

    public function test_sin_contratiempos_no_aparece_si_hubo_contratiempos(): void
    {
        $hitos = HitoExploracion::desde([
            'incidentes' => ['encuentros' => 3, 'victorias' => 2, 'contratiempos' => 1],
        ]);

        $this->assertNotContains('sin_contratiempos', $this->codigos($hitos));
    }

    public function test_sin_contratiempos_no_aparece_sin_actividad(): void
    {
        $hitos = HitoExploracion::desde([
            'incidentes' => ['encuentros' => 0, 'victorias' => 0, 'contratiempos' => 0],
        ]);

        $this->assertNotContains('sin_contratiempos', $this->codigos($hitos));
    }

    public function test_expedicion_express_entre_uno_y_sesenta_minutos(): void
    {
        $hitos = HitoExploracion::desde(['duration_real' => 45]);

        $this->assertContains('expedicion_express', $this->codigos($hitos));
    }

    public function test_expedicion_express_no_aparece_con_duracion_cero_o_mayor_de_sesenta(): void
    {
        $this->assertNotContains('expedicion_express', $this->codigos(HitoExploracion::desde(['duration_real' => 0])));
        $this->assertNotContains('expedicion_express', $this->codigos(HitoExploracion::desde(['duration_real' => 61])));
    }

    public function test_hito_tiene_codigo_etiqueta_y_descripcion(): void
    {
        $hitos = HitoExploracion::desde(['duration_real' => 30]);

        $this->assertSame(
            [
                'codigo' => 'expedicion_express',
                'etiqueta' => 'Expedición exprés',
                'descripcion' => 'Regresó en menos de una hora.',
            ],
            $hitos[0],
        );
    }

    /**
     * @param  list<array{codigo: string, etiqueta: string, descripcion: string}>  $hitos
     * @return list<string>
     */
    private function codigos(array $hitos): array
    {
        return array_column($hitos, 'codigo');
    }
}
