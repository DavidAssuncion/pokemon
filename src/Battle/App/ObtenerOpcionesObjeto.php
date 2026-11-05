<?php

declare(strict_types=1);

namespace Src\Battle\App;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Collections\OpcionObjetoCollection;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\DataTransferObjects\OpcionObjeto;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\UsoObjetos\CatalogoEfectosItem;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Items\DefinicionItem;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Construye las 44 opciones del panel de objetos de combate con su stock,
 * categoría y aplicabilidad para el actor activo. Una sola consulta de
 * saldos por render.
 */
final readonly class ObtenerOpcionesObjeto
{
    public function __construct(
        private InventarioJugador $inventario,
    ) {
    }

    public function handle(int $userId, AgregadoBatalla $battle, Combatiente $actor): OpcionObjetoCollection
    {
        $definiciones = CatalogoItems::objetosDeCombate();
        $claves = new StringCollection($definiciones->pluck(
            static fn (DefinicionItem $definicion): string => $definicion->clave,
        ));
        $saldos = $this->inventario->saldos($userId, $claves);

        $opciones = [];
        foreach ($definiciones as $definicion) {
            if (! CatalogoEfectosItem::existe($definicion->clave)) {
                continue;
            }

            try {
                $efecto = CatalogoEfectosItem::efecto($definicion->clave);
            } catch (ItemDesconocido) {
                continue;
            }

            $cantidad = $saldos->obtener($definicion->clave);
            $objetivos = $this->objetivosPara($battle, $actor, $efecto->objetivos());
            $motivos = [];
            $aplicableAlguno = false;

            foreach ($objetivos as $objetivo) {
                $motivo = $efecto->validar($objetivo);
                if ($motivo === null) {
                    $aplicableAlguno = true;

                    break;
                }

                $motivos[] = $motivo;
            }

            $motivo = match (true) {
                $cantidad <= 0 => 'Sin existencias.',
                $objetivos === [] => 'No hay objetivos válidos.',
                ! $aplicableAlguno => $motivos[0] ?? 'No hay objetivos válidos.',
                default => '',
            };

            $opciones[] = new OpcionObjeto(
                clave: $definicion->clave,
                nombre: $definicion->nombre,
                categoria: $definicion->categoria->value,
                icono: $definicion->icono,
                descripcion: $definicion->descripcion,
                cantidad: $cantidad,
                aplicable: $cantidad > 0 && $aplicableAlguno,
                motivo: $motivo,
                objetivos: $efecto->objetivos(),
            );
        }

        return new OpcionObjetoCollection($opciones);
    }

    /**
     * @return list<Combatiente>
     */
    private function objetivosPara(
        AgregadoBatalla $battle,
        Combatiente $actor,
        ObjetivosItem $objetivos,
    ): array {
        $actorEnTeam1 = $battle->team1->findCombatantById($actor->id()) !== null;
        $equipoAliado = $actorEnTeam1 ? $battle->team1 : $battle->team2;
        $equipoRival = $actorEnTeam1 ? $battle->team2 : $battle->team1;
        $equipo = $objetivos === ObjetivosItem::RIVAL_VIVO ? $equipoRival : $equipoAliado;

        $resultado = [];
        foreach ($equipo->combatants()->all() as $combatiente) {
            if ($objetivos === ObjetivosItem::ALIADO_DEBILITADO && $combatiente->estaVivo()) {
                continue;
            }

            if ($objetivos !== ObjetivosItem::ALIADO_DEBILITADO && ! $combatiente->estaVivo()) {
                continue;
            }

            $resultado[] = $combatiente;
        }

        return $resultado;
    }
}
