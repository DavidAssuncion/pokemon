<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\SyncCandyRegionales;
use App\Models\Pokemon;
use PHPUnit\Framework\TestCase;

class SyncCandyRegionalesTest extends TestCase
{
    public function test_mapeo_variantes_regionales_por_prefijo_de_nombre(): void
    {
        $command = new SyncCandyRegionales();

        $mapa = $command->mapearVariantes([
            $this->pokemon(19, 'rattata', 19),
            $this->pokemon(10091, 'rattata-alola', 10091),
            $this->pokemon(52, 'meowth', 52),
            $this->pokemon(10107, 'meowth-alola', 10107),
            $this->pokemon(10161, 'meowth-galar', 10161),
            $this->pokemon(27, 'sandshrew', 27),
            $this->pokemon(28, 'sandslash', 28),
            $this->pokemon(10101, 'sandshrew-alola', 10101),
            $this->pokemon(10102, 'sandslash-alola', 10102),
            $this->pokemon(128, 'tauros', 128),
            $this->pokemon(10250, 'tauros-paldea-aqua-breed', 10250),
            $this->pokemon(555, 'darmanitan-standard', 555),
            $this->pokemon(10177, 'darmanitan-galar-standard', 10177),
        ]);

        $this->assertSame(19, $mapa[10091]);
        $this->assertSame(52, $mapa[10107]);
        $this->assertSame(52, $mapa[10161]);
        $this->assertSame(27, $mapa[10101]);
        $this->assertSame(28, $mapa[10102]);
        $this->assertSame(128, $mapa[10250]);
        $this->assertSame(555, $mapa[10177]);
    }

    public function test_mapeo_ignora_no_regionales_y_guiones_no_regionales(): void
    {
        $command = new SyncCandyRegionales();

        $mapa = $command->mapearVariantes([
            $this->pokemon(19, 'rattata', 19),
            $this->pokemon(29, 'nidoran-f', 29),
            $this->pokemon(122, 'mr-mime', 122),
            $this->pokemon(250, 'ho-oh', 250),
        ]);

        $this->assertSame([], $mapa);
    }

    public function test_mapeo_sin_base_omite_la_variante(): void
    {
        $command = new SyncCandyRegionales();

        $mapa = $command->mapearVariantes([
            $this->pokemon(19, 'rattata', 19),
            $this->pokemon(10091, 'rattata-alola', 10091),
            $this->pokemon(10260, 'fantasma-alola', 10260),
        ]);

        $this->assertArrayNotHasKey(10260, $mapa);
        $this->assertArrayHasKey(10091, $mapa);
    }

    public function test_sincronizar_copia_la_imagen_del_base_a_la_variante(): void
    {
        $dir = sys_get_temp_dir().'/candy-sync-'.uniqid();
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/19.webp', 'img-19');

        try {
            $resultado = (new SyncCandyRegionales())->sincronizar([
                $this->pokemon(19, 'rattata', 19),
                $this->pokemon(10091, 'rattata-alola', 10091),
            ], $dir);

            $this->assertSame(['copiadas' => 1, 'ya_existian' => 0, 'sin_origen' => 0], $resultado);
            $this->assertFileExists($dir.'/10091.webp');
            $this->assertSame('img-19', file_get_contents($dir.'/10091.webp'));
        } finally {
            @unlink($dir.'/19.webp');
            @unlink($dir.'/10091.webp');
            @rmdir($dir);
        }
    }

    public function test_sincronizar_no_sobrescribe_si_el_destino_existe(): void
    {
        $dir = sys_get_temp_dir().'/candy-sync-'.uniqid();
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/19.webp', 'img-19');
        file_put_contents($dir.'/10091.webp', 'img-existente');

        try {
            $resultado = (new SyncCandyRegionales())->sincronizar([
                $this->pokemon(19, 'rattata', 19),
                $this->pokemon(10091, 'rattata-alola', 10091),
            ], $dir);

            $this->assertSame(['copiadas' => 0, 'ya_existian' => 1, 'sin_origen' => 0], $resultado);
            $this->assertSame('img-existente', file_get_contents($dir.'/10091.webp'));
        } finally {
            @unlink($dir.'/19.webp');
            @unlink($dir.'/10091.webp');
            @rmdir($dir);
        }
    }

    public function test_sincronizar_sin_origen_no_copia(): void
    {
        $dir = sys_get_temp_dir().'/candy-sync-'.uniqid();
        mkdir($dir, 0777, true);

        try {
            $resultado = (new SyncCandyRegionales())->sincronizar([
                $this->pokemon(28, 'sandslash', 28),
                $this->pokemon(10102, 'sandslash-alola', 10102),
            ], $dir);

            $this->assertSame(['copiadas' => 0, 'ya_existian' => 0, 'sin_origen' => 1], $resultado);
            $this->assertFileDoesNotExist($dir.'/10102.webp');
        } finally {
            @rmdir($dir);
        }
    }

    public function test_sincronizar_acumula_conteos_en_escenario_mixto(): void
    {
        $dir = sys_get_temp_dir().'/candy-sync-'.uniqid();
        mkdir($dir, 0777, true);
        file_put_contents($dir.'/19.webp', 'img-19');
        file_put_contents($dir.'/10091.webp', 'img-existente');
        file_put_contents($dir.'/37.webp', 'img-37');

        try {
            $resultado = (new SyncCandyRegionales())->sincronizar([
                $this->pokemon(19, 'rattata', 19),
                $this->pokemon(10091, 'rattata-alola', 10091),
                $this->pokemon(28, 'sandslash', 28),
                $this->pokemon(10102, 'sandslash-alola', 10102),
                $this->pokemon(37, 'vulpix', 37),
                $this->pokemon(10103, 'vulpix-alola', 10103),
            ], $dir);

            $this->assertSame(['copiadas' => 1, 'ya_existian' => 1, 'sin_origen' => 1], $resultado);
            $this->assertFileExists($dir.'/10103.webp');
        } finally {
            @unlink($dir.'/19.webp');
            @unlink($dir.'/10091.webp');
            @unlink($dir.'/37.webp');
            @unlink($dir.'/10103.webp');
            @rmdir($dir);
        }
    }

    private function pokemon(int $id, string $name, int $speciesId): Pokemon
    {
        return new Pokemon([
            'id' => $id,
            'name' => $name,
            'species_id' => $speciesId,
            'evolution_chain_id' => $id,
        ]);
    }
}
