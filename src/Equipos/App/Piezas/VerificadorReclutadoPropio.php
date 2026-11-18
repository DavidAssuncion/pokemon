<?php

declare(strict_types=1);

namespace Src\Equipos\App\Piezas;

use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Exceptions\RecursoNoExiste;

/**
 * Anti-IDOR: garantiza que un reclutado pertenece al jugador autenticado.
 *
 * Compartido por los casos de uso de piezas para que el 404 sea idéntico en
 * lectura, equipado y desequipado. Se consulta por `DB::table` con
 * `where('user_id')` explícito, sin depender del global scope del modelo
 * (inactivo fuera de HTTP).
 */
final class VerificadorReclutadoPropio
{
    /**
     * @throws RecursoNoExiste
     */
    public function asegurar(int $userId, int $reclutadoId): void
    {
        $existe = DB::table('reclutados')
            ->where('id', $reclutadoId)
            ->where('user_id', $userId)
            ->exists();

        if (! $existe) {
            throw new RecursoNoExiste('El reclutado no existe.');
        }
    }

    /**
     * Deja la fila del reclutado bloqueada hasta el commit.
     *
     * @throws RecursoNoExiste
     */
    public function bloquear(int $userId, int $reclutadoId): void
    {
        $reclutado = DB::table('reclutados')
            ->where('id', $reclutadoId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($reclutado === null) {
            throw new RecursoNoExiste('El reclutado no existe.');
        }
    }
}
