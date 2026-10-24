<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Chain;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Enums\ClaveItem;
use Src\Battle\Domain\ReglasBatalla;

/**
 * Aplica el multiplicador de daño del objeto equipado por el atacante.
 * Consulta un mapa centralizado objeto → multiplicador; si el atacante no
 * lleva objeto (o uno sin multiplicador de daño) devuelve ×1.0 (sin cambio).
 */
class ManejadorObjetosEquipados extends ManejadorDanioAbstracto
{
    /** @var array<string, float> */
    private readonly array $multiplicadores;

    /**
     * @param  array<string, float>  $multiplicadores
     */
    public function __construct(array $multiplicadores = [ClaveItem::ORBE_VIDA->value => ReglasBatalla::BONUS_ORBE_VIDA])
    {
        $this->multiplicadores = $multiplicadores;
    }

    protected function process(AccionBatalla $action, float $daño): float
    {
        if (! $action->attacker->estaVivo() || ! $action->attacker->itemActivo()) {
            return $daño;
        }

        $item = $action->attacker->item();
        $multiplicador = $this->multiplicadores[$item] ?? 1.0;

        if ($item === ClaveItem::CINTA_ELECCION->value && $action->move->esFisico()) {
            $multiplicador *= 1.30;
        }

        if ($item === ClaveItem::GAFAS_ELECCION->value && $action->move->esEspecial()) {
            $multiplicador *= 1.30;
        }

        return $daño * $multiplicador;
    }
}
