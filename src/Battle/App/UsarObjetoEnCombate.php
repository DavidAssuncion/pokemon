<?php

declare(strict_types=1);

namespace Src\Battle\App;

use Illuminate\Support\Facades\DB;
use Src\Battle\Domain\AccionObjeto;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\UsoObjetos\ResultadoUsoObjeto;
use Src\Battle\Domain\UsoObjetos\ServicioUsoObjetos;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Exceptions\StockInsuficiente;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Caso de uso de uso manual de objetos en combate.
 *
 * Valida primero y, en una única transacción, consume exactamente una unidad
 * y aplica el efecto. Un fallo de stock no consume ni aplica nada; un fallo
 * al aplicar revierte el consumo.
 */
final readonly class UsarObjetoEnCombate
{
    public function __construct(
        private InventarioJugador $inventario,
        private ServicioUsoObjetos $servicio,
    ) {
    }

    /**
     * Valida la acción sin consumir inventario. La usa la presentación antes
     * de fijar la acción pendiente.
     */
    public function validar(AgregadoBatalla $battle, AccionObjeto $accion): ?string
    {
        return $this->servicio->validar($accion, $battle);
    }

    public function handle(int $userId, AgregadoBatalla $battle, AccionObjeto $accion): ResultadoUsoObjeto
    {
        $motivo = $this->validar($battle, $accion);
        if ($motivo !== null) {
            return ResultadoUsoObjeto::fallo($accion->clave, $motivo);
        }

        try {
            return DB::transaction(function () use ($userId, $battle, $accion): ResultadoUsoObjeto {
                $this->inventario->consumir($userId, $accion->clave, 1);

                // Dentro de la misma transacción: si aplicar() falla, el
                // consumo de inventario se revierte.
                return $this->servicio->aplicar($accion, $battle);
            });
        } catch (StockInsuficiente|ItemDesconocido $e) {
            return ResultadoUsoObjeto::fallo($accion->clave, $e->getMessage());
        }
    }
}
