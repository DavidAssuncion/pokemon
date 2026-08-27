<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\SlugTipo;

class SlugTipoTest extends TestCase
{
    public function test_quita_acentos_y_pasa_a_minusculas(): void
    {
        $this->assertSame('electrico', SlugTipo::de('Eléctrico'));
        $this->assertSame('dragon', SlugTipo::de('Dragón'));
        $this->assertSame('psiquico', SlugTipo::de('Psíquico'));
    }

    public function test_sin_acentos_se_mantiene_en_minusculas(): void
    {
        $this->assertSame('fuego', SlugTipo::de('Fuego'));
        $this->assertSame('hada', SlugTipo::de('Hada'));
        $this->assertSame('siniestro', SlugTipo::de('Siniestro'));
    }

    public function test_cubre_los_18_tipos_espanoles(): void
    {
        $tipos = [
            'Normal' => 'normal',
            'Lucha' => 'lucha',
            'Volador' => 'volador',
            'Veneno' => 'veneno',
            'Tierra' => 'tierra',
            'Roca' => 'roca',
            'Bicho' => 'bicho',
            'Fantasma' => 'fantasma',
            'Acero' => 'acero',
            'Fuego' => 'fuego',
            'Agua' => 'agua',
            'Planta' => 'planta',
            'Eléctrico' => 'electrico',
            'Psíquico' => 'psiquico',
            'Hielo' => 'hielo',
            'Dragón' => 'dragon',
            'Siniestro' => 'siniestro',
            'Hada' => 'hada',
        ];

        foreach ($tipos as $nombre => $slug) {
            $this->assertSame($slug, SlugTipo::de($nombre));
        }
    }
}
