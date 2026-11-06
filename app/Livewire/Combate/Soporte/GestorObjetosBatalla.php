<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate;
use App\Support\BattleSessionService;
use Illuminate\Contracts\Auth\Guard;
use Src\Battle\App\UsarObjetoEnCombate;
use Src\Battle\Domain\AccionObjeto;
use Src\Battle\Domain\DataTransferObjects\DTOAccionObjeto;
use Src\Battle\Domain\Enums\FaseCombate;
use Src\Battle\Domain\UsoObjetos\CatalogoEfectosItem;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Gestiona la selección de objetos de combate y su objetivo. Sin estado: la
 * autorización y el inventario se consultan en servidor en cada acción.
 */
final class GestorObjetosBatalla
{
    public function __construct(
        private readonly InventarioJugador $inventario,
        private readonly UsarObjetoEnCombate $usarObjeto,
        private readonly Guard $guard,
        private readonly ResolutorActorBatalla $resolutorActor,
        private readonly GestorVistaBatalla $gestorVista,
        private readonly GestorAnimacionBatalla $gestorAnimacion,
        private readonly BattleSessionService $session,
    ) {
    }

    public function seleccionar(Combate $componente, string $clave): void
    {
        if ($componente->phase !== FaseCombate::SELECCION_OBJETIVO->value) {
            return;
        }

        if ($componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        if (! CatalogoItems::existe($clave) || ! CatalogoEfectosItem::existe($clave)) {
            return;
        }

        $definicion = CatalogoItems::definicion($clave);
        if (! $definicion->usableEnCombate) {
            return;
        }

        $userId = (int) $this->guard->id();
        if ($userId <= 0) {
            return;
        }

        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null) {
            return;
        }

        // El actor se deriva del estado del agregado en servidor:
        // `actingRefId` es una propiedad pública manipulable por el cliente.
        $actor = $this->resolutorActor->jugadorAutorizado($battle);
        if ($actor === null) {
            return;
        }

        $saldo = $this->inventario->saldo($userId, $clave);
        if ($saldo <= 0) {
            $componente->log[] = "No tienes unidades de {$definicion->nombre}.";
            $this->gestorVista->sincronizar($componente, $battle, $actor);
            $this->session->guardar($componente->battleId, $battle);

            return;
        }

        $efecto = CatalogoEfectosItem::efecto($clave);
        $componente->objetoSeleccionado = $clave;
        $componente->objetivosItem = $efecto->objetivos()->value;
        $componente->selectedTargetTeam = null;
        $componente->selectedTargetIdx = null;
        $componente->selectedTargetRefId = '';
        $componente->phase = FaseCombate::SELECCION_OBJETIVO_ITEM->value;
        $this->gestorVista->sincronizar($componente, $battle, $actor);
        $this->session->guardar($componente->battleId, $battle);
    }

    public function cancelar(Combate $componente): void
    {
        if ($componente->phase !== FaseCombate::SELECCION_OBJETIVO_ITEM->value) {
            return;
        }

        $this->limpiar($componente);
        $componente->selectedTargetTeam = null;
        $componente->selectedTargetIdx = null;
        $componente->selectedTargetRefId = '';
        $componente->phase = FaseCombate::SELECCION_OBJETIVO->value;
    }

    public function confirmarObjetivoItem(Combate $componente, int $teamIdx, int $pokemonIdx): void
    {
        if ($componente->phase !== FaseCombate::SELECCION_OBJETIVO_ITEM->value) {
            return;
        }

        if ($componente->processing || $componente->animAttackerId !== '') {
            return;
        }

        if ($componente->objetoSeleccionado === '') {
            return;
        }

        $battle = $this->session->cargar($componente->battleId);
        if ($battle === null) {
            return;
        }

        // `actingRefId`, `phase` y `processing` son propiedades públicas: la
        // autorización real se deriva del estado server-side del agregado.
        $actor = $this->resolutorActor->jugadorAutorizado($battle);
        if ($actor === null) {
            return;
        }

        // El objeto puede haber cambiado de estado entre la selección y la
        // confirmación (stock agotado, catálogo actualizado).
        if (! CatalogoItems::existe($componente->objetoSeleccionado)
            || ! CatalogoEfectosItem::existe($componente->objetoSeleccionado)) {
            $componente->log[] = 'Ese objeto no se puede usar en combate.';

            return;
        }

        $item = CatalogoItems::definicion($componente->objetoSeleccionado);
        if (! $item->usableEnCombate) {
            $componente->log[] = "{$item->nombre} no se puede usar en combate.";

            return;
        }

        $userId = (int) $this->guard->id();
        $saldo = $userId > 0
            ? $this->inventario->saldo($userId, $componente->objetoSeleccionado)
            : 0;
        if ($saldo <= 0) {
            $componente->log[] = "No tienes unidades de {$item->nombre}.";
            $this->cancelar($componente);

            return;
        }

        $objetivo = $this->resolutorActor->objetivoPorSeleccion($battle, $teamIdx, $pokemonIdx);
        if ($objetivo === null) {
            return;
        }

        $accion = new AccionObjeto(
            actorId: $actor->id(),
            objetivoId: $objetivo->id(),
            clave: $componente->objetoSeleccionado,
        );

        $motivo = $this->usarObjeto->validar($battle, $accion);
        if ($motivo !== null) {
            $componente->log[] = $motivo;
            $this->gestorVista->sincronizar($componente, $battle, $actor);
            $this->session->guardar($componente->battleId, $battle);

            return;
        }

        $battle->setPendingAction(new DTOAccionObjeto(
            actorId: $actor->id(),
            objetivoId: $objetivo->id(),
            clave: $componente->objetoSeleccionado,
        ));

        $componente->selectedTargetTeam = $teamIdx;
        $componente->selectedTargetIdx = $pokemonIdx;
        $componente->selectedTargetRefId = $objetivo->id();

        $this->gestorAnimacion->objeto($componente, $actor, $objetivo, $item->nombre);
        $this->gestorVista->sincronizar($componente, $battle, $actor);
        $this->session->guardar($componente->battleId, $battle);
    }

    public function limpiar(Combate $componente): void
    {
        $componente->objetoSeleccionado = '';
        $componente->objetivosItem = '';
    }
}
