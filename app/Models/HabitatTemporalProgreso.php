<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Progreso de un jugador en una facción de hábitat temporal.
 */
class HabitatTemporalProgreso extends Model
{
    protected $table = 'habitat_temporal_progreso';

    protected $fillable = [
        'user_id',
        'faccion_slug',
        'oleada_actual',
        'combate_actual',
        'completed_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'oleada_actual' => 'integer',
        'combate_actual' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
