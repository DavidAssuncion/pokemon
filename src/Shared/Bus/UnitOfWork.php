<?php

declare(strict_types=1);

namespace Src\Shared\Bus;

/**
 * Frontera de transacciones para el CommandBus (infraestructura genérica).
 */
interface UnitOfWork
{
    /**
     * Ejecuta $callback dentro de una transacción; si lanza, revierte
     * automáticamente y re-lanza la excepción.
     */
    public function transaction(callable $callback): mixed;

    /**
     * Registra $callback para ejecutarse solo tras un commit exitoso.
     * Nunca se ejecuta si la transacción hace rollback.
     */
    public function afterCommit(callable $callback): void;
}
