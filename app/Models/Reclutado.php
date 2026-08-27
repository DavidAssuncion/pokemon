<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reclutado extends Model
{
    protected $table = 'reclutados';

    protected $fillable = [
        'nombre', 'pokemon_id', 'exp', 'es_shiny', 'obj_equipados', 'movimientos',
    ];

    protected $casts = [
        'exp' => 'array',
        'obj_equipados' => 'array',
        'movimientos' => 'array',
        'es_shiny' => 'boolean',
    ];

    public function pokemon()
    {
        return $this->belongsTo(Pokemon::class, 'pokemon_id');
    }

    public function teamMember()
    {
        return $this->hasOne(TeamMember::class, 'pokemon_id');
    }
}
