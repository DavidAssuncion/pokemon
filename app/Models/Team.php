<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use BelongsToUser;

    protected $table = 'teams';

    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }

    /**
     * Check if this team is currently exploring (any member in active exploration).
     */
    public function isExploring(): bool
    {
        $memberIds = $this->members()->pluck('pokemon_id');

        return ExploracionActiva::whereNull('regreso')
            ->whereIn('reclutado_id', $memberIds)
            ->exists();
    }
}
