<?php

declare(strict_types=1);

namespace Tests\Unit\Exploraciones;

use App\Enums\TipoEnum;
use App\Models\Pokemon;
use App\Models\PokemonType;
use App\Models\Reclutado;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;
use Src\Exploraciones\Presentation\ExploradorVista;

final class ExploradorVistaTest extends TestCase
{
    public function test_sin_reclutado_devuelve_defaults_seguros(): void
    {
        $vista = ExploradorVista::toView(null, []);

        $this->assertSame('Sin reclutado', $vista['nombre']);
        $this->assertNull($vista['id']);
        $this->assertNull($vista['pokemon_id']);
        $this->assertNull($vista['icono']);
        $this->assertSame(1, $vista['nivel']);
        $this->assertSame(0, $vista['exp_total']);
        $this->assertSame([], $vista['exp_tipos']);
        $this->assertFalse($vista['es_shiny']);
        $this->assertSame('', $vista['rol']);
        $this->assertSame('', $vista['rol_label']);
        $this->assertSame([], $vista['tipos']);
        $this->assertSame(0, $vista['hp_actual']);
        $this->assertSame(0, $vista['hp_max']);
        $this->assertNull($vista['hp_pct']);
        $this->assertSame('sin_datos', $vista['hp_estado']);
        $this->assertSame('Sin incidencias', $vista['hp_label']);
        $this->assertSame([], $vista['barreras']);
        $this->assertSame('neutro', $vista['animo']['tono']);
        $this->assertSame(0, $vista['progreso_nivel']);
    }

    public function test_hp_sin_combate_no_inventa_datos(): void
    {
        $reclutado = $this->reclutado();

        $vista = ExploradorVista::toView($reclutado, [
            'explorador' => ['hp' => 0, 'hp_max' => 0],
        ]);

        $this->assertSame('sin_datos', $vista['hp_estado']);
        $this->assertNull($vista['hp_pct']);
        $this->assertSame('Sin incidencias', $vista['hp_label']);
        $this->assertSame([], $vista['barreras']);
    }

    public function test_hp_tocado_entre_30_y_59(): void
    {
        $vista = ExploradorVista::toView($this->reclutado(), [
            'explorador' => [
                'hp' => 50,
                'hp_max' => 100,
                'barrera_fisica' => 20,
                'barrera_fisica_max' => 40,
                'barrera_especial' => 10,
                'barrera_especial_max' => 40,
            ],
        ]);

        $this->assertSame(50, $vista['hp_pct']);
        $this->assertSame('tocado', $vista['hp_estado']);
        $this->assertSame(50, $vista['hp_actual']);
        $this->assertSame(100, $vista['hp_max']);
        $this->assertSame('50 / 100 PS (50%)', $vista['hp_label']);
        $this->assertStringContainsString('amber', $vista['hp_clase']);
        $this->assertSame('negativo', $vista['animo']['tono']);
        $this->assertCount(2, $vista['barreras']);
        $this->assertSame(50, $vista['barreras'][0]['pct']);
        $this->assertSame(25, $vista['barreras'][1]['pct']);
    }

    public function test_hp_critico_por_debajo_de_30(): void
    {
        $vista = ExploradorVista::toView($this->reclutado(), [
            'explorador' => ['hp' => 10, 'hp_max' => 100],
        ]);

        $this->assertSame(10, $vista['hp_pct']);
        $this->assertSame('critico', $vista['hp_estado']);
        $this->assertStringContainsString('red', $vista['hp_clase']);
        $this->assertSame('alerta', $vista['animo']['tono']);
    }

    public function test_hp_sano_desde_60(): void
    {
        $vista = ExploradorVista::toView($this->reclutado(), [
            'explorador' => ['hp' => 60, 'hp_max' => 100],
        ]);

        $this->assertSame('sano', $vista['hp_estado']);
        $this->assertStringContainsString('green', $vista['hp_clase']);
    }

    public function test_ficha_con_shiny_nivel_experiencia_y_tipos(): void
    {
        $reclutado = $this->reclutado(
            [
                'nombre' => 'Chispa',
                'pokemon_id' => 25,
                'exp' => ['total' => 1500, 'tipos' => ['electrico' => 1500]],
                'es_shiny' => true,
                'behavior' => 'RECOLECTOR',
            ],
            [TipoEnum::ELECTRIC, TipoEnum::FLYING],
        );

        $vista = ExploradorVista::toView($reclutado, []);

        $this->assertSame('Chispa', $vista['nombre']);
        $this->assertSame(7, $vista['id']);
        $this->assertSame(25, $vista['pokemon_id']);
        $this->assertSame('/images/iconos_webp/25.webp', $vista['icono']);
        $this->assertSame(5, $vista['nivel']);
        $this->assertSame(1500, $vista['exp_total']);
        $this->assertSame(['electrico' => 1500], $vista['exp_tipos']);
        $this->assertTrue($vista['es_shiny']);
        $this->assertSame('RECOLECTOR', $vista['rol']);
        $this->assertSame('Recolector', $vista['rol_label']);
        $this->assertSame([
            ['nombre' => 'Eléctrico', 'slug' => 'electrico'],
            ['nombre' => 'Volador', 'slug' => 'volador'],
        ], $vista['tipos']);
        $this->assertGreaterThan(0, $vista['progreso_nivel']);
    }

    public function test_tipos_sin_relacion_cargada_devuelve_lista_vacia(): void
    {
        $vista = ExploradorVista::toView($this->reclutado(), []);

        $this->assertSame([], $vista['tipos']);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<TipoEnum>  $tipos
     */
    private function reclutado(array $atributos = [], array $tipos = []): Reclutado
    {
        $reclutado = new Reclutado(array_merge([
            'nombre' => 'Pikachu',
            'pokemon_id' => 25,
            'exp' => ['total' => 0, 'tipos' => []],
            'es_shiny' => false,
            'behavior' => 'COMBATIENTE',
        ], $atributos));
        $reclutado->id = 7;

        if ($tipos !== []) {
            $pokemon = new Pokemon(['id' => 25, 'name' => 'Pikachu']);

            $types = new Collection();
            foreach ($tipos as $indice => $tipo) {
                $types->push(new PokemonType([
                    'pokemon_id' => 25,
                    'type' => $tipo,
                    'slot' => $indice + 1,
                ]));
            }

            $pokemon->setRelation('types', $types);
            $reclutado->setRelation('pokemon', $pokemon);
        }

        return $reclutado;
    }

    public function test_nombre_usa_el_pokemon_cuando_el_reclutado_no_tiene_alias(): void
    {
        $vista = ExploradorVista::toView(
            $this->reclutado(['nombre' => null], [TipoEnum::ELECTRIC]),
            [],
        );

        $this->assertSame('Pikachu', $vista['nombre']);
    }

    public function test_nombre_sin_relacion_pokemon_cae_a_sin_reclutado(): void
    {
        $vista = ExploradorVista::toView($this->reclutado(['nombre' => null]), []);

        $this->assertSame('Sin reclutado', $vista['nombre']);
    }
}
