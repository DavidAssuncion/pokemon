<?php

declare(strict_types=1);

namespace Tests\Feature\CombateRuta;

use App\Enums\TipoEnum;
use App\Models\Move;
use App\Models\Pokemon;
use App\Models\PokemonType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\Attributes\Test;
use Src\CombateRuta\App\SelectorMovimientosMeta;
use Tests\TestCase;

class SelectorMovimientosMetaTest extends TestCase
{
    private SelectorMovimientosMeta $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new SelectorMovimientosMeta();
    }

    #[Test]
    public function carga_seis_siete_u_ocho_movimientos_segun_el_nivel_de_ruta(): void
    {
        $pokemon = $this->crearPokemon([TipoEnum::NORMAL], $this->movimientosOfensivos(12));

        $this->assertCount(6, $this->selector->seleccionar($pokemon, 1, 100));
        $this->assertCount(7, $this->selector->seleccionar($pokemon, 2, 100));
        $this->assertCount(8, $this->selector->seleccionar($pokemon, 3, 100));
    }

    #[Test]
    public function limita_el_nivel_de_ruta_entre_seis_y_ocho(): void
    {
        $pokemon = $this->crearPokemon([TipoEnum::NORMAL], $this->movimientosOfensivos(12));

        $this->assertCount(6, $this->selector->seleccionar($pokemon, 0, 100));
        $this->assertCount(8, $this->selector->seleccionar($pokemon, 99, 100));
    }

    #[Test]
    public function prioriza_stab_y_cobertura_de_tipos(): void
    {
        $movimientos = [
            $this->crearMovimiento(1, 'normal-85', TipoEnum::NORMAL, potencia: 85),
            $this->crearMovimiento(2, 'fuego-120', TipoEnum::FIRE, 'especial', 120),
            $this->crearMovimiento(3, 'agua-100', TipoEnum::WATER, 'especial', 100),
            $this->crearMovimiento(4, 'planta-100', TipoEnum::GRASS, 'especial', 100),
            $this->crearMovimiento(5, 'roca-100', TipoEnum::ROCK, potencia: 100),
            $this->crearMovimiento(6, 'electrico-100', TipoEnum::ELECTRIC, 'especial', 100),
            $this->crearMovimiento(7, 'lucha-80', TipoEnum::FIGHTING, potencia: 80),
            $this->crearMovimiento(8, 'bicho-80', TipoEnum::BUG, potencia: 80),
            $this->crearMovimiento(9, 'normal-60', TipoEnum::NORMAL, potencia: 60),
        ];

        $elegidos = $this->selector->seleccionar(
            $this->crearPokemon([TipoEnum::NORMAL], $movimientos),
            3,
            100,
        );

        $this->assertContains('normal-85', $elegidos);

        $tipos = array_map(
            fn (string $slug): int => collect($movimientos)->firstWhere('slug', $slug)->type_id,
            $elegidos,
        );

        $this->assertGreaterThanOrEqual(4, count(array_unique($tipos)));
    }

    #[Test]
    public function excluye_movimientos_que_el_nivel_del_jugador_no_desbloquea(): void
    {
        $movimientos = [
            $this->crearMovimiento(1, 'accesible', TipoEnum::NORMAL, nivel: 10),
            $this->crearMovimiento(2, 'bloqueado-30', TipoEnum::FIRE, nivel: 30),
            $this->crearMovimiento(3, 'bloqueado-50', TipoEnum::WATER, nivel: 50),
        ];

        $elegidos = $this->selector->seleccionar(
            $this->crearPokemon([TipoEnum::NORMAL], $movimientos),
            1,
            20,
        );

        $this->assertSame(['accesible'], $elegidos);
    }

    #[Test]
    public function reserva_como_maximo_dos_huecos_para_utilidad(): void
    {
        $movimientos = array_merge(
            $this->movimientosOfensivos(10),
            $this->movimientosDeUtilidad(8),
        );

        $elegidos = $this->selector->seleccionar(
            $this->crearPokemon([TipoEnum::NORMAL], $movimientos),
            3,
            100,
        );

        $utilidades = array_filter(
            $elegidos,
            static fn (string $slug): bool => str_starts_with($slug, 'utilidad-'),
        );

        $this->assertLessThanOrEqual(2, count($utilidades));
        $this->assertGreaterThanOrEqual(4, count($elegidos) - count($utilidades));
    }

    #[Test]
    public function excluye_movimientos_que_sacrifican_al_usuario(): void
    {
        $movimientos = [
            $this->crearMovimiento(1, 'explosion', TipoEnum::NORMAL, potencia: 250, extra: [
                'data' => ['autodestruccion' => true],
            ]),
            $this->crearMovimiento(2, 'memento', TipoEnum::DARK, 'estado', 0, extra: [
                'data' => ['autodestruccion' => true],
            ]),
            $this->crearMovimiento(3, 'placaje', TipoEnum::NORMAL, potencia: 50),
        ];

        $elegidos = $this->selector->seleccionar(
            $this->crearPokemon([TipoEnum::NORMAL], $movimientos),
            1,
            100,
        );

        $this->assertSame(['placaje'], $elegidos);
    }

    #[Test]
    public function devuelve_todos_los_movimientos_si_hay_menos_de_seis(): void
    {
        $movimientos = $this->movimientosOfensivos(3);

        $elegidos = $this->selector->seleccionar(
            $this->crearPokemon([TipoEnum::NORMAL], $movimientos),
            3,
            100,
        );

        $this->assertCount(3, $elegidos);
    }

    #[Test]
    public function devuelve_lista_vacia_sin_movimientos(): void
    {
        $this->assertSame([], $this->selector->seleccionar(
            $this->crearPokemon([TipoEnum::NORMAL], []),
            1,
            100,
        ));
    }

    #[Test]
    public function la_seleccion_es_determinista(): void
    {
        $pokemon = $this->crearPokemon([TipoEnum::NORMAL], $this->movimientosOfensivos(12));

        $this->assertSame(
            $this->selector->seleccionar($pokemon, 2, 100),
            $this->selector->seleccionar($pokemon, 2, 100),
        );
    }

    /**
     * @return list<Move>
     */
    private function movimientosOfensivos(int $cantidad): array
    {
        $tipos = [
            TipoEnum::NORMAL,
            TipoEnum::FIRE,
            TipoEnum::WATER,
            TipoEnum::GRASS,
            TipoEnum::ELECTRIC,
            TipoEnum::ICE,
            TipoEnum::FIGHTING,
            TipoEnum::POISON,
            TipoEnum::GROUND,
            TipoEnum::FLYING,
            TipoEnum::PSYCHIC,
            TipoEnum::BUG,
            TipoEnum::ROCK,
            TipoEnum::GHOST,
            TipoEnum::DRAGON,
            TipoEnum::DARK,
            TipoEnum::STEEL,
            TipoEnum::FAIRY,
        ];

        $movimientos = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $tipo = $tipos[$i % count($tipos)];
            $movimientos[] = $this->crearMovimiento(
                $i + 1,
                'ataque-'.($i + 1),
                $tipo,
                potencia: 60 + ($i % 5) * 10,
            );
        }

        return $movimientos;
    }

    /**
     * @return list<Move>
     */
    private function movimientosDeUtilidad(int $cantidad): array
    {
        $movimientos = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $movimientos[] = $this->crearMovimiento(
                100 + $i,
                'utilidad-'.($i + 1),
                TipoEnum::NORMAL,
                categoria: 'estado',
                potencia: 0,
                extra: [
                    'status_effect' => 'paralysis',
                    'effect_key' => 'boost',
                    'heal_percent' => 50,
                    'self_effects' => [['stat' => 'attack', 'stages' => 1]],
                ],
            );
        }

        return $movimientos;
    }

    /**
     * @param  list<TipoEnum>  $tipos
     * @param  list<Move>  $movimientos
     */
    private function crearPokemon(array $tipos, array $movimientos): Pokemon
    {
        $pokemon = new Pokemon();

        $tiposModelo = [];
        foreach (array_values($tipos) as $indice => $tipo) {
            $tiposModelo[] = new PokemonType([
                'type' => $tipo->value,
                'slot' => $indice + 1,
            ]);
        }

        $pokemon->setRelation('types', new EloquentCollection($tiposModelo));
        $pokemon->setRelation('moves', new EloquentCollection($movimientos));

        return $pokemon;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function crearMovimiento(
        int $id,
        string $slug,
        TipoEnum $tipo,
        string $categoria = 'fisico',
        int $potencia = 60,
        int $nivel = 1,
        array $extra = [],
    ): Move {
        $movimiento = new Move(array_merge([
            'slug' => $slug,
            'name' => $slug,
            'type_id' => $tipo->value,
            'category' => $categoria,
            'base_power' => $potencia,
            'accuracy' => 100,
            'nivel_requerido' => $nivel,
            'priority' => 0,
            'multihit_min' => 1,
            'multihit_max' => 1,
            'drain_percent' => 0,
            'recoil_percent' => 0,
            'heal_percent' => 0,
            'status_effect' => null,
            'effect_key' => null,
            'self_effects' => null,
        ], $extra));

        $movimiento->id = $id;

        return $movimiento;
    }
}
