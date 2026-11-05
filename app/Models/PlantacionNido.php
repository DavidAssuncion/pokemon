<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plantación persistente de una baya en un hueco de nido (`nest_plantings`).
 * Una fila por hueco ocupado; el stock se calcula al leer.
 *
 * @property int $id
 * @property int $nest_id
 * @property int $slot_index
 * @property string $berry_key
 * @property \Illuminate\Support\Carbon $planted_at
 * @property int $harvest_count
 */
class PlantacionNido extends Model
{
    protected $table = 'nest_plantings';

    protected $fillable = [
        'nest_id',
        'slot_index',
        'berry_key',
        'planted_at',
        'harvest_count',
    ];

    protected $casts = [
        'nest_id' => 'integer',
        'slot_index' => 'integer',
        'planted_at' => 'datetime',
        'harvest_count' => 'integer',
    ];

    /**
     * @return BelongsTo<Nido, $this>
     */
    public function nido(): BelongsTo
    {
        return $this->belongsTo(Nido::class, 'nest_id');
    }
}
