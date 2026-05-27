<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ability extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'effect',
        'data',
    ];

    protected $casts = [
        'data' => 'json',
    ];

    public function pokemon()
    {
        return $this->hasMany(Pokemon::class);
    }
}
