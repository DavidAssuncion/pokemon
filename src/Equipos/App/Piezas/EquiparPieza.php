<?php

declare(strict_types=1);

namespace Src\Equipos\App\Piezas;

use App\Models\RecruitGear;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Equipos\Domain\Ports\PiezasEquipadas;
use Src\Equipos\Domain\ValueObjects\EquipamientoPiezas;
use Src\Shared\Domain\Exceptions\RecursoNoExiste;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\Items\ClavePieza;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Equipa una pieza forjada en uno de los 3 slots de un reclutado.
 *
 * Consume la pieza del inventario y, si el slot estaba ocupado, devuelve la
 * anterior (swap) en la misma transacción. Orden de locks: reclutado → claves
 * de inventario (ascendente por `item_key`) → fila de `recruit_gear`. El
 * reclutado bloqueado serializa las operaciones del mismo Pokémon, y el orden
 * ascendente de las claves impide que dos swaps cruzados tomen los locks en
 * orden inverso (deadlock AB/BA), igual que `InventarioJugador::consumirVarios`.
 */
final readonly class EquiparPieza
{
    private const INTENTOS = 3;

    /** SQLSTATE de violación de unicidad: 23505 (PostgreSQL), 23000 (MySQL/SQLite). */
    private const SQLSTATES_UNICIDAD = ['23505', '23000'];

    public function __construct(
        private InventarioJugador $inventario,
        private ObtenerPiezas $obtenerPiezas,
        private VerificadorReclutadoPropio $verificador,
        private PiezasEquipadas $piezasEquipadas,
    ) {
    }

    /**
     * @throws RecursoNoExiste
     * @throws ViolacionReglaNegocio
     */
    public function handle(int $userId, int $reclutadoId, int $slot, string $clave): EquipamientoPiezas
    {
        $pieza = ClavePieza::desde($clave);
        $slot = EquipamientoPiezas::normalizarSlot($slot);
        $claveCanonica = $pieza->clave();

        return DB::transaction(function () use ($userId, $reclutadoId, $slot, $claveCanonica): EquipamientoPiezas {
            $this->verificador->bloquear($userId, $reclutadoId);

            // Con el reclutado bloqueado esta lectura es estable y permite
            // conocer la clave anterior antes de pedir los locks de inventario.
            $filaActual = DB::table('recruit_gear')
                ->where('reclutado_id', $reclutadoId)
                ->where('slot', $slot)
                ->first();

            $anterior = $filaActual !== null ? (string) $filaActual->item_key : null;

            if ($anterior === $claveCanonica) {
                return $this->obtenerPiezas->paraReclutado($userId, $reclutadoId);
            }

            // Una pieza ya equipada no tiene saldo en el inventario: se detecta
            // antes de consumir para devolver un 422 con motivo (propio o de
            // otro reclutado) en vez de "No tienes esa pieza".
            if ($this->piezaEquipada($reclutadoId, $claveCanonica)) {
                throw new ViolacionReglaNegocio('Esa pieza ya está equipada en este Pokémon.');
            }

            if ($this->piezasEquipadas->clavesDe($userId)->contiene($claveCanonica)) {
                throw new ViolacionReglaNegocio('Esa pieza ya está equipada en otro Pokémon.');
            }

            $this->bloquearClaves($userId, $anterior, $claveCanonica);

            if ($filaActual !== null) {
                $this->inventario->incrementar($userId, (string) $anterior, 1);
                DB::table('recruit_gear')->where('id', (int) $filaActual->id)->delete();
            }

            $this->inventario->consumir($userId, $claveCanonica, 1, 'No tienes esa pieza');

            try {
                RecruitGear::query()->forceCreate([
                    'reclutado_id' => $reclutadoId,
                    'slot' => $slot,
                    'item_key' => $claveCanonica,
                ]);
            } catch (QueryException $e) {
                // Red de seguridad del unique(reclutado_id, item_key) ante
                // concurrencia; la pre-consulta cubre el caso normal.
                if (! $this->esViolacionDeUnicidad($e)) {
                    throw $e;
                }

                throw new ViolacionReglaNegocio('Esa pieza ya está equipada en este Pokémon.');
            }

            return $this->obtenerPiezas->paraReclutado($userId, $reclutadoId);
        }, self::INTENTOS);
    }

    private function piezaEquipada(int $reclutadoId, string $clave): bool
    {
        return DB::table('recruit_gear')
            ->where('reclutado_id', $reclutadoId)
            ->where('item_key', $clave)
            ->exists();
    }

    /**
     * Bloquea la clave entrante y, si hay swap, también la saliente, en orden
     * ascendente por `item_key`.
     */
    private function bloquearClaves(int $userId, ?string $anterior, string $claveCanonica): void
    {
        $claves = $anterior === null ? [$claveCanonica] : [$anterior, $claveCanonica];

        sort($claves);

        foreach ($claves as $clave) {
            $this->inventario->bloquearClave($userId, $clave);
        }
    }

    private function esViolacionDeUnicidad(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? $e->getCode();

        return in_array((string) $sqlState, self::SQLSTATES_UNICIDAD, true);
    }
}
