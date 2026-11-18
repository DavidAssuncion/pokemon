<?php

declare(strict_types=1);

namespace Src\Forja\Domain\Ports;

use Src\Forja\Domain\Piezas\Collections\NidosConstruidosCollection;

/**
 * Puerto de lectura de los nidos construidos del jugador.
 *
 * Forja no conoce las tablas de Granjas: recibe una proyección tipada con
 * hábitat, familia y bonguri. Solo los nidos con nivel ≥ 1 son visibles.
 */
interface NidosConstruidos
{
    public function paraJugador(int $userId): NidosConstruidosCollection;

    /**
     * Nivel del nido propio de `($userId, $farmId, $evolutionChainId)` bloqueado
     * con `FOR UPDATE`, o `null` si no existe.
     *
     * Exige una transacción activa: el bloqueo se libera al commit o rollback.
     */
    public function bloquear(int $userId, int $farmId, int $evolutionChainId): ?int;
}
