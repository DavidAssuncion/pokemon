<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarameloEv extends Model
{
    protected $table = 'caramelos_ev';

    protected $fillable = [
        'stat',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];
}
