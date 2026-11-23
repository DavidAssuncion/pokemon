<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        'bonguri',
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
            ->withPivot('level', 'min_level', 'manual');
    }

    /**
     * Nivel mínimo de jugador requerido para acceder al nivel de exploración
     * dado, derivado de los spawns reales (`pokemon_habitat.min_level`).
     * `null` si el nivel no tiene spawns o ninguno impone restricción.
     */
    public function minLvlParaNivel(int $nivel): ?int
    {
        $minimo = DB::table('pokemon_habitat')
            ->where('habitat_id', $this->id)
            ->where('level', $nivel)
            ->min('min_level');

        return $minimo !== null ? (int) $minimo : null;
    }

    /**
     * ¿El hábitat está excluido de los spawns salvajes? (p. ej. legendarios,
     * que se sirven desde encuentros especiales).
     */
    public static function sinSpawns(int $habitatId): bool
    {
        /** @var list<int|string> $ids */
        $ids = config('habitats.habitats_sin_spawns', []);

        return in_array($habitatId, array_map(static fn (int|string $id): int => (int) $id, $ids), true);
    }

    /**
     * @return HasMany<ExploracionActiva, $this>
     */
    public function exploraciones(): HasMany
    {
        return $this->hasMany(ExploracionActiva::class);
    }
}
