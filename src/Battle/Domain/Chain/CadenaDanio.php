<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

class CadenaDanio
{
    private ManejadorDanioAbstracto $first;

    public function __construct()
    {
        $manejadores = [
            new ManejadorDanioBase(),
            new ManejadorProteccion(),
            new ManejadorInmunidadTipo(),
            new ManejadorEfectividadTipo(),
            new ManejadorSTAB(),
            new ManejadorCombo(),
            new ManejadorCritico(),
            new ManejadorPosicion(),
            new ManejadorClima(),
            new ManejadorTerreno(),
            new ManejadorHabilidadesAtaque(),
            new ManejadorHabilidadesDefensa(),
            new ManejadorMarcado(),
            new ManejadorEquipo(),
            new ManejadorObjetosEquipados(),
            new ManejadorBonusDaño(),
            new ManejadorBonificadorDanio(),
            new ManejadorAguante(),
        ];

        for ($indice = 0; $indice < count($manejadores) - 1; $indice++) {
            $manejadores[$indice]->setNext($manejadores[$indice + 1]);
        }

        $this->first = $manejadores[0];
    }

    public function calculate(AccionBatalla $action): float
    {
        // Los movimientos de estado no infligen daño (ni siquiera el mínimo de 1).
        if ($action->move->esEstado()) {
            return 0.0;
        }

        $daño = $this->first->handle($action, 0);

        // El daño fijo (Sacrificio, Esfuerzo, Superdiente…) no pasa por STAB,
        // efectividad, crítico ni bonificadores: se aplica tal cual.
        if ($action->move->perfil->formulaDano !== 'normal') {
            return max(0.0, floor($daño));
        }

        // Inmunidad de tipo o habilidad: el 0 es real, no se aplica el mínimo 1.
        if ($daño <= 0.0) {
            return 0.0;
        }

        return max(1, floor($daño));
    }
}
