<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pokedex extends Model
{
    use BelongsToUser;

    protected $table = 'pokedex';

    protected $fillable = [
        'user_id',
        'pokemon_id',
        'visto',
        'atrapado',
    ];

    protected $casts = [
        'visto' => 'boolean',
        'atrapado' => 'boolean',
    ];

    /**
     * @return BelongsTo<Pokemon, $this>
     */
    public function pokemon(): BelongsTo
    {
        return $this->belongsTo(Pokemon::class);
    }
}
