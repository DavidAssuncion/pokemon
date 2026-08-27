<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvolutionChain extends Model
{
    public $timestamps = false;

    protected $table = 'evolution_chains';

    protected $fillable = [
        'id',
        'data',
    ];

    protected $casts = [
        'data' => 'json',
    ];

    /**
     * @return HasMany<Pokemon, $this>
     */
    public function pokemon(): HasMany
    {
        return $this->hasMany(Pokemon::class);
    }
}
