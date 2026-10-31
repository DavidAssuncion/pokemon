<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Experiencia de Sendas de un tipo en la cuenta del jugador.
 * No genera caramelos: sólo alimenta el nivel de la Senda.
 */
class PlayerTypePath extends Model
{
    use BelongsToUser;

    protected $table = 'player_type_paths';

    protected $fillable = [
        'user_id',
        'type_id',
        'exp',
        'level',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'type_id' => 'integer',
        'exp' => 'integer',
        'level' => 'integer',
    ];
}
