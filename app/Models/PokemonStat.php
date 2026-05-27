<?php

namespace App\Models;

use App\Enums\StatEnum;
use Illuminate\Database\Eloquent\Model;

class PokemonStat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pokemon_id',
        'stat',
        'base_stat',
        'effort',
    ];

    protected $casts = [
        'stat' => StatEnum::class,
    ];

    public function pokemon()
    {
        return $this->belongsTo(Pokemon::class);
    }

    /**
     * Obtener el nombre en español del stat
     */
    public function getStatNombreAttribute(): string
    {
        return $this->stat->label();
    }
}
