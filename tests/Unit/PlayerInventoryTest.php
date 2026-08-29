<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PlayerInventory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_item(): void
    {
        $user = User::factory()->create();

        $item = PlayerInventory::create([
            'user_id' => $user->id,
            'item_key' => 'familia:51',
            'cantidad' => 10,
        ]);

        $this->assertDatabaseHas('player_inventory', [
            'user_id' => $user->id,
            'item_key' => 'familia:51',
            'cantidad' => 10,
        ]);
        $this->assertSame('familia:51', $item->item_key);
    }

    public function test_default_cantidad_is_zero(): void
    {
        $user = User::factory()->create();

        $item = PlayerInventory::create([
            'user_id' => $user->id,
            'item_key' => 'ev:6',
        ]);
        $item->refresh();

        $this->assertSame(0, $item->cantidad);
    }

    public function test_unique_constraint_on_user_id_and_item_key(): void
    {
        $user = User::factory()->create();
        PlayerInventory::create(['user_id' => $user->id, 'item_key' => 'familia:51']);

        $this->expectException(QueryException::class);

        PlayerInventory::create(['user_id' => $user->id, 'item_key' => 'familia:51']);
    }

    public function test_mismo_item_key_para_distintos_usuarios(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        PlayerInventory::create(['user_id' => $usuarioA->id, 'item_key' => 'familia:51', 'cantidad' => 3]);
        PlayerInventory::create(['user_id' => $usuarioB->id, 'item_key' => 'familia:51', 'cantidad' => 7]);

        $this->assertSame(2, PlayerInventory::withoutUserScope()->count());
    }
}
