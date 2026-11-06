<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Livewire\Combate\Soporte\DTO\SnapshotBatalla;
use App\Support\BattleSessionService;
use Src\Battle\Domain\AgregadoBatalla;

/**
 * Orquesta la grabación de eventos de animación sobre las propiedades
 * `eventos` y `eventoTick` del componente. El flag `grabando` se recibe por
 * referencia: solo `iniciar()` y `cerrar()` lo escriben.
 */
final class GestorEventosBatalla
{
    public function __construct(
        private readonly GrabadorEventosBatalla $grabador,
        private readonly BattleSessionService $session,
    ) {
    }

    public function iniciar(Combate $componente, bool &$grabando): void
    {
        $componente->eventos = [];
        $componente->eventoTick++;
        $grabando = true;
    }

    public function cerrar(Combate $componente, bool &$grabando): void
    {
        if ($grabando) {
            $battle = $this->session->cargar($componente->battleId);

            if ($battle !== null) {
                $componente->eventos[] = $this->grabador->eventoFin(
                    $this->capturar($battle),
                    $componente->eventoTick,
                    count($componente->eventos) + 1,
                );
            }
        }

        $grabando = false;
    }

    public function capturar(AgregadoBatalla $battle): SnapshotBatalla
    {
        return $this->grabador->capturar($battle);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function registrar(
        Combate $componente,
        bool $grabando,
        string $tipo,
        SnapshotBatalla $antes,
        SnapshotBatalla $despues,
        array $extra = [],
    ): void {
        if (! $grabando) {
            return;
        }

        $evento = $this->grabador->registrar(
            $tipo,
            $antes,
            $despues,
            $componente->eventoTick,
            count($componente->eventos) + 1,
            $extra,
        );
        if ($evento !== null) {
            $componente->eventos[] = $evento;
        }
    }

    public function batallaTerminada(
        Combate $componente,
        bool $grabando,
        string $texto,
        bool $victoria,
    ): void {
        if (! $grabando) {
            return;
        }

        $componente->eventos[] = $this->grabador->eventoBatallaTerminada(
            $texto,
            $victoria,
            $componente->eventoTick,
            count($componente->eventos) + 1,
        );
    }
}
