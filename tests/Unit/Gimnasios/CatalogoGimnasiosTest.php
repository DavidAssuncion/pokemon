<?php

declare(strict_types=1);

namespace Tests\Unit\Gimnasios;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Gimnasios\Domain\CatalogoGimnasios;
use Src\Gimnasios\Domain\DataTransferObjects\EquipoEtapaGimnasio;
use Src\Shared\Tipos\TipoPokemon;

class CatalogoGimnasiosTest extends TestCase
{
    private CatalogoGimnasios $catalogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogo = new CatalogoGimnasios();
    }

    #[Test]
    public function test_contiene_los_18_gimnasios_en_orden_de_medalla(): void
    {
        $slugs = array_map(
            static fn ($gimnasio): string => $gimnasio->slug,
            $this->catalogo->todos(),
        );

        $this->assertSame([
            'bug', 'poison', 'normal', 'grass', 'flying',
            'rock', 'electric', 'ice', 'fire', 'water',
            'ground', 'psychic', 'dark', 'ghost', 'fighting',
            'fairy', 'steel', 'dragon',
        ], $slugs);
    }

    #[Test]
    public function test_niveles_minimos_de_los_18_gimnasios(): void
    {
        $esperado = [
            'bug' => 10, 'poison' => 15, 'normal' => 20, 'grass' => 25, 'flying' => 31,
            'rock' => 36, 'electric' => 41, 'ice' => 46, 'fire' => 52, 'water' => 57,
            'ground' => 62, 'psychic' => 67, 'dark' => 73, 'ghost' => 78, 'fighting' => 83,
            'fairy' => 88, 'steel' => 94, 'dragon' => 100,
        ];

        foreach ($esperado as $slug => $nivel) {
            $this->assertSame($nivel, $this->catalogo->porSlug($slug)?->nivelMinimo, "nivel_min de {$slug}");
        }
    }

    #[Test]
    public function test_medallas_y_tipos_de_los_18_gimnasios(): void
    {
        $esperado = [
            'bug' => ['Medalla Bicho', TipoPokemon::BICHO],
            'poison' => ['Medalla Veneno', TipoPokemon::VENENO],
            'normal' => ['Medalla Normal', TipoPokemon::NORMAL],
            'grass' => ['Medalla Planta', TipoPokemon::PLANTA],
            'flying' => ['Medalla Volador', TipoPokemon::VOLADOR],
            'rock' => ['Medalla Roca', TipoPokemon::ROCA],
            'electric' => ['Medalla Eléctrico', TipoPokemon::ELECTRICO],
            'ice' => ['Medalla Hielo', TipoPokemon::HIELO],
            'fire' => ['Medalla Fuego', TipoPokemon::FUEGO],
            'water' => ['Medalla Agua', TipoPokemon::AGUA],
            'ground' => ['Medalla Tierra', TipoPokemon::TIERRA],
            'psychic' => ['Medalla Psíquico', TipoPokemon::PSIQUICO],
            'dark' => ['Medalla Siniestro', TipoPokemon::SINIESTRO],
            'ghost' => ['Medalla Fantasma', TipoPokemon::FANTASMA],
            'fighting' => ['Medalla Lucha', TipoPokemon::LUCHA],
            'fairy' => ['Medalla Hada', TipoPokemon::HADA],
            'steel' => ['Medalla Acero', TipoPokemon::ACERO],
            'dragon' => ['Medalla Dragón', TipoPokemon::DRAGON],
        ];

        foreach ($esperado as $slug => [$medalla, $tipo]) {
            $gimnasio = $this->catalogo->porSlug($slug);
            $this->assertSame($medalla, $gimnasio?->medalla, "medalla de {$slug}");
            $this->assertSame($tipo, $gimnasio?->tipo, "tipo de {$slug}");
        }
    }

    #[Test]
    public function test_bug_etapas_vanguardia_retaguardia(): void
    {
        $gimnasio = $this->catalogo->porSlug('bug');
        $this->assertNotNull($gimnasio);

        $this->assertEquipo($gimnasio->equipoEtapa(1), [268, 266], [900]);
        $this->assertEquipo($gimnasio->equipoEtapa(2), [11], [15, 269]);
        $this->assertEquipo($gimnasio->equipoEtapa(3), [14], [12, 267]);
        $this->assertEquipo($gimnasio->equipoEtapa(4), [213, 212], [127]);
    }

    #[Test]
    public function test_poison_etapa_2_reparte_posiciones_segun_pipeline(): void
    {
        // Bug reportado: nidorina | Golbat grimer-alola salieron los 3 en vanguardia.
        // La pipeline | es el separador: 30 (vanguardia) | 42, 10112 (retaguardia).
        $gimnasio = $this->catalogo->porSlug('poison');
        $this->assertNotNull($gimnasio);

        $this->assertEquipo($gimnasio->equipoEtapa(2), [30], [42, 10112]);
    }

    #[Test]
    public function test_rock_etapa_3_preserva_duplicados(): void
    {
        $gimnasio = $this->catalogo->porSlug('rock');
        $this->assertNotNull($gimnasio);

        $this->assertEquipo($gimnasio->equipoEtapa(3), [338], [464, 464]);
    }

    #[Test]
    public function test_dark_etapa_3_tiene_cuatro_miembros_en_retaguardia(): void
    {
        $gimnasio = $this->catalogo->porSlug('dark');
        $this->assertNotNull($gimnasio);

        $this->assertEquipo($gimnasio->equipoEtapa(3), [560], [461, 861, 461]);
    }

    #[Test]
    public function test_equipo_etapa_devuelve_null_fuera_de_rango(): void
    {
        $gimnasio = $this->catalogo->porSlug('bug');
        $this->assertNotNull($gimnasio);

        $this->assertNull($gimnasio->equipoEtapa(5));
    }

    #[Test]
    public function test_equipos_son_array_de_equipo_etapa_gimnasio(): void
    {
        $gimnasio = $this->catalogo->porSlug('poison');
        $this->assertNotNull($gimnasio);

        foreach ($gimnasio->equipos as $etapa => $equipo) {
            $this->assertInstanceOf(EquipoEtapaGimnasio::class, $equipo, "equipo etapa {$etapa}");
        }
        $this->assertSame([1, 2, 3, 4], array_keys($gimnasio->equipos));
    }

    /**
     * @param  list<int>  $vanguardia
     * @param  list<int>  $retaguardia
     */
    private function assertEquipo(?EquipoEtapaGimnasio $equipo, array $vanguardia, array $retaguardia): void
    {
        $this->assertNotNull($equipo);
        $this->assertSame($vanguardia, $equipo->vanguardia->all());
        $this->assertSame($retaguardia, $equipo->retaguardia->all());
    }
}
