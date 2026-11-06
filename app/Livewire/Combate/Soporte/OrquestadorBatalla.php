<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Livewire\Presenters\PresentadorVistaCombate;

/**
 * Composition root de la fachada `Combate`: mantiene el contexto efímero de
 * la request y delega cada interacción en el gestor especializado.
 */
final class OrquestadorBatalla
{
    private readonly ContextoEjecucionBatalla $contexto;

    public function __construct(
        private readonly GestorLifecycleBatalla $lifecycle,
        private readonly GestorCicloTurnosBatalla $ciclo,
        private readonly GestorPreparacionBatalla $preparacion,
        private readonly GestorAccionBatalla $accion,
        private readonly GestorSeleccionBatalla $seleccion,
        private readonly GestorObjetosBatalla $objetos,
        private readonly PresentadorVistaCombate $presentador,
    ) {
        $this->contexto = new ContextoEjecucionBatalla();
    }

    public function nuevaBatalla(Combate $componente): void
    {
        $this->lifecycle->nueva($componente, $this->contexto);
    }

    public function montar(Combate $componente): void
    {
        $this->lifecycle->montar($componente, $this->contexto);
    }

    /**
     * @return array<string, mixed>
     */
    public function datosVista(Combate $componente): array
    {
        return $this->presentador->datos($componente);
    }

    public function nextActor(Combate $componente, bool $grabando): void
    {
        $this->ciclo->avanzar($componente, $this->contexto, $grabando);
    }

    public function confirmarPreparacion(Combate $componente): void
    {
        $this->preparacion->confirmar($componente, $this->contexto);
    }

    public function saltarPreparacion(Combate $componente): void
    {
        $this->preparacion->saltar($componente, $this->contexto);
    }

    public function cambiarPostura(Combate $componente, string $refId, string $postura): void
    {
        $this->preparacion->cambiarPostura($componente, $refId, $postura);
    }

    public function commitAction(Combate $componente, bool &$grabando): void
    {
        $this->accion->commit($componente, $this->contexto, $grabando);
    }

    public function previewTarget(Combate $componente, int $teamIdx, int $pokemonIdx): void
    {
        $this->seleccion->previewTarget($componente, $teamIdx, $pokemonIdx);
    }

    public function selectMove(Combate $componente, int $index): void
    {
        $this->seleccion->selectMove($componente, $this->contexto, $index);
    }

    public function cancelTarget(Combate $componente): void
    {
        $this->seleccion->cancelTarget($componente);
    }

    public function seleccionarObjeto(Combate $componente, string $clave): void
    {
        $this->objetos->seleccionar($componente, $clave);
    }

    public function cancelarObjeto(Combate $componente): void
    {
        $this->objetos->cancelar($componente);
    }

    public function confirmarObjetivoItem(Combate $componente, int $teamIdx, int $pokemonIdx): void
    {
        $this->objetos->confirmarObjetivoItem($componente, $teamIdx, $pokemonIdx);
    }

    public function swapTurno(Combate $componente): void
    {
        $this->seleccion->swapTurno($componente, $this->contexto);
    }

    public function retrasarTurno(Combate $componente): void
    {
        $this->seleccion->retrasarTurno($componente, $this->contexto);
    }

    public function pasarTurno(Combate $componente): void
    {
        $this->seleccion->pasarTurno($componente, $this->contexto);
    }
}
