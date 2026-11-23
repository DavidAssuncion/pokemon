<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Forma/versión desbloqueada por un reclutado concreto.
 *
 * La forma original no se guarda aquí: siempre está disponible. Solo se
 * registran las formas alternativas que el jugador ha pagado a nivel 55.
 */
class ReclutadoForma extends Model
{
    protected $table = 'reclutado_formas';

    protected $fillable = [
        'reclutado_id',
        'pokemon_id',
        'unlocked_at',
    ];

    protected $casts = [
        'reclutado_id' => 'integer',
        'pokemon_id' => 'integer',
        'unlocked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Reclutado, $this>
     */
    public function reclutado(): BelongsTo
    {
        return $this->belongsTo(Reclutado::class, 'reclutado_id');
    }

    /**
     * @return BelongsTo<Pokemon, $this>
     */
    public function pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class, 'pokemon_id');
    }
}
