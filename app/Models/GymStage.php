<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymStage extends Model
{
    protected $table = 'gym_stages';

    protected $fillable = [
        'gym_id',
        'etapa',
        'vanguardia',
        'retaguardia',
    ];

    protected $casts = [
        'etapa' => 'integer',
        'vanguardia' => 'array',
        'retaguardia' => 'array',
    ];

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }
}
