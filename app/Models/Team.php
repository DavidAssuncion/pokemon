<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TeamMember;

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
