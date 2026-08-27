<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReclutadoExpTipo extends Model
{
    protected $table = 'reclutados_exp_tipo';

    protected $fillable = [
        'reclutado_id',
        'tipo',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    /**
     * @return BelongsTo<Reclutado, $this>
     */
    public function reclutado(): BelongsTo
    {
        return $this->belongsTo(Reclutado::class, 'reclutado_id');
    }
}
