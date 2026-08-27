<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habitat extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'province_id',
        'name',
        'pokemons',
    ];

    protected $casts = [
        'pokemons' => 'array',
    ];

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsToMany<Pokemon, $this>
     */
    public function pokemon(): BelongsToMany
    {
        return $this->belongsToMany(Pokemon::class, 'pokemon_habitat')
            ->withPivot('level');
    }

    /**
     * @return HasMany<ExploracionActiva, $this>
     */
    public function exploraciones(): HasMany
    {
        return $this->hasMany(ExploracionActiva::class);
    }
}
