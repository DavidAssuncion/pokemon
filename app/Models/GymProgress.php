<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymProgress extends Model
{
    use BelongsToUser;

    protected $table = 'gym_progress';

    protected $fillable = [
        'user_id',
        'gym_id',
        'current_stage',
        'completed_at',
    ];

    protected $casts = [
        'current_stage' => 'integer',
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
