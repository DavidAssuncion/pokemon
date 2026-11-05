<?php

declare(strict_types=1);

namespace Src\Granjas\App;

use App\Enums\StatEnum;
use App\Models\PlayerInventory;
use App\Support\ItemCatalogo;
use Src\Granjas\Domain\Collections\BayasGranjaCollection;
use Src\Granjas\Domain\Collections\CaramelosEvCollection;
use Src\Granjas\Domain\Collections\CaramelosFamiliaCollection;
use Src\Granjas\Domain\DataTransferObjects\BayaGranja;
use Src\Granjas\Domain\DataTransferObjects\CarameloEv;
use Src\Granjas\Domain\DataTransferObjects\CarameloFamilia;
use Src\Granjas\Domain\DataTransferObjects\InventarioCaramelosGranja;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Items\ClaveBaya;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Lectura y consumo de caramelos para las granjas. Reutiliza el inventario
 * unificado (`player_inventory`) con las claves `familia:{chain}` y `ev:{stat}`.
 *
 * El consumo delega en el puerto compartido `InventarioJugador`; la lectura
 * de caramelos y el resumen de bayas se resuelven aquí porque son propios
 * del contexto de granja.
 */
final class InventarioGranja
{
    public function __construct(
        private readonly InventarioJugador $inventario,
    ) {
    }

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

    /**
     * Bayas del inventario con existencias. Solo devuelve cantidades > 0.
     */
    public function resumenBayas(int $userId): BayasGranjaCollection
    {
        $saldos = $this->inventario->saldos($userId, $this->clavesBayas());
        $bayas = new BayasGranjaCollection();

        foreach (ClaveBaya::cases() as $baya) {
            $cantidad = $saldos->obtener($baya->clave());

            if ($cantidad <= 0) {
                continue;
            }

            $bayas->add($this->bayaGranja($baya, $cantidad));
        }

        return $bayas;
    }

    /**
     * Catálogo completo de las 36 bayas con las existencias del jugador.
     * Incluye las agotadas con cantidad 0 para que la UI pueda mostrarlas.
     */
    public function catalogoBayas(int $userId): BayasGranjaCollection
    {
        $saldos = $this->inventario->saldos($userId, $this->clavesBayas());
        $bayas = new BayasGranjaCollection();

        foreach (ClaveBaya::cases() as $baya) {
            $bayas->add($this->bayaGranja($baya, $saldos->obtener($baya->clave())));
        }

        return $bayas;
    }

    private function clavesBayas(): StringCollection
    {
        $claves = new StringCollection();

        foreach (ClaveBaya::cases() as $baya) {
            $claves->add($baya->clave());
        }

        return $claves;
    }

    private function bayaGranja(ClaveBaya $baya, int $cantidad): BayaGranja
    {
        return new BayaGranja(
            clave: $baya->clave(),
            nombre: $baya->nombre(),
            icono: $baya->archivoIcono(),
            cantidad: $cantidad,
            grupo: CatalogoItems::grupoDeBaya($baya),
        );
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
        $this->inventario->consumir(
            $userId,
            ItemCatalogo::keyFamilia($evolutionChainId),
            $cantidad,
            'No tienes suficientes caramelos de esta familia',
        );
    }

    public function consumirEv(int $userId, int $stat, int $cantidad): void
    {
        $this->inventario->consumir(
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
}
