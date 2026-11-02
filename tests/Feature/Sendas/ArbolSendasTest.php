<?php

declare(strict_types=1);

namespace Tests\Feature\Sendas;

use App\Enums\TipoEnum;
use App\Models\Move;
use App\Models\PlayerInventory;
use App\Models\PlayerTypePath;
use App\Models\TypePathNode;
use App\Models\User;
use App\Support\ArbolSendas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArbolSendasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function test_los_tramos_cuatro_y_cinco_se_muestran_en_el_nivel_visible_tres_con_su_coste(): void
    {
        $this->crearNodo('tramo-uno', 1, 40);
        $this->crearNodo('tramo-cuatro', 4, 90);
        $this->crearNodo('tramo-cinco', 5, 120);
        $this->crearProgreso(18_000, 3);
        $this->crearCaramelos(100);

        $nodos = $this->nodosDelArbol();

        $uno = $nodos['tramo-uno'];
        $this->assertSame(1, $uno['nivel_requerido']);
        $this->assertSame(1, $uno['tramo']);
        $this->assertSame(2, $uno['coste']);
        $this->assertFalse($uno['es_maestria']);

        $cuatro = $nodos['tramo-cuatro'];
        $this->assertSame(3, $cuatro['nivel_requerido']);
        $this->assertSame(4, $cuatro['tramo']);
        $this->assertSame(25, $cuatro['coste']);
        $this->assertTrue($cuatro['es_maestria']);
        $this->assertTrue($cuatro['disponible']);
        $this->assertTrue($cuatro['puede_desbloquear']);

        $cinco = $nodos['tramo-cinco'];
        $this->assertSame(3, $cinco['nivel_requerido']);
        $this->assertSame(5, $cinco['tramo']);
        $this->assertSame(60, $cinco['coste']);
        $this->assertTrue($cinco['es_maestria']);
        $this->assertTrue($cinco['disponible']);
        $this->assertTrue($cinco['puede_desbloquear']);
    }

    #[Test]
    public function test_los_nodos_de_maestria_no_estan_disponibles_antes_del_nivel_tres(): void
    {
        $this->crearNodo('tramo-cuatro', 4, 90);
        $this->crearNodo('tramo-cinco', 5, 120);
        $this->crearProgreso(9_000, 2);
        $this->crearCaramelos(100);

        $nodos = $this->nodosDelArbol();

        $this->assertFalse($nodos['tramo-cuatro']['disponible']);
        $this->assertFalse($nodos['tramo-cuatro']['puede_desbloquear']);
        $this->assertFalse($nodos['tramo-cinco']['disponible']);
        $this->assertFalse($nodos['tramo-cinco']['puede_desbloquear']);
    }

    #[Test]
    public function test_el_arbol_ordena_por_nivel_visible_y_luego_por_tramo(): void
    {
        $this->crearNodo('tramo-cinco', 5, 120);
        $this->crearNodo('tramo-cuatro', 4, 90);
        $this->crearNodo('tramo-uno', 1, 40);
        $this->crearProgreso(0, 1);
        $this->crearCaramelos(0);

        $arbol = ArbolSendas::para($this->user->id, TipoEnum::FIRE);

        $this->assertSame(
            ['tramo-uno', 'tramo-cuatro', 'tramo-cinco'],
            array_column($arbol['ramas']['especial'], 'slug'),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function nodosDelArbol(): array
    {
        $arbol = ArbolSendas::para($this->user->id, TipoEnum::FIRE);
        $nodos = [];

        foreach ($arbol['ramas']['especial'] as $nodo) {
            $nodos[(string) $nodo['slug']] = $nodo;
        }

        return $nodos;
    }

    private function crearNodo(string $slug, int $tramo, int $basePower): TypePathNode
    {
        $move = Move::query()->create([
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'type_id' => TipoEnum::FIRE->value,
            'category' => 'especial',
            'base_power' => $basePower,
            'nivel_requerido' => 10,
        ]);

        return TypePathNode::query()->create([
            'type_id' => TipoEnum::FIRE->value,
            'move_id' => $move->id,
            'category' => 'especial',
            'required_level' => $tramo,
            'sort_order' => 1,
        ]);
    }

    private function crearProgreso(int $exp, int $nivel): void
    {
        PlayerTypePath::query()->create([
            'user_id' => $this->user->id,
            'type_id' => TipoEnum::FIRE->value,
            'exp' => $exp,
            'level' => $nivel,
        ]);
    }

    private function crearCaramelos(int $cantidad): void
    {
        PlayerInventory::query()->create([
            'user_id' => $this->user->id,
            'item_key' => 'tipo:fuego',
            'cantidad' => $cantidad,
        ]);
    }
}
