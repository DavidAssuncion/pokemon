<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pokemon extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'pokemon';

    protected $fillable = [
        'id',
        'name',
        'species_id',
        'capture_rate',
        'base_experience',
        'height',
        'weight',
        'hatch',
        'evolution_chain_id',
    ];

    /**
     * @return BelongsToMany<Habitat, $this>
     */
    public function habitats(): BelongsToMany
    {
        return $this->belongsToMany(Habitat::class, 'pokemon_habitat')
            ->withPivot('level');
    }

    /**
     * @return HasMany<PokemonStat, $this>
     */
    public function stats(): HasMany
    {
        return $this->hasMany(PokemonStat::class);
    }

    /**
     * @return HasMany<PokemonType, $this>
     */
    public function types(): HasMany
    {
        return $this->hasMany(PokemonType::class);
    }
}
