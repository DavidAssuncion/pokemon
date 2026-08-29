<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Multiplayer: acota todas las consultas del modelo al usuario autenticado.
 * Sin usuario autenticado el scope queda inactivo para no romper CLI/jobs.
 */
trait BelongsToUser
{
    public const USER_SCOPE_NAME = 'belongsToUser';

    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope(self::USER_SCOPE_NAME, function (Builder $builder): void {
            $userId = Auth::id();
            if ($userId === null) {
                return;
            }

            $builder->where($builder->getModel()->getTable().'.user_id', $userId);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Consulta sin el filtro por usuario (p. ej. el procesador de exploraciones).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeWithoutUserScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::USER_SCOPE_NAME);
    }
}
