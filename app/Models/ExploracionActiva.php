<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExploracionActiva extends Model
{
    protected $table = 'exploraciones_activas';

    protected $fillable = [
        'equipo_id',
        'habitat_id',
        'nivel',
        'duracion_horas',
        'hora_limite',
        'indefinido',
        'eventos',
        'inicio_exploracion',
        'llegada_destino',
        'regreso',
    ];

    protected $casts = [
        'eventos' => 'array',
        'inicio_exploracion' => 'datetime',
        'llegada_destino' => 'datetime',
        'regreso' => 'datetime',
        'indefinido' => 'boolean',
    ];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'equipo_id');
    }

    /**
     * @return BelongsTo<Habitat, $this>
     */
    public function habitat(): BelongsTo
    {
        return $this->belongsTo(Habitat::class);
    }
}
