<?php

namespace App\Models;

use App\Enums\TipoEnum;
use Illuminate\Database\Eloquent\Model;

class PokemonType extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pokemon_id',
        'type',
        'slot',
    ];

    protected $casts = [
        'type' => TipoEnum::class,
    ];

    public function pokemon()
    {
        return $this->belongsTo(Pokemon::class);
    }

    /**
     * Obtener el nombre en español del tipo
     */
    public function getTipoNombreAttribute(): string
    {
        return $this->type->label();
    }
}
