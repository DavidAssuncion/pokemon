<?php

declare(strict_types=1);

namespace Src\Shared\Infra\Inventario;

use App\Models\PlayerInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Exceptions\StockInsuficiente;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Ports\InventarioJugador;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Adaptador Eloquent del inventario del jugador.
 *
 * No depende del usuario autenticado: todas las consultas reciben el
 * `userId` explícito y desactivan el scope global de `PlayerInventory`.
 * El consumo bloquea las filas en orden de `item_key` para evitar
 * deadlocks entre operaciones concurrentes.
 */
final class InventarioJugadorEloquent implements InventarioJugador
{
    /**
     * Prefijos de claves heredadas del inventario de caramelos, que no
     * forman parte del catálogo de items (`baya:`/`bonguri:`/...).
     *
     * @var list<string>
     */
    private const PREFIJOS_CARAMELO = ['familia:', 'ev:'];

    public function saldo(int $userId, string $clave): int
    {
        $this->asegurarClaveValida($clave);

        $cantidad = PlayerInventory::withoutUserScope()
            ->where('user_id', $userId)
            ->where('item_key', $clave)
            ->value('cantidad');

        return (int) $cantidad;
    }

    public function saldos(int $userId, StringCollection $claves): MapaConteo
    {
        $clavesUnicas = array_values(array_unique($claves->all()));

        if ($clavesUnicas === []) {
            return MapaConteo::vacio();
        }

        foreach ($clavesUnicas as $clave) {
            $this->asegurarClaveValida($clave);
        }

        $saldos = array_fill_keys($clavesUnicas, 0);

        $items = PlayerInventory::withoutUserScope()
            ->where('user_id', $userId)
            ->where(function (Builder $query) use ($clavesUnicas): void {
                foreach ($clavesUnicas as $clave) {
                    $query->orWhere('item_key', $clave);
                }
            })
            ->get();

        foreach ($items as $item) {
            $clave = (string) $item->getAttribute('item_key');
            $saldos[$clave] = (int) $item->getAttribute('cantidad');
        }

        return MapaConteo::desdeArray($saldos);
    }

    public function consumir(int $userId, string $clave, int $cantidad, ?string $mensajeError = null): void
    {
        if ($cantidad <= 0) {
            return;
        }

        $this->consumirVarios($userId, MapaConteo::vacio()->con($clave, $cantidad), $mensajeError);
    }

    public function consumirVarios(int $userId, MapaConteo $claves, ?string $mensajeError = null): void
    {
        $requeridos = $claves->toArray();

        foreach (array_keys($requeridos) as $clave) {
            $this->asegurarClaveValida($clave);
        }

        $requeridos = array_filter(
            $requeridos,
            static fn (int $cantidad): bool => $cantidad > 0,
        );

        if ($requeridos === []) {
            return;
        }

        ksort($requeridos, SORT_STRING);

        DB::transaction(function () use ($userId, $requeridos, $mensajeError): void {
            $consulta = PlayerInventory::withoutUserScope()
                ->where('user_id', $userId)
                ->where(function (Builder $query) use ($requeridos): void {
                    foreach (array_keys($requeridos) as $clave) {
                        $query->orWhere('item_key', $clave);
                    }
                });

            // Query\Builder expone orderBy/lockForUpdate como métodos reales;
            // el Builder de Eloquent solo los reenvía. El bloqueo se aplica
            // sobre la misma consulta que hidrata los modelos.
            $consulta->getQuery()
                ->orderBy('item_key')
                ->lockForUpdate();

            $items = $consulta->get();

            /** @var array<string, PlayerInventory> $porClave */
            $porClave = [];

            foreach ($items as $item) {
                $porClave[(string) $item->getAttribute('item_key')] = $item;
            }

            foreach ($requeridos as $clave => $cantidad) {
                $item = $porClave[$clave] ?? null;
                $disponible = $item !== null ? (int) $item->getAttribute('cantidad') : 0;

                if ($disponible < $cantidad) {
                    throw StockInsuficiente::para($clave, $cantidad, $disponible, $mensajeError);
                }
            }

            foreach ($requeridos as $clave => $cantidad) {
                $item = $porClave[$clave] ?? null;

                if ($item === null) {
                    continue;
                }

                $item->decrement('cantidad', $cantidad);
            }
        });
    }

    public function incrementar(int $userId, string $clave, int $cantidad): void
    {
        $this->asegurarClaveValida($clave);

        if ($cantidad <= 0) {
            return;
        }

        $ahora = now();

        // `insertOrIgnore` evita la violación de unique cuando dos procesos
        // insertan la misma clave a la vez; el increment posterior es atómico.
        PlayerInventory::withoutUserScope()->getQuery()->insertOrIgnore([
            'user_id' => $userId,
            'item_key' => $clave,
            'cantidad' => 0,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        PlayerInventory::withoutUserScope()
            ->where('user_id', $userId)
            ->where('item_key', $clave)
            ->increment('cantidad', $cantidad);
    }

    /**
     * @throws ItemDesconocido
     */
    private function asegurarClaveValida(string $clave): void
    {
        if (CatalogoItems::existe($clave) || $this->esClaveCaramelo($clave)) {
            return;
        }

        throw ItemDesconocido::paraClave($clave);
    }

    private function esClaveCaramelo(string $clave): bool
    {
        foreach (self::PREFIJOS_CARAMELO as $prefijo) {
            if (! str_starts_with($clave, $prefijo)) {
                continue;
            }

            return ctype_digit(substr($clave, strlen($prefijo)));
        }

        return false;
    }
}
