<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento desbloqueado por el jugador a nivel de cuenta. Una vez
 * desbloqueado en su Senda, todos los Pokémon compatibles pueden equiparlo.
 */
class PlayerMoveUnlock extends Model
{
    use BelongsToUser;

    protected $table = 'player_move_unlocks';

    protected $fillable = [
        'user_id',
        'move_id',
    ];

    /**
     * @return BelongsTo<Move, $this>
     */
    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class);
    }
}
