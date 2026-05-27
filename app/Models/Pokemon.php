<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pokemon extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'pokemon';

    protected $fillable = [
        'name',
        'species_id',
        'capture_rate',
        'base_experience',
        'height',
        'weight',
        'hatch',
    ];
}
