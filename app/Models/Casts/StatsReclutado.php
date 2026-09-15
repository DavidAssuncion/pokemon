<?php

declare(strict_types=1);

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Src\Pokemon\Domain\Stats\StatsValue;

/**
 * Custom cast para columnas JSON de StatsValue (ivs/evs) en reclutados y huevos.
 *
 * Legacy rows (null/empty) → zeros (StatsValue(0,0,0,0,0,0)).
 * DB stores canonical short keys: {hp, atk, def, spAtk, spDef, speed}.
 *
 * @implements CastsAttributes<StatsValue, mixed>
 */
final class StatsReclutado implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): StatsValue
    {
        return self::fromRaw($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $stats = $value instanceof StatsValue ? $value : self::fromRaw($value);

        return json_encode($stats->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function fromRaw(mixed $raw): StatsValue
    {
        if ($raw instanceof StatsValue) {
            return $raw;
        }

        if ($raw === null || $raw === '') {
            return new StatsValue(0, 0, 0, 0, 0, 0);
        }

        $data = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($data)) {
            return new StatsValue(0, 0, 0, 0, 0, 0);
        }

        return StatsValue::desdeArray($data);
    }
}
