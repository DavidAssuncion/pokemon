<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// atajo: modelo en app/Models, mover a src/Equipos/Infra/Models al migrar el módulo
class RecruitGear extends Model
{
    protected $table = 'recruit_gear';

    protected $fillable = [
        'slot',
        'item_key',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'slot' => 'integer',
    ];

    /**
     * @return BelongsTo<Reclutado, $this>
     */
    public function reclutado(): BelongsTo
    {
        return $this->belongsTo(Reclutado::class, 'reclutado_id');
    }
}
