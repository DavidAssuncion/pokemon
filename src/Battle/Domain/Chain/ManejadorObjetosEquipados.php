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
        $multiplicador = $this->multiplicadores[$action->attacker->item()] ?? 1.0;

        if ($action->attacker->estaVivo()) {
            $daño *= $multiplicador;
        }

        return $daño;
    }
}
