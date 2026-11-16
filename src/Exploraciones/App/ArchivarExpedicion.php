<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\Exceptions\RecursoNoExiste;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;

/**
 * Archiva una expedición completada del usuario.
 *
 * Reglas:
 * - 404 si no existe o pertenece a otro usuario.
 * - 422 si sigue activa (`regreso` null) o está cancelada.
 * - Idempotente: una expedición ya archivada se devuelve sin tocar la fecha.
 * - Transacción + `lockForUpdate` para no competir con el procesador ni con
 *   `cerrar`.
 */
final class ArchivarExpedicion
{
    public function __construct(
        private readonly ConsultorEstadoExpedicion $consultor,
    ) {
    }

    public function ejecutar(int $userId, int $expedicionId): ExploracionActiva
    {
        return DB::transaction(function () use ($userId, $expedicionId): ExploracionActiva {
            $bloqueada = DB::table('exploraciones_activas')
                ->where('id', $expedicionId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($bloqueada === null) {
                throw new RecursoNoExiste("No existe la exploración con id {$expedicionId}.");
            }

            $expedicion = ExploracionActiva::query()->find($expedicionId);

            if (! $expedicion instanceof ExploracionActiva) {
                throw new RecursoNoExiste("No existe la exploración con id {$expedicionId}.");
            }

            if ($expedicion->regreso === null) {
                throw new ViolacionReglaNegocio('Solo se pueden archivar expediciones completadas.');
            }

            if ($this->consultor->estaCancelada($expedicion)) {
                throw new ViolacionReglaNegocio('No se puede archivar una expedición cancelada.');
            }

            if ($expedicion->archived_at !== null) {
                return $expedicion;
            }

            $expedicion->archived_at = now();
            $expedicion->save();

            return $expedicion;
        });
    }
}
