<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Support\BattleSessionService;
use Illuminate\Http\Request;
use Src\Battle\Domain\FabricaBatallaInterface;
use Src\Battle\Domain\ServicioEjecucionBatalla;

/**
 * Arranca y rehidrata la batalla del componente. Conserva el orden exacto de
 * guardados, log y `nextActor()` respecto al componente original.
 */
final class GestorLifecycleBatalla
{
    public function __construct(
        private readonly FabricaBatallaInterface $fabricaBatalla,
        private readonly BattleSessionService $session,
        private readonly GestorVistaBatalla $gestorVista,
        private readonly Request $request,
    ) {
    }

    public function nueva(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        $componente->battleId = $this->session->crearId();
        $this->iniciarMock($componente, $contexto);
    }

    public function montar(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        $battleId = $this->request->query('battle_id');
        if (is_string($battleId) && $battleId !== '') {
            $componente->battleId = $battleId;
            $battle = $this->session->cargar($componente->battleId);
            if ($battle !== null) {
                $contexto->fijarServicio(new ServicioEjecucionBatalla($battle->damageChain()));
                $this->gestorVista->sincronizar($componente, $battle);
                $componente->log[] = '¡Comienza la batalla!';
                $this->session->guardar($componente->battleId, $battle);
                $componente->nextActor();

                return;
            }
        }

        $this->nueva($componente, $contexto);
    }

    private function iniciarMock(Combate $componente, ContextoEjecucionBatalla $contexto): void
    {
        $battle = $this->fabricaBatalla->createBattle();
        $contexto->fijarServicio(new ServicioEjecucionBatalla($battle->damageChain()));
        $this->session->guardar($componente->battleId, $battle);

        $this->gestorVista->sincronizar($componente, $battle);
        $this->session->guardar($componente->battleId, $battle); // re-save after clearing log
        $componente->log[] = '¡Comienza la batalla!';
        $componente->nextActor();
    }
}
