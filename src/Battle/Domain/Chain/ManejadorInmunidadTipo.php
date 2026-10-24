<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;

/**
 * Anula el daño cuando el defensor es inmune al tipo del movimiento
 * (Absorción Agua / Electricidad / Fuego). La reacción de absorción (cura,
 * subida de stat) la dispara el ejecutor, no la cadena, para no mutar el
 * estado durante previews ni estimaciones de la IA.
 */
class ManejadorInmunidadTipo extends ManejadorDanioAbstracto
{
    protected function process(AccionBatalla $action, float $daño): float
    {
        if ($action->attacker->ignoraHabilidadRival()) {
            return $daño;
        }

        if ($action->defender->tieneCondicion(\Src\Battle\Domain\Enums\ClaveCondicion::LEVITACION)
            && $action->attacker->tipoEfectivoMovimiento($action) === \Src\Shared\Tipos\TipoPokemon::TIERRA) {
            return 0.0;
        }

        return $action->defender->inmunidadTipo($action->attacker->tipoEfectivoMovimiento($action)) !== null
            ? 0.0
            : $daño;
    }
}
