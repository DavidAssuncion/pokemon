<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitatFavorito extends Model
{
    use BelongsToUser;

    public const MAX_FAVORITOS = 6;

    protected $table = 'habitat_favoritos';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'habitat_id',
    ];

    /**
     * @return BelongsTo<Habitat, $this>
     */
    public function habitat(): BelongsTo
    {
        return $this->belongsTo(Habitat::class);
    }
}
