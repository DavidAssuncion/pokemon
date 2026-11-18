<?php

declare(strict_types=1);

namespace Src\Equipos\App\Piezas;

use Illuminate\Support\Facades\DB;
use Src\Equipos\Domain\ValueObjects\EquipamientoPiezas;
use Src\Shared\Domain\Exceptions\RecursoNoExiste;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Desequipa la pieza de un slot y la devuelve al inventario del jugador.
 *
 * Orden de locks: reclutado → fila de `recruit_gear` → clave de inventario;
 * la clave se bloquea antes de incrementarla para no chocar con `EquiparPieza`.
 */
final readonly class DesequiparPieza
{
    private const INTENTOS = 3;

    public function __construct(
        private InventarioJugador $inventario,
        private ObtenerPiezas $obtenerPiezas,
        private VerificadorReclutadoPropio $verificador,
    ) {
    }

    /**
     * @throws RecursoNoExiste
     * @throws ViolacionReglaNegocio
     */
    public function handle(int $userId, int $reclutadoId, int $slot): EquipamientoPiezas
    {
        $slot = EquipamientoPiezas::normalizarSlot($slot);

        return DB::transaction(function () use ($userId, $reclutadoId, $slot): EquipamientoPiezas {
            $this->verificador->bloquear($userId, $reclutadoId);

            $fila = DB::table('recruit_gear')
                ->where('reclutado_id', $reclutadoId)
                ->where('slot', $slot)
                ->lockForUpdate()
                ->first();

            if ($fila === null) {
                throw new RecursoNoExiste('Ese slot no tiene ninguna pieza equipada.');
            }

            $clave = (string) $fila->item_key;

            $this->inventario->bloquearClave($userId, $clave);
            $this->inventario->incrementar($userId, $clave, 1);
            DB::table('recruit_gear')->where('id', (int) $fila->id)->delete();

            return $this->obtenerPiezas->paraReclutado($userId, $reclutadoId);
        }, self::INTENTOS);
    }
}
