<?php

declare(strict_types=1);

namespace Tests\Feature\Sendas;

use App\Support\SendaCostes;
use Database\Seeders\MoveSeeder;
use Database\Seeders\PokemonMoveSeeder;
use Database\Seeders\PokemonSeeder;
use Database\Seeders\TypePathNodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integración con el catálogo real de movimientos: verifica el reparto visible
 * y el coste total una vez comprimidos los tramos 4-5 al nivel visible 3.
 *
 * Cifras de referencia del seeder limpio: 632 nodos, 186/114/332 visibles y
 * coste total 9.432. La BD actual puede contener 639 nodos (187/114/338 y
 * coste 9.584) porque incluye 7 movimientos de formas no disponibles en los
 * seeders de Pokémon (snap-trap y 6 de tramo 4). La lógica de reparto y coste
 * es idéntica; este test fija el seeder limpio, no la BD de desarrollo.
 */
class CatalogoSendaTest extends TestCase
{
    use RefreshDatabase;

    private const NODOS_SEED_LIMPIO = 632;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PokemonSeeder::class);
        $this->seed(MoveSeeder::class);
        $this->seed(PokemonMoveSeeder::class);
        $this->seed(TypePathNodeSeeder::class);
    }

    #[Test]
    public function test_reparto_visible_y_coste_total_del_catalogo(): void
    {
        $nodos = DB::table('type_path_nodes')->get(['required_level', 'category']);

        $porNivel = [1 => 0, 2 => 0, 3 => 0];
        $porNivelCategoria = [
            1 => ['fisico' => 0, 'especial' => 0, 'estado' => 0],
            2 => ['fisico' => 0, 'especial' => 0, 'estado' => 0],
            3 => ['fisico' => 0, 'especial' => 0, 'estado' => 0],
        ];
        $costeTotal = 0;

        foreach ($nodos as $nodo) {
            $tramo = max(1, min(5, (int) $nodo->required_level));
            $visible = min(3, $tramo);
            $categoria = in_array($nodo->category, ['fisico', 'especial', 'estado'], true)
                ? (string) $nodo->category
                : 'estado';

            $porNivel[$visible]++;
            $porNivelCategoria[$visible][$categoria]++;
            $costeTotal += SendaCostes::coste($tramo);
        }

        $this->assertCount(self::NODOS_SEED_LIMPIO, $nodos);
        $this->assertSame([1 => 186, 2 => 114, 3 => 332], $porNivel);
        $this->assertSame([
            1 => ['fisico' => 62, 'especial' => 34, 'estado' => 90],
            2 => ['fisico' => 46, 'especial' => 29, 'estado' => 39],
            3 => ['fisico' => 161, 'especial' => 132, 'estado' => 39],
        ], $porNivelCategoria);
        $this->assertSame(9_432, $costeTotal);
    }
}
