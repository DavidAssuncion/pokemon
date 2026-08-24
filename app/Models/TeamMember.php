<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    protected $table = 'team_members';

    protected $fillable = [
        'team_id', 'pokemon_id', 'slot', 'behavior',
    ];

    protected $casts = [
    ];

    public function reclutado()
    {
        return $this->belongsTo(Reclutado::class, 'pokemon_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
