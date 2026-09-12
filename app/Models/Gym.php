<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gym extends Model
{
    protected $table = 'gyms';

    protected $fillable = [
        'slug',
        'medalla',
        'tipo',
        'nivel_minimo',
    ];

    protected $casts = [
        'tipo' => 'integer',
        'nivel_minimo' => 'integer',
    ];

    /**
     * @return HasMany<GymStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(GymStage::class, 'gym_id');
    }
}
