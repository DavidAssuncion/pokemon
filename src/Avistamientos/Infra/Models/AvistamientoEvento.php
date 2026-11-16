<?php

declare(strict_types=1);

namespace Src\Avistamientos\Infra\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Fila de una ventana de avistamiento.
 *
 * La fila se inserta una sola vez por `window_index` y nunca se actualiza;
 * es el registro congelado de la selección de hábitat y familia.
 *
 * @property int $id
 * @property int $window_index
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $habitat_id
 * @property int $evolution_chain_id
 * @property float $factor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class AvistamientoEvento extends Model
{
    protected $table = 'avistamiento_eventos';

    protected $fillable = [
        'window_index',
        'starts_at',
        'ends_at',
        'habitat_id',
        'evolution_chain_id',
        'factor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_index' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'habitat_id' => 'integer',
            'evolution_chain_id' => 'integer',
            'factor' => 'float',
        ];
    }
}
