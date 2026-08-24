<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'teams';

    protected $fillable = [
        'name',
    ];

    public function members()
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }
}
