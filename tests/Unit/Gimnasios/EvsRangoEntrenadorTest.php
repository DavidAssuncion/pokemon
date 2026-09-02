<?php

declare(strict_types=1);

namespace Tests\Unit\Gimnasios;

use PHPUnit\Framework\Attributes\Test;
use Src\Gimnasios\Domain\EvsRangoEntrenador;
use Src\Pokemon\Domain\Stats\StatsValue;
use Tests\TestCase;

class EvsRangoEntrenadorTest extends TestCase
{
    #[Test]
    public function test_dos_mejores_stats_reciben_principal_resto_recibe_resto(): void
    {
        // stats: atk=90, spAtk=80, speed=75, hp=50, def=40, spDef=30
        // Los 2 mejores: atk(90), spAtk(80) → principal 128
        // Los 4 restantes: speed(75), hp(50), def(40), spDef(30) → resto 64
        $statsBase = ['hp' => 50, 'atk' => 90, 'def' => 40, 'spAtk' => 80, 'spDef' => 30, 'speed' => 75];
        $resultado = EvsRangoEntrenador::distribuir(128, 64, $statsBase);

        $this->assertInstanceOf(StatsValue::class, $resultado);
        $this->assertSame(64.0, $resultado->hp);       // hp es 4º (50)
        $this->assertSame(128.0, $resultado->attack);   // atk es 1º (90)
        $this->assertSame(64.0, $resultado->defense);   // def es 5º (40)
        $this->assertSame(128.0, $resultado->spAtk);    // spAtk es 2º (80)
        $this->assertSame(64.0, $resultado->spDef);     // spDef es 6º (30)
        $this->assertSame(64.0, $resultado->speed);     // speed es 3º (75)
    }

    #[Test]
    public function test_empates_resueltos_por_orden_fijo(): void
    {
        // Todos los stats iguales (60), orden fijo: hp, atk, def, spAtk, spDef, speed
        // Los 2 primeros: hp(60), atk(60) → principal 252
        // Los 4 restantes: def(60), spAtk(60), spDef(60), speed(60) → resto 128
        $statsBase = ['hp' => 60, 'atk' => 60, 'def' => 60, 'spAtk' => 60, 'spDef' => 60, 'speed' => 60];
        $resultado = EvsRangoEntrenador::distribuir(252, 128, $statsBase);

        $this->assertSame(252.0, $resultado->hp);
        $this->assertSame(252.0, $resultado->attack);
        $this->assertSame(128.0, $resultado->defense);
        $this->assertSame(128.0, $resultado->spAtk);
        $this->assertSame(128.0, $resultado->spDef);
        $this->assertSame(128.0, $resultado->speed);
    }

    #[Test]
    public function test_gimnasio_64_64_todos_a_64(): void
    {
        $statsBase = ['hp' => 100, 'atk' => 90, 'def' => 80, 'spAtk' => 70, 'spDef' => 60, 'speed' => 50];
        $resultado = EvsRangoEntrenador::distribuir(64, 64, $statsBase);

        $this->assertSame(64.0, $resultado->hp);
        $this->assertSame(64.0, $resultado->attack);
        $this->assertSame(64.0, $resultado->defense);
        $this->assertSame(64.0, $resultado->spAtk);
        $this->assertSame(64.0, $resultado->spDef);
        $this->assertSame(64.0, $resultado->speed);
    }

    #[Test]
    public function test_ruta_0_0_todos_a_0(): void
    {
        $statsBase = ['hp' => 100, 'atk' => 90, 'def' => 80, 'spAtk' => 70, 'spDef' => 60, 'speed' => 50];
        $resultado = EvsRangoEntrenador::distribuir(0, 0, $statsBase);

        $this->assertSame(0.0, $resultado->hp);
        $this->assertSame(0.0, $resultado->attack);
        $this->assertSame(0.0, $resultado->defense);
        $this->assertSame(0.0, $resultado->spAtk);
        $this->assertSame(0.0, $resultado->spDef);
        $this->assertSame(0.0, $resultado->speed);
    }

    #[Test]
    public function test_orden_fijo_consistente_con_stats_mezclados(): void
    {
        // hp=100, atk=95, def=30, spAtk=95, spDef=30, speed=20
        // Empate atk(95) vs spAtk(95) → por orden fijo: atk va antes que spAtk
        // Mejores: hp(100), atk(95) → principal 252
        // Resto: spAtk(95), def(30), spDef(30), speed(20) → resto 128
        $statsBase = ['hp' => 100, 'atk' => 95, 'def' => 30, 'spAtk' => 95, 'spDef' => 30, 'speed' => 20];
        $resultado = EvsRangoEntrenador::distribuir(252, 128, $statsBase);

        $this->assertSame(252.0, $resultado->hp, 'hp es el mejor con 100');
        $this->assertSame(252.0, $resultado->attack, 'atk empata con spAtk pero orden fijo lo pone 2º');
        $this->assertSame(128.0, $resultado->spAtk, 'spAtk empata con atk pero orden fijo lo pone 3º');
        $this->assertSame(128.0, $resultado->defense, 'def es 4º con 30');
        $this->assertSame(128.0, $resultado->spDef, 'spDef es 5º con 30');
        $this->assertSame(128.0, $resultado->speed, 'speed es 6º con 20');
    }
}
