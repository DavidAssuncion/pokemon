<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvolutionChain extends Model
{
    public $timestamps = false;

    protected $table = 'evolution_chains';

    protected $fillable = [
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
