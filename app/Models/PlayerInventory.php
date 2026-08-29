<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Inventario de items del jugador (caramelos de familia/EV/tipo desde la
 * Fase 1 multiplayer). item_key canónico: `familia:{chain_id}`, `ev:{stat}`,
 * `tipo:{slug}`; único por usuario.
 */
class PlayerInventory extends Model
{
    use BelongsToUser;

    protected $table = 'player_inventory';

    protected $fillable = [
        'user_id',
        'item_key',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];
}
