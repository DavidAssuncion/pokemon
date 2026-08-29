<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Pokemon;
use App\Support\ItemCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function crearPokemon(
        int $id,
        string $name,
        int $speciesId,
        ?int $evolutionChainId = null,
    ): Pokemon {
        return Pokemon::create([
            'id' => $id,
            'name' => $name,
            'species_id' => $speciesId,
            'capture_rate' => 45,
            'base_experience' => 64,
            'height' => 7,
            'weight' => 69,
            'evolution_chain_id' => $evolutionChainId,
        ]);
    }

    public function test_keys_canonicas(): void
    {
        $this->assertSame('familia:51', ItemCatalogo::keyFamilia(51));
        $this->assertSame('ev:6', ItemCatalogo::keyEv(6));
        $this->assertSame('tipo:electrico', ItemCatalogo::keyTipo('Eléctrico'));
        $this->assertSame('tipo:dragon', ItemCatalogo::keyTipo('Dragón'));
    }

    public function test_resolve_familia_elige_el_primer_integrante_de_menor_species_id(): void
    {
        // Happiny(440)/Chansey(113)/Blissey(242): la base de display es Chansey (min species_id).
        $this->crearPokemon(1, 'chansey', 113, 51);
        $this->crearPokemon(2, 'blissey', 242, 51);
        $this->crearPokemon(3, 'happiny', 440, 51);

        $resuelto = ItemCatalogo::resolve('familia:51');

        $this->assertSame('chansey', $resuelto['nombre']);
        $this->assertSame('/images/candy_pokemon/1.webp', $resuelto['imagen']);
        $this->assertSame('familia', $resuelto['categoria']);
    }

    public function test_resolve_familia_desempata_por_id_con_especies_iguales(): void
    {
        // Dos miembros con el mismo species_id: gana el de menor id (regla documentada).
        $this->crearPokemon(5, 'copia-tardia', 113, 52);
        $this->crearPokemon(4, 'copia-temprana', 113, 52);

        $resuelto = ItemCatalogo::resolve('familia:52');

        $this->assertSame('copia-temprana', $resuelto['nombre']);
        $this->assertSame('/images/candy_pokemon/4.webp', $resuelto['imagen']);
    }

    public function test_resolve_familia_sin_pokemon_cae_al_fallback(): void
    {
        $resuelto = ItemCatalogo::resolve('familia:999');

        $this->assertSame('Desconocido', $resuelto['nombre']);
        $this->assertSame('/images/candy_pokemon/0.webp', $resuelto['imagen']);
        $this->assertSame('familia', $resuelto['categoria']);
    }

    public function test_resolve_ev_devuelve_nombre_y_slug_del_stat(): void
    {
        $resuelto = ItemCatalogo::resolve('ev:2');

        $this->assertSame('Ataque', $resuelto['nombre']);
        $this->assertSame('/images/candy_ev/atk.webp', $resuelto['imagen']);
        $this->assertSame('ev', $resuelto['categoria']);
    }

    public function test_resolve_ev_todos_los_stats(): void
    {
        $esperados = [
            1 => ['hp', 'PS (HP)'],
            2 => ['atk', 'Ataque'],
            3 => ['def', 'Defensa'],
            4 => ['atksp', 'Ataque Especial'],
            5 => ['defsp', 'Defensa Especial'],
            6 => ['spd', 'Velocidad'],
        ];

        foreach ($esperados as $stat => [$slug, $nombre]) {
            $resuelto = ItemCatalogo::resolve('ev:'.$stat);
            $this->assertSame($nombre, $resuelto['nombre'], "stat {$stat}");
            $this->assertSame("/images/candy_ev/{$slug}.webp", $resuelto['imagen'], "stat {$stat}");
        }
    }

    public function test_resolve_ev_desconocido_cae_al_fallback(): void
    {
        $resuelto = ItemCatalogo::resolve('ev:99');

        $this->assertSame('Desconocido', $resuelto['nombre']);
        $this->assertSame('/images/candy_pokemon/0.webp', $resuelto['imagen']);
        $this->assertSame('ev', $resuelto['categoria']);
    }

    public function test_resolve_tipo_resuelve_el_slug_al_nombre_en_espanol(): void
    {
        $resuelto = ItemCatalogo::resolve('tipo:electrico');

        $this->assertSame('Eléctrico', $resuelto['nombre']);
        $this->assertSame('/images/candy_type/electrico.webp', $resuelto['imagen']);
        $this->assertSame('tipo', $resuelto['categoria']);
    }

    public function test_resolve_tipo_desconocido_cae_al_fallback(): void
    {
        $resuelto = ItemCatalogo::resolve('tipo:metal');

        $this->assertSame('Desconocido', $resuelto['nombre']);
        $this->assertSame('/images/candy_pokemon/0.webp', $resuelto['imagen']);
        $this->assertSame('tipo', $resuelto['categoria']);
    }

    public function test_resolve_key_desconocida_no_lanza(): void
    {
        $resuelto = ItemCatalogo::resolve('pocion:42');

        $this->assertSame('Desconocido', $resuelto['nombre']);
        $this->assertSame('/images/candy_pokemon/0.webp', $resuelto['imagen']);
        $this->assertSame('desconocida', $resuelto['categoria']);
    }

    public function test_resolve_ev_no_numerico_cae_al_fallback(): void
    {
        $resuelto = ItemCatalogo::resolve('ev:xyz');

        $this->assertSame('Desconocido', $resuelto['nombre']);
        $this->assertSame('desconocida', $resuelto['categoria']);
    }
}
