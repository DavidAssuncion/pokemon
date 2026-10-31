<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nido de una familia evolutiva dentro de una granja. Nivel 0..5: cada nivel
 * mejora el rango de IVs de los huevos y reduce su tiempo de gestación/eclosión.
 */
class Nido extends Model
{
    protected $table = 'nests';

    protected $fillable = [
        'farm_id',
        'evolution_chain_id',
        'level',
    ];

    protected $casts = [
        'farm_id' => 'integer',
        'evolution_chain_id' => 'integer',
        'level' => 'integer',
    ];

    /**
     * @return BelongsTo<Granja, $this>
     */
    public function granja(): BelongsTo
    {
        return $this->belongsTo(Granja::class, 'farm_id');
    }
}
