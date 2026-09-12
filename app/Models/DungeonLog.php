<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de combates de mazmorra por piso: sirve para el cooldown de 1h
 * tras una derrota (el piso ganado no se puede repetir).
 */
class DungeonLog extends Model
{
    protected $table = 'dungeon_log';

    protected $fillable = [
        'user_id',
        'habitat_id',
        'floor',
        'won',
        'fought_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'habitat_id' => 'integer',
        'floor' => 'integer',
        'won' => 'boolean',
        'fought_at' => 'datetime',
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
