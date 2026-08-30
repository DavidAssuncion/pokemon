<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Domain\EvaluadorExploracion;
use Src\Exploraciones\Domain\RolExploracion;

class EvaluadorExploracionTest extends TestCase
{
    public function test_dificultad_suma_base_del_subtipo_mas_peligro_por_cinco(): void
    {
        // RF-06: dificultad = base(subtipo) + peligro×5.
        $this->assertSame(35, EvaluadorExploracion::dificultad('normal', 1));
        $this->assertSame(55, EvaluadorExploracion::dificultad('grupo', 2));
        $this->assertSame(65, EvaluadorExploracion::dificultad('excepcional', 2));
        $this->assertSame(55, EvaluadorExploracion::dificultad('emboscada', 1));
    }

    public function test_capacidad_suficiente_es_victoria_sin_coste(): void
    {
        // capacidad 77 >= dificultad 35 → victoria.
        $resultado = EvaluadorExploracion::resolverEncuentro('normal', 77, 1, fn (): float => 0.99);
        $this->assertSame('victoria', $resultado['resolucion']);
        $this->assertSame(0, $resultado['duration_loss']);
    }

    public function test_entre_dificultad_menos_15_y_dificultad_es_victoria_con_coste(): void
    {
        // dificultad 40, capacidad 30 → en [25, 40) → victoria_con_coste.
        // aleatorio < 0.5 → coste 5; >= 0.5 → coste 10.
        $conCosteMin = EvaluadorExploracion::resolverEncuentro('normal', 30, 2, fn (): float => 0.4);
        $conCosteMax = EvaluadorExploracion::resolverEncuentro('normal', 30, 2, fn (): float => 0.9);

        $this->assertSame('victoria_con_coste', $conCosteMin['resolucion']);
        $this->assertSame(5, $conCosteMin['duration_loss']);
        $this->assertSame('victoria_con_coste', $conCosteMax['resolucion']);
        $this->assertSame(10, $conCosteMax['duration_loss']);
    }

    public function test_desventaja_salvaje_huye_con_quince_por_ciento(): void
    {
        // dificultad 40, capacidad 20 → desventaja (< 25). aleatorio 0.1 < 0.15 → huida.
        $huida = EvaluadorExploracion::resolverEncuentro('normal', 20, 2, fn (): float => 0.1);
        $this->assertSame('huida', $huida['resolucion']);
        $this->assertSame(0, $huida['duration_loss']);
    }

    public function test_desventaja_resto_es_derrota_con_diez_minutos(): void
    {
        // aleatorio 0.5 > 0.15 → derrota −10 min.
        $derrota = EvaluadorExploracion::resolverEncuentro('normal', 20, 2, fn (): float => 0.5);
        $this->assertSame('derrota', $derrota['resolucion']);
        $this->assertSame(10, $derrota['duration_loss']);
    }

    public function test_muy_por_debajo_es_retirada_probable(): void
    {
        // dificultad 40, capacidad 5 < 40−30 → retirada probable.
        $retirada = EvaluadorExploracion::resolverEncuentro('normal', 5, 2, fn (): float => 0.2);
        $this->assertSame('retirada', $retirada['resolucion']);
        $this->assertTrue($retirada['retirada']);
        $this->assertArrayHasKey('reason', $retirada);
    }

    public function test_rastreador_reduce_la_probabilidad_de_huida(): void
    {
        // Sin rastreador: aleatorio 0.1 < 0.15 → huida. Con rastreador (0.5×): 0.1 >= 0.075 → derrota.
        $huida = EvaluadorExploracion::resolverEncuentro('normal', 20, 2, fn (): float => 0.1);
        $derrota = EvaluadorExploracion::resolverEncuentro('normal', 20, 2, fn (): float => 0.1, [RolExploracion::RASTREADOR]);
        $this->assertSame('huida', $huida['resolucion']);
        $this->assertSame('derrota', $derrota['resolucion']);
    }

    public function test_combatiente_reduce_la_probabilidad_de_retirada(): void
    {
        // capacidad 5, dificultad 40: sin combatiente aleatorio 0.3 < 0.5 → retirada.
        // con combatiente (0.6×): 0.3 >= 0.3 → se sigue (huida/derrota); aleatorio 0.1 < 0.09? no → derrota.
        $retirada = EvaluadorExploracion::resolverEncuentro('normal', 5, 2, fn (): float => 0.3);
        $this->assertSame('retirada', $retirada['resolucion']);

        $sinRetirada = EvaluadorExploracion::resolverEncuentro('normal', 5, 2, fn (): float => 0.5, [RolExploracion::COMBATIENTE]);
        $this->assertNotSame('retirada', $sinRetirada['resolucion']);
    }

    public function test_emboscada_sin_vanguardia_superada_al_vencer(): void
    {
        // capacidad 77 >= dificultad emboscada 55 → victoria → superada con coste −10.
        $resultado = EvaluadorExploracion::resolverEmboscada(false, 77, 1, fn (): float => 0.5);
        $this->assertSame('superada', $resultado['resolucion']);
        $this->assertSame(10, $resultado['duration_loss']);
        $this->assertSame(55, $resultado['dificultad']);
    }

    public function test_emboscada_pierde_con_quince_minutos_y_retirada_probable(): void
    {
        // capacidad 10 < 55−15 → desventaja; aleatorio 0.9 > 0.15 → derrota → superada_con_cost −15.
        $resultado = EvaluadorExploracion::resolverEmboscada(false, 10, 1, fn (): float => 0.9);
        $this->assertSame('superada_con_cost', $resultado['resolucion']);
        $this->assertSame(15, $resultado['duration_loss']);
        $this->assertTrue($resultado['retirada_probable']);
    }

    public function test_vanguardia_detecta_y_evita_la_emboscada(): void
    {
        // Vanguardia detecta: aleatorio < 0.5 → evitada sin coste.
        $resultado = EvaluadorExploracion::resolverEmboscada(true, 10, 1, fn (): float => 0.1);
        $this->assertSame('evitada', $resultado['resolucion']);
        $this->assertSame(0, $resultado['duration_loss']);
    }

    public function test_vanguardia_detecta_pero_no_evita_y_paga_penalizacion(): void
    {
        // Vanguardia detecta pero no evita: capacidad efectiva = 50% × 10 = 5 → derrota.
        $resultado = EvaluadorExploracion::resolverEmboscada(true, 10, 1, fn (): float => 0.9);
        $this->assertSame('superada_con_cost', $resultado['resolucion']);
        $this->assertSame(15, $resultado['duration_loss']);
    }

    public function test_contratiempos_tienen_costes_base_mitigados_por_rol(): void
    {
        // Base: desorientacion 15, terreno 10, clima 10, bloqueo 15.
        $this->assertSame(15, EvaluadorExploracion::resolverContratiempo('desorientacion', [])['duration_loss']);
        $this->assertSame(10, EvaluadorExploracion::resolverContratiempo('terreno', [])['duration_loss']);
        $this->assertSame(10, EvaluadorExploracion::resolverContratiempo('clima', [])['duration_loss']);
        $this->assertSame(15, EvaluadorExploracion::resolverContratiempo('bloqueo', [])['duration_loss']);
        $this->assertSame('mitigado', EvaluadorExploracion::resolverContratiempo('terreno', [])['resolucion']);
    }

    public function test_vanguardia_mitiga_terreno_y_bloqueo(): void
    {
        $this->assertSame(5, EvaluadorExploracion::resolverContratiempo('terreno', [RolExploracion::VANGUARDIA])['duration_loss']);
        $this->assertSame(8, EvaluadorExploracion::resolverContratiempo('bloqueo', [RolExploracion::VANGUARDIA])['duration_loss']);
    }

    public function test_combatiente_mitiga_clima(): void
    {
        $this->assertSame(5, EvaluadorExploracion::resolverContratiempo('clima', [RolExploracion::COMBATIENTE])['duration_loss']);
    }

    public function test_rastreador_mitiga_tiempo_perdido_a_nivel_de_tick(): void
    {
        // El −50 % de tiempo perdido general de Rastreador se aplica en el tick
        // sobre el duration_loss acumulado, no dentro del contratiempo.
        $this->assertSame(15, EvaluadorExploracion::resolverContratiempo('desorientacion', [RolExploracion::RASTREADOR])['duration_loss']);
        $this->assertSame(0.5, RolExploracion::RASTREADOR->multiplicadorTiempoPerdido());
    }

    public function test_categoria_final_exito_con_todas_victorias(): void
    {
        $eventos = [
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'victoria'],
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'victoria'],
            ['tipo' => 'hallazgo'],
        ];
        $this->assertSame('exito', EvaluadorExploracion::categoriaFinal($eventos));
    }

    public function test_categoria_final_exito_excepcional_requiere_vencer_excepcional(): void
    {
        $eventos = [
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'victoria'],
            ['tipo' => 'encuentro', 'subtype' => 'excepcional', 'resolucion' => 'victoria'],
        ];
        $this->assertSame('exito_excepcional', EvaluadorExploracion::categoriaFinal($eventos));
    }

    public function test_categoria_final_exito_parcial_y_fracaso(): void
    {
        $parcial = [
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'victoria'],
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'derrota'],
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'derrota'],
        ];
        $this->assertSame('exito_parcial', EvaluadorExploracion::categoriaFinal($parcial));

        $fracaso = [
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'derrota'],
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'huida'],
        ];
        $this->assertSame('fracaso', EvaluadorExploracion::categoriaFinal($fracaso));
    }

    public function test_categoria_final_retirada_tiene_prioridad(): void
    {
        $eventos = [
            ['tipo' => 'encuentro', 'subtype' => 'normal', 'resolucion' => 'victoria'],
            ['tipo' => 'retirada', 'resolucion' => 'retirada', 'reason' => 'grupo_enemigo'],
        ];
        $this->assertSame('retirada', EvaluadorExploracion::categoriaFinal($eventos));
    }

    public function test_categoria_final_sin_combates_es_exito(): void
    {
        $this->assertSame('exito', EvaluadorExploracion::categoriaFinal([]));
        $this->assertSame('exito', EvaluadorExploracion::categoriaFinal([
            ['tipo' => 'hallazgo'],
            ['tipo' => 'neutral'],
        ]));
    }

    public function test_multiplicadores_de_categoria(): void
    {
        $this->assertSame(1.2, EvaluadorExploracion::multiplicador('exito_excepcional'));
        $this->assertSame(1.0, EvaluadorExploracion::multiplicador('exito'));
        $this->assertSame(0.7, EvaluadorExploracion::multiplicador('exito_parcial'));
        $this->assertSame(0.25, EvaluadorExploracion::multiplicador('fracaso'));
        $this->assertSame(1.0, EvaluadorExploracion::multiplicador('retirada'));
    }

    public function test_retrocompat_evento_sin_resolucion_es_victoria(): void
    {
        // RF-07: evento sin resolucion (bitácoras antiguas) → victoria.
        $this->assertTrue(EvaluadorExploracion::esVictoria([
            'tipo' => 'pokemon',
            'pokemon_id' => 1,
        ]));
        $this->assertTrue(EvaluadorExploracion::esVictoria([
            'tipo' => 'encuentro',
            'subtype' => 'normal',
            'resolucion' => 'victoria',
        ]));
        $this->assertFalse(EvaluadorExploracion::esVictoria([
            'tipo' => 'encuentro',
            'subtype' => 'normal',
            'resolucion' => 'derrota',
        ]));
        $this->assertFalse(EvaluadorExploracion::esVictoria([
            'tipo' => 'huida',
            'resolucion' => 'sin_combate',
        ]));
    }
}
