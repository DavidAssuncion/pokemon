<?php

declare(strict_types=1);

namespace Src\Equipos\Domain\Ports;

use Src\Shared\Collections\StringCollection;

/**
 * Claves de las piezas forjadas que el jugador tiene equipadas.
 *
 * Una pieza equipada no está en el inventario, pero sigue siendo del jugador:
 * Forja la trata como poseída para no fabricar un duplicado.
 */
interface PiezasEquipadas
{
    /**
     * Claves `item_key` equipadas en cualquier reclutado del jugador, sin
     * duplicados. Colección vacía si no tiene ninguna.
     */
    public function clavesDe(int $userId): StringCollection;
}
