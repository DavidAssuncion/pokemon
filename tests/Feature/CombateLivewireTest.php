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
            ->assertSee('Campo de Combate');
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
}
