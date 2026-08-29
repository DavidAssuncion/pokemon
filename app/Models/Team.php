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
     * @return HasMany<ExploracionActiva, $this>
     */
    public function exploraciones(): HasMany
    {
        return $this->hasMany(ExploracionActiva::class, 'equipo_id');
    }

    /**
     * Check if this team is currently exploring.
     */
    public function isExploring(): bool
    {
        return $this->exploraciones()
            ->whereNull('regreso')
            ->exists();
    }
}
