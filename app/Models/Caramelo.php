<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caramelo extends Model
{
    protected $table = 'caramelos';

    protected $fillable = [
        'evolution_chain_id',
        'cantidad',
    ];
}
