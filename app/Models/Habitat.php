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
        'peligro',
        'mazmorra',
    ];

    protected $casts = [
        'pokemons' => 'array',
        'peligro' => 'integer',
        'mazmorra' => 'array',
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
     * Nivel mínimo de jugador requerido para explorar este hábitat en el
     * nivel de exploración dado (columnas min_lvl_1/2/3; null = sin restricción).
     */
    public function minLvlParaNivel(int $nivel): ?int
    {
        return $this->getAttribute('min_lvl_'.$nivel);
    }

    /**
     * @return HasMany<ExploracionActiva, $this>
     */
    public function exploraciones(): HasMany
    {
        return $this->hasMany(ExploracionActiva::class);
    }
}
