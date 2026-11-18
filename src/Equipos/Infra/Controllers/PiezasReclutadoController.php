<?php

declare(strict_types=1);

namespace Src\Equipos\Infra\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Piezas\SlotsPiezas;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Src\Equipos\App\Piezas\DesequiparPieza;
use Src\Equipos\App\Piezas\EquiparPieza;
use Src\Equipos\App\Piezas\ObtenerPiezas;
use Src\Equipos\Domain\ValueObjects\EquipamientoPiezas;
use Src\Equipos\Infra\Requests\EquiparPiezaRequest;
use Src\Forja\Domain\Ports\PiezasPorEspecie;

/**
 * Piezas forjadas equipadas por reclutado (3 slots).
 *
 * Los IDs vienen por URL y el `tipo` de cada pieza se deriva de la clave de
 * inventario. Nombre, icono, stats y habilidad se resuelven en una única
 * lectura por especie, sin depender de que el jugador conserve el nido.
 */
final class PiezasReclutadoController extends Controller
{
    public function __construct(
        private readonly ObtenerPiezas $obtenerPiezas,
        private readonly EquiparPieza $equiparPieza,
        private readonly DesequiparPieza $desequiparPieza,
        private readonly PiezasPorEspecie $piezasPorEspecie,
        private readonly Guard $auth,
    ) {
    }

    public function index(int $reclutado): JsonResponse
    {
        return $this->respuesta($this->obtenerPiezas->paraReclutado($this->userId(), $reclutado));
    }

    public function update(EquiparPiezaRequest $request, int $reclutado, string $slot): JsonResponse
    {
        return $this->respuesta($this->equiparPieza->handle(
            $this->userId(),
            $reclutado,
            EquipamientoPiezas::normalizarSlot($slot),
            $request->clave(),
        ));
    }

    public function destroy(int $reclutado, string $slot): JsonResponse
    {
        return $this->respuesta($this->desequiparPieza->handle(
            $this->userId(),
            $reclutado,
            EquipamientoPiezas::normalizarSlot($slot),
        ));
    }

    private function respuesta(EquipamientoPiezas $equipamiento): JsonResponse
    {
        $definiciones = $this->piezasPorEspecie->definicionesDeEspecies(
            SlotsPiezas::especiesDe($equipamiento->todas()),
        );

        return response()->json([
            'piezas' => SlotsPiezas::construir($equipamiento, $definiciones),
        ]);
    }

    private function userId(): int
    {
        return (int) $this->auth->id();
    }
}
