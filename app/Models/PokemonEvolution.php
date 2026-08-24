<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PokemonEvolution extends Model
{
    public $timestamps = false;

    protected $table = 'pokemon_evolution';

    protected $fillable = [
        'evolution_chain_id',
        'evolved_species_id',
        'evolves_from_species_id',
        'minimum_level',
    ];

    public function evolvedSpecies()
    {
        return $this->belongsTo(Pokemon::class, 'evolved_species_id');
    }
}
