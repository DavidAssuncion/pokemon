<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo de movimientos. La lógica de dominio vive en
 * Src\Battle\Domain\Movimiento; este modelo solo persiste y castea JSON.
 */
class Move extends Model
{
    protected $table = 'moves';

    protected $fillable = [
        'slug', 'num', 'name', 'description', 'type_id', 'category', 'base_power', 'nivel_requerido',
        'accuracy', 'priority', 'target', 'crit_ratio', 'will_crit', 'status_effect',
        'status_chance', 'multihit_min', 'multihit_max', 'multiaccuracy',
        'drain_percent', 'recoil_percent', 'heal_percent', 'effect_key',
        'flags', 'secondaries', 'self_effects', 'data',
    ];

    protected $casts = [
        'num' => 'integer',
        'type_id' => 'integer',
        'base_power' => 'integer',
        'nivel_requerido' => 'integer',
        'accuracy' => 'integer',
        'priority' => 'integer',
        'crit_ratio' => 'integer',
        'will_crit' => 'boolean',
        'status_chance' => 'integer',
        'multihit_min' => 'integer',
        'multihit_max' => 'integer',
        'multiaccuracy' => 'boolean',
        'drain_percent' => 'integer',
        'recoil_percent' => 'integer',
        'heal_percent' => 'integer',
        'flags' => 'array',
        'secondaries' => 'array',
        'self_effects' => 'array',
        'data' => 'array',
    ];

    /**
     * @return BelongsToMany<Pokemon, $this>
     */
    public function pokemons(): BelongsToMany
    {
        return $this->belongsToMany(Pokemon::class, 'pokemon_moves')
            ->withPivot(['slot', 'source'])
            ->withTimestamps()
            ->orderByPivot('slot');
    }
}
