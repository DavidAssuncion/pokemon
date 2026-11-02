<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Combate;
use Livewire\Livewire;
use Tests\TestCase;

class CombateLivewireTest extends TestCase
{
    public function test_combate_mounts_without_constructor_error(): void
    {
        Livewire::test(Combate::class)
            ->assertSee('DEF.ESP');
    }

    public function test_combate_shows_battle_log_on_mount(): void
    {
        Livewire::test(Combate::class)
            ->assertSee('¡Comienza la batalla!');
    }

    public function test_combate_creates_battle_on_mount(): void
    {
        Livewire::test(Combate::class)
            ->assertSet('battleId', fn (string $battleId) => str_starts_with($battleId, 'battle_'))
            ->assertCount('team1', 3)
            ->assertCount('team2', 3)
            ->assertSet('round', 1);
    }

    public function test_modal_de_recompensas_de_ruta_muestra_grid_de_capturas_y_caramelos(): void
    {
        Livewire::test(Combate::class)
            ->set('phase', 'battle_over')
            ->set('rewards', [
                'exp_total' => 120,
                'exp_miembro' => 60,
                'caramelos_familia' => [
                    ['pokemon_id' => 19, 'nombre' => 'Rattata', 'cantidad' => 2],
                ],
                'capturas' => [
                    ['pokemon_id' => 25, 'cantidad' => 1],
                ],
            ])
            ->assertSee('grid-cols-3', false)
            ->assertSee('lg:col-span-1', false)
            ->assertSee('lg:col-span-2', false)
            ->assertSee('Capturados', false)
            ->assertSee('Rattata', false);
    }

    public function test_modal_de_recompensas_de_ruta_sin_capturas_usa_columna_completa(): void
    {
        Livewire::test(Combate::class)
            ->set('phase', 'battle_over')
            ->set('rewards', [
                'exp_total' => 80,
                'exp_miembro' => 40,
                'caramelos_familia' => [
                    ['pokemon_id' => 19, 'nombre' => 'Rattata', 'cantidad' => 2],
                ],
                'capturas' => [],
            ])
            ->assertSee('lg:col-span-3', false)
            ->assertDontSee('Capturados', false);
    }

    public function test_modal_de_recompensas_de_ruta_solo_con_capturas_usa_columna_completa(): void
    {
        Livewire::test(Combate::class)
            ->set('phase', 'battle_over')
            ->set('rewards', [
                'exp_total' => 50,
                'exp_miembro' => 25,
                'capturas' => [
                    ['pokemon_id' => 25, 'cantidad' => 1, 'es_shiny' => false],
                ],
            ])
            ->assertSee('lg:col-span-3', false)
            ->assertDontSee('lg:col-span-2', false)
            ->assertSee('Capturados', false);
    }
}
