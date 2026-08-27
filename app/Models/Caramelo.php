<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Caramelo extends Model
{
    protected $table = 'caramelos';

    protected $fillable = [
        'evolution_chain_id',
        'cantidad',
    ];

    /**
     * @return BelongsTo<EvolutionChain, $this>
     */
    public function evolutionChain(): BelongsTo
    {
        return $this->belongsTo(EvolutionChain::class);
    }
}
