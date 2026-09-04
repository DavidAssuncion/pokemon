<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorito extends Model
{
    use BelongsToUser;

    protected $table = 'favoritos';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'reclutado_id',
        'habitat_id',
    ];

    /**
     * @return BelongsTo<Reclutado, $this>
     */
    public function reclutado(): BelongsTo
    {
        return $this->belongsTo(Reclutado::class);
    }

    /**
     * @return BelongsTo<Habitat, $this>
     */
    public function habitat(): BelongsTo
    {
        return $this->belongsTo(Habitat::class);
    }

    /**
     * Favoritos globales (habitat_id NULL) — para la página /equipos.
     *
     * @param  Builder<Favorito>  $query
     * @return Builder<Favorito>
     */
    public function scopeGlobales(Builder $query): Builder
    {
        return $query->whereNull('habitat_id');
    }

    /**
     * Favoritos para un hábitat específico — para la vista /habitats/{habitat}.
     *
     * @param  Builder<Favorito>  $query
     * @return Builder<Favorito>
     */
    public function scopeParaHabitat(Builder $query, int $habitatId): Builder
    {
        return $query->where('habitat_id', $habitatId);
    }

    /**
     * Alterna un favorito: si existe lo elimina, si no lo crea.
     *
     * @return bool true si se añadió, false si se eliminó
     */
    public static function toggle(int $userId, int $reclutadoId, ?int $habitatId = null): bool
    {
        $favorito = static::withoutGlobalScope('belongsToUser')
            ->where('user_id', $userId)
            ->where('reclutado_id', $reclutadoId)
            ->where('habitat_id', $habitatId)
            ->first();

        if ($favorito !== null) {
            $favorito->delete();

            return false;
        }

        static::withoutGlobalScope('belongsToUser')->create(['user_id' => $userId, 'reclutado_id' => $reclutadoId, 'habitat_id' => $habitatId]);

        return true;
    }

    /**
     * Cuenta los favoritos globales de un usuario.
     */
    public static function countGlobales(int $userId): int
    {
        return static::withoutGlobalScope('belongsToUser')
            ->globales()
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Cuenta los favoritos de un usuario para un hábitat concreto.
     */
    public static function countParaHabitat(int $userId, int $habitatId): int
    {
        return static::withoutGlobalScope('belongsToUser')
            ->paraHabitat($habitatId)
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Comprueba si un user ya tiene marcado este reclutado en el alcance dado.
     */
    public static function esFavorito(int $userId, int $reclutadoId, ?int $habitatId = null): bool
    {
        return static::withoutGlobalScope('belongsToUser')
            ->where('user_id', $userId)
            ->where('reclutado_id', $reclutadoId)
            ->where('habitat_id', $habitatId)
            ->exists();
    }
}
