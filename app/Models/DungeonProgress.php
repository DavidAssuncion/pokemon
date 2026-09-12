<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Progreso del jugador en una mazmorra de hábitat: el piso actual alcanzado.
 */
class DungeonProgress extends Model
{
    protected $table = 'dungeon_progress';

    protected $fillable = [
        'user_id',
        'habitat_id',
        'current_floor',
        'completed_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'habitat_id' => 'integer',
        'current_floor' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Habitat, $this>
     */
    public function habitat(): BelongsTo
    {
        return $this->belongsTo(Habitat::class);
    }
}
