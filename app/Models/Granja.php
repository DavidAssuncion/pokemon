<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Granja de un jugador en un hábitat. Agrupa nidos, refugios y complementos
 * de las familias asignadas al hábitat.
 */
class Granja extends Model
{
    use BelongsToUser;

    protected $table = 'farms';

    protected $fillable = [
        'user_id',
        'habitat_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'habitat_id' => 'integer',
    ];

    /**
     * @return BelongsTo<Habitat, $this>
     */
    public function habitat(): BelongsTo
    {
        return $this->belongsTo(Habitat::class);
    }

    /**
     * @return HasMany<Nido, $this>
     */
    public function nidos(): HasMany
    {
        return $this->hasMany(Nido::class, 'farm_id');
    }

    /**
     * @return HasMany<Refugio, $this>
     */
    public function refugios(): HasMany
    {
        return $this->hasMany(Refugio::class, 'farm_id');
    }
}
