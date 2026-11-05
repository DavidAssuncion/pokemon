<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos;

use Src\Battle\Domain\AccionObjeto;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Shared\Domain\Items\CatalogoItems;

/**
 * Validación y aplicación de objetos de combate sobre el agregado.
 *
 * No consume inventario: eso corresponde al caso de uso de aplicación.
 * Toda la lógica de objetivos y efectos vive en dominio.
 */
final class ServicioUsoObjetos
{
    /**
     * Devuelve el motivo por el que la acción no es válida, o null si lo es.
     */
    public function validar(AccionObjeto $accion, AgregadoBatalla $battle): ?string
    {
        if (! CatalogoEfectosItem::existe($accion->clave) || ! CatalogoItems::existe($accion->clave)) {
            return 'Ese objeto no se puede usar en combate.';
        }

        $actor = $this->buscarCombatiente($battle, $accion->actorId);
        $objetivo = $this->buscarCombatiente($battle, $accion->objetivoId);

        if ($actor === null) {
            return 'El combatiente que usa el objeto no existe.';
        }

        if ($objetivo === null) {
            return 'El objetivo del objeto no existe.';
        }

        if (! $actor->estaVivo()) {
            return "{$actor->nombre()} está debilitado y no puede usar objetos.";
        }

        $efecto = CatalogoEfectosItem::efecto($accion->clave);
        $mismoEquipo = $this->esDelMismoEquipo($battle, $actor, $objetivo);

        $motivoObjetivo = match ($efecto->objetivos()) {
            ObjetivosItem::ALIADO_VIVO => $this->validarAliadoVivo($mismoEquipo, $objetivo),
            ObjetivosItem::ALIADO_DEBILITADO => $this->validarAliadoDebilitado($mismoEquipo, $objetivo),
            ObjetivosItem::RIVAL_VIVO => $this->validarRivalVivo($mismoEquipo, $objetivo),
        };

        if ($motivoObjetivo !== null) {
            return $motivoObjetivo;
        }

        return $efecto->validar($objetivo);
    }

    /**
     * Ejecuta el efecto y construye el resultado con el log narrativo.
     */
    public function aplicar(AccionObjeto $accion, AgregadoBatalla $battle): ResultadoUsoObjeto
    {
        $actor = $this->buscarCombatiente($battle, $accion->actorId);
        $objetivo = $this->buscarCombatiente($battle, $accion->objetivoId);

        if ($actor === null || $objetivo === null) {
            return ResultadoUsoObjeto::fallo($accion->clave, 'El combatiente o el objetivo no existen.');
        }

        $efecto = CatalogoEfectosItem::efecto($accion->clave);
        $resultado = $efecto->aplicar($objetivo, $actor);
        $item = CatalogoItems::definicion($accion->clave);

        $log = "{$actor->nombre()} usó {$item->nombre} en {$objetivo->nombre()}. {$resultado->texto}";

        return new ResultadoUsoObjeto(
            exito: true,
            motivo: null,
            clave: $accion->clave,
            actorNombre: $actor->nombre(),
            objetivoNombre: $objetivo->nombre(),
            log: $log,
            curado: $resultado->curado,
            revivido: $resultado->revivido,
        );
    }

    private function buscarCombatiente(AgregadoBatalla $battle, string $id): ?Combatiente
    {
        return $battle->team1->findCombatantById($id)
            ?? $battle->team2->findCombatantById($id);
    }

    private function esDelMismoEquipo(AgregadoBatalla $battle, Combatiente $actor, Combatiente $objetivo): bool
    {
        $actorEnTeam1 = $battle->team1->findCombatantById($actor->id()) !== null;
        $objetivoEnTeam1 = $battle->team1->findCombatantById($objetivo->id()) !== null;

        return $actorEnTeam1 === $objetivoEnTeam1;
    }

    private function validarAliadoVivo(bool $mismoEquipo, Combatiente $objetivo): ?string
    {
        if (! $mismoEquipo) {
            return 'El objeto solo puede usarse en un aliado.';
        }

        if (! $objetivo->estaVivo()) {
            return "{$objetivo->nombre()} está debilitado.";
        }

        return null;
    }

    private function validarAliadoDebilitado(bool $mismoEquipo, Combatiente $objetivo): ?string
    {
        if (! $mismoEquipo) {
            return 'El objeto solo puede usarse en un aliado.';
        }

        if ($objetivo->estaVivo()) {
            return "{$objetivo->nombre()} no está debilitado.";
        }

        return null;
    }

    private function validarRivalVivo(bool $mismoEquipo, Combatiente $objetivo): ?string
    {
        if ($mismoEquipo) {
            return 'El objeto debe usarse en un rival.';
        }

        if (! $objetivo->estaVivo()) {
            return "{$objetivo->nombre()} está debilitado.";
        }

        return null;
    }
}
