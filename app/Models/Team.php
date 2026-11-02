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
        'formacion',
        'posturas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'formacion' => 'array',
            'posturas' => 'array',
        ];
    }

    /**
     * Miembros del equipo. La exploración es individual por reclutado, por lo
     * que el equipo no se bloquea aunque alguno de sus miembros esté explorando.
     *
     * @return HasMany<TeamMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }
}
