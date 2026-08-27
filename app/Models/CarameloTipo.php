<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarameloTipo extends Model
{
    protected $table = 'caramelos_tipo';

    protected $fillable = [
        'tipo',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];
}
