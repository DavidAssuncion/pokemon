<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerCombatLog extends Model
{
    use BelongsToUser;

    protected $table = 'trainer_combat_log';

    protected $fillable = [
        'user_id',
        'habitat_id',
        'level',
        'trainer_index',
        'won',
        'fought_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'trainer_index' => 'integer',
        'won' => 'boolean',
        'fought_at' => 'date',
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
