<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo de habilidades. El comportamiento de combate vive en
 * `FabricaEfectos`; `data.effect_keys` guarda las claves canónicas que el
 * motor debe instanciar.
 */
class Ability extends Model
{
    protected $table = 'abilities';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'effect',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * @return BelongsToMany<Pokemon, $this>
     */
    public function pokemons(): BelongsToMany
    {
        return $this->belongsToMany(Pokemon::class, 'ability_pokemon')
            ->withPivot(['slot', 'source'])
            ->withTimestamps()
            ->orderByPivot('slot');
    }
}
