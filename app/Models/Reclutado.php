<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Casts\ExpReclutado;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Src\Exploraciones\Domain\RolExploracion;

class Reclutado extends Model
{
    use BelongsToUser;

    protected $table = 'reclutados';

    protected $fillable = [
        'user_id', 'nombre', 'pokemon_id', 'exp', 'es_shiny', 'obj_equipados', 'movimientos', 'behavior',
    ];

    protected $casts = [
        'exp' => ExpReclutado::class,
        'obj_equipados' => 'array',
        'movimientos' => 'array',
        'es_shiny' => 'boolean',
        'behavior' => 'string',
    ];

    /**
     * Rol de exploración individual del reclutado. RFC: el behavior pasa de
     * vivir en team_members.behavior a una columna propia en reclutados.
     * Fallback a COMBATIENTE ante un valor inválido o vacío.
     */
    public function rol(): RolExploracion
    {
        return RolExploracion::tryFrom($this->behavior ?? '')
            ?? RolExploracion::COMBATIENTE;
    }

    /**
     * @return BelongsTo<Pokemon, $this>
     */
    public function pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class, 'pokemon_id');
    }

    /**
     * @return HasOne<TeamMember, $this>
     */
    public function teamMember(): HasOne
    {
        return $this->hasOne(TeamMember::class, 'pokemon_id');
    }

    /**
     * @return HasMany<Favorito, $this>
     */
    public function favoritos(): HasMany
    {
        return $this->hasMany(Favorito::class, 'reclutado_id');
    }
}
