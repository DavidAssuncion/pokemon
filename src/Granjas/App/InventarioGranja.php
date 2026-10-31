<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Enums\StatEnum;
use App\Models\PlayerInventory;
use App\Support\ItemCatalogo;
use Src\Granjas\Domain\Collections\CaramelosEvCollection;
use Src\Granjas\Domain\Collections\CaramelosFamiliaCollection;
use Src\Granjas\Domain\DataTransferObjects\CarameloEv;
use Src\Granjas\Domain\DataTransferObjects\CarameloFamilia;
use Src\Granjas\Domain\DataTransferObjects\InventarioCaramelosGranja;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Lectura y consumo de caramelos para las granjas. Reutiliza el inventario
 * unificado (`player_inventory`) con las claves `familia:{chain}` y `ev:{stat}`.
 */
final class InventarioGranja
{
    public function resumen(int $userId): InventarioCaramelosGranja
    {
        $familia = new CaramelosFamiliaCollection();
        $ev = new CaramelosEvCollection();

        $items = PlayerInventory::withoutUserScope()
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->where('item_key', 'like', 'familia:%')
                    ->orWhere('item_key', 'like', 'ev:%');
            })
            ->get();

        foreach ($items as $item) {
            $key = $item->item_key;

            if (str_starts_with($key, 'familia:')) {
                $familia->add(new CarameloFamilia(
                    chainId: (int) substr($key, strlen('familia:')),
                    cantidad: $item->cantidad,
                ));
                continue;
            }

            if (str_starts_with($key, 'ev:')) {
                $stat = StatEnum::tryFrom((int) substr($key, strlen('ev:')));

                if ($stat !== null) {
                    $ev->add(new CarameloEv(stat: $stat, cantidad: $item->cantidad));
                }
            }
        }

        return new InventarioCaramelosGranja(familia: $familia, ev: $ev);
    }

    public function saldoFamilia(int $userId, int $evolutionChainId): int
    {
        return $this->saldo($userId, ItemCatalogo::keyFamilia($evolutionChainId));
    }

    public function saldoEv(int $userId, int $stat): int
    {
        return $this->saldo($userId, ItemCatalogo::keyEv($stat));
    }

    public function consumirFamilia(int $userId, int $evolutionChainId, int $cantidad): void
    {
        $this->consumir(
            $userId,
            ItemCatalogo::keyFamilia($evolutionChainId),
            $cantidad,
            'No tienes suficientes caramelos de esta familia',
        );
    }

    public function consumirEv(int $userId, int $stat, int $cantidad): void
    {
        $this->consumir(
            $userId,
            ItemCatalogo::keyEv($stat),
            $cantidad,
            'No tienes suficientes caramelos EV de ese stat',
        );
    }

    private function saldo(int $userId, string $itemKey): int
    {
        $item = PlayerInventory::withoutUserScope()
            ->where('user_id', $userId)
            ->where('item_key', $itemKey)
            ->first();

        return $item !== null ? $item->cantidad : 0;
    }

    private function consumir(int $userId, string $itemKey, int $cantidad, string $mensaje): void
    {
        if ($cantidad <= 0) {
            return;
        }

        $item = PlayerInventory::withoutUserScope()
            ->where('user_id', $userId)
            ->where('item_key', $itemKey)
            ->lockForUpdate()
            ->first();

        if ($item === null || $item->cantidad < $cantidad) {
            throw new ViolacionReglaNegocio($mensaje);
        }

        $item->decrement('cantidad', $cantidad);
    }
}
