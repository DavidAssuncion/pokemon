<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Ports;

use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Exceptions\StockInsuficiente;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Puerto único de acceso al inventario del jugador (`player_inventory`).
 *
 * Centraliza la semántica atómica que comparten granja, forja y combate:
 * lectura de saldos, consumo con bloqueo y validación de stock, e incremento.
 * La implementación es responsable de aislar Eloquent y de no depender de Auth.
 */
interface InventarioJugador
{
    /**
     * Saldo actual de una clave de inventario. 0 si no existe la fila.
     *
     * @throws ItemDesconocido
     */
    public function saldo(int $userId, string $clave): int;

    /**
     * Saldos de varias claves en una sola lectura. Las claves sin fila
     * devuelven 0.
     *
     * @throws ItemDesconocido
     */
    public function saldos(int $userId, StringCollection $claves): MapaConteo;

    /**
     * Descuenta `$cantidad` unidades de una clave de forma atómica.
     *
     * @throws ItemDesconocido
     * @throws StockInsuficiente
     */
    public function consumir(int $userId, string $clave, int $cantidad, ?string $mensajeError = null): void;

    /**
     * Descuenta varias claves en una sola transacción, validando todos los
     * saldos antes de decrementar y bloqueando las filas en orden de clave.
     *
     * @throws ItemDesconocido
     * @throws StockInsuficiente
     */
    public function consumirVarios(int $userId, MapaConteo $claves, ?string $mensajeError = null): void;

    /**
     * Suma `$cantidad` unidades a una clave, creando la fila si no existe.
     *
     * @throws ItemDesconocido
     */
    public function incrementar(int $userId, string $clave, int $cantidad): void;

    /**
     * Garantiza que la fila `(user_id, item_key)` existe y la deja bloqueada
     * hasta el commit. Crear la fila es un no-op de valor (cantidad 0).
     *
     * Exige una transacción activa: el bloqueo se libera al commit o rollback.
     *
     * @throws ItemDesconocido
     */
    public function bloquearClave(int $userId, string $clave): void;
}
