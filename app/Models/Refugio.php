<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Refugio de una familia evolutiva dentro de una granja. Nivel 0..5:
 * multiplica aparición/captura; "condiciones insalubres" invierte el efecto.
 */
class Refugio extends Model
{
    protected $table = 'shelters';

    protected $fillable = [
        'farm_id',
        'evolution_chain_id',
        'level',
        'unhealthy_conditions',
    ];

    protected $casts = [
        'farm_id' => 'integer',
        'evolution_chain_id' => 'integer',
        'level' => 'integer',
        'unhealthy_conditions' => 'boolean',
    ];

    /**
     * @return BelongsTo<Granja, $this>
     */
    public function granja(): BelongsTo
    {
        return $this->belongsTo(Granja::class, 'farm_id');
    }

    /**
     * @return HasMany<Complemento, $this>
     */
    public function complementos(): HasMany
    {
        return $this->hasMany(Complemento::class, 'shelter_id');
    }
}
