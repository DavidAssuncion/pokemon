<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExploracionActiva extends Model
{
    protected $table = 'exploraciones_activas';

    protected $fillable = [
        'equipo_id', 'eventos', 'inicio_exploracion', 'llegada_destino', 'regreso',
    ];

    protected $casts = [
        'eventos' => 'array',
        'inicio_exploracion' => 'datetime',
        'llegada_destino' => 'datetime',
        'regreso' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'equipo_id');
    }
}
