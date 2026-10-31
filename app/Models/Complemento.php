<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Complemento EV de un stat para una familia. Nivel 0..5 = 64/128/256/384/512
 * EV que se aplican a todos los reclutados de la familia.
 */
class Complemento extends Model
{
    protected $table = 'complements';

    protected $fillable = [
        'shelter_id',
        'stat',
        'level',
    ];

    protected $casts = [
        'shelter_id' => 'integer',
        'stat' => StatEnum::class,
        'level' => 'integer',
    ];

    /**
     * @return BelongsTo<Refugio, $this>
     */
    public function refugio(): BelongsTo
    {
        return $this->belongsTo(Refugio::class, 'shelter_id');
    }
}
