<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    protected $table = 'team_members';

    protected $fillable = [
        'team_id', 'pokemon_id', 'slot', 'behavior',
    ];

    /**
     * @return BelongsTo<Reclutado, $this>
     */
    public function reclutado(): BelongsTo
    {
        return $this->belongsTo(Reclutado::class, 'pokemon_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
