<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nodo de una Senda de tipo. Cada movimiento aprendible tiene exactamente un
 * nodo y el nivel requerido de la Senda se guarda aquí; el coste se calcula
 * con App\Support\SendaCostes.
 */
class TypePathNode extends Model
{
    protected $table = 'type_path_nodes';

    protected $fillable = [
        'type_id',
        'move_id',
        'category',
        'required_level',
        'sort_order',
    ];

    protected $casts = [
        'type_id' => 'integer',
        'move_id' => 'integer',
        'required_level' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<Move, $this>
     */
    public function move(): BelongsTo
    {
        return $this->belongsTo(Move::class);
    }
}
