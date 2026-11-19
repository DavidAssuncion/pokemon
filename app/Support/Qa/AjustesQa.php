<?php

declare(strict_types=1);

namespace App\Support\Qa;

use App\Models\PlayerInventory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Mutaciones atómicas del panel QA. Todo se acota por `user_id` explícito y
 * desactiva el global scope de `PlayerInventory`; nunca deja valores negativos.
 */
final class AjustesQa
{
    /** @var list<int> */
    public const CANTIDADES_EXPERIENCIA = [1000, 10000, 100000, 1000000];

    /** @var list<int> */
    public const CANTIDADES_CARAMELOS = [100, 255, 3072];

    /**
     * @return list<int>
     */
    public static function deltasExperiencia(): array
    {
        return self::conSignos(self::CANTIDADES_EXPERIENCIA);
    }

    /**
     * @return list<int>
     */
    public static function deltasCaramelos(): array
    {
        return self::conSignos(self::CANTIDADES_CARAMELOS);
    }

    public function ajustarExperiencia(int $userId, int $delta): ResultadoAjusteQa
    {
        return DB::transaction(function () use ($userId, $delta): ResultadoAjusteQa {
            $consulta = User::query()->whereKey($userId);
            $consulta->getQuery()->lockForUpdate();
            $usuario = $consulta->firstOrFail();

            $anterior = $usuario->experiencia;
            $actual = max(0, $anterior + $delta);

            if ($actual !== $anterior) {
                $usuario->experiencia = $actual;
                $usuario->save();
            }

            return new ResultadoAjusteQa('experiencia', $anterior, $actual, $actual - $anterior);
        });
    }

    public function ajustarInventario(int $userId, string $clave, int $delta): ResultadoAjusteQa
    {
        return DB::transaction(function () use ($userId, $clave, $delta): ResultadoAjusteQa {
            if ($delta < 0) {
                $fila = $this->bloquear($userId, $clave);

                if ($fila === null) {
                    // No existe la fila: una retirada no crea stock 0.
                    return new ResultadoAjusteQa($clave, 0, 0, 0);
                }

                return $this->aplicar($fila, $clave, $delta);
            }

            PlayerInventory::withoutUserScope()->firstOrCreate(
                ['user_id' => $userId, 'item_key' => $clave],
                ['cantidad' => 0],
            );

            $fila = $this->bloquear($userId, $clave);

            if ($fila === null) {
                return new ResultadoAjusteQa($clave, 0, 0, 0);
            }

            return $this->aplicar($fila, $clave, $delta);
        });
    }

    private function bloquear(int $userId, string $clave): ?Model
    {
        $consulta = PlayerInventory::withoutUserScope()
            ->where('user_id', $userId)
            ->where('item_key', $clave);

        // Query\Builder expone lockForUpdate como método real; el Builder de
        // Eloquent solo lo reenvía vía __call.
        $consulta->getQuery()->lockForUpdate();

        return $consulta->first();
    }

    private function aplicar(Model $fila, string $clave, int $delta): ResultadoAjusteQa
    {
        $anterior = (int) $fila->getAttribute('cantidad');
        $actual = max(0, $anterior + $delta);

        if ($actual !== $anterior) {
            $fila->setAttribute('cantidad', $actual);
            $fila->save();
        }

        return new ResultadoAjusteQa($clave, $anterior, $actual, $actual - $anterior);
    }

    /**
     * @param  list<int>  $cantidades
     * @return list<int>
     */
    private static function conSignos(array $cantidades): array
    {
        $negativos = array_map(
            static fn (int $cantidad): int => -$cantidad,
            array_reverse($cantidades),
        );

        return [...$negativos, ...$cantidades];
    }
}
