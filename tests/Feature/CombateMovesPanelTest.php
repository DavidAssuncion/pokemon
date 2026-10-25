<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Combate;
use Livewire\Livewire;
use Tests\TestCase;

class CombateMovesPanelTest extends TestCase
{
    public function test_preview_target_renders_moves_panel_from_presenter_contract(): void
    {
        Livewire::test(Combate::class)
            ->set('animAttackerId', '')
            ->call('previewTarget', 1, 0)
            ->assertSet('phase', 'player_move')
            ->assertSet('currentMoves', fn (array $moves): bool => $moves !== [])
            ->assertSee('Ataques')
            ->assertSee('Ronda')
            ->assertSee('move-card');
    }
}
