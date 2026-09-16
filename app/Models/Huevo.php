<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Casts\StatsReclutado;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Huevo en gestación/incubación/eclosión. BelongsToUser → anti-IDOR por scope.
 * Estado calculado on-read (no stored state): created_at + capture_rate + hatch.
 */
class Huevo extends Model
{
    use BelongsToUser;

    protected $table = 'huevos';

    protected $fillable = [
        'user_id', 'pokemon_id', 'evolution_chain_id', 'capture_rate', 'hatch', 'es_shiny', 'ivs', 'eclosionado_en',
    ];

    protected $casts = [
        'es_shiny' => 'boolean',
        'ivs' => StatsReclutado::class,
        'eclosionado_en' => 'datetime',
    ];

    /** @return BelongsTo<Pokemon, $this> */
    public function pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class, 'pokemon_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
