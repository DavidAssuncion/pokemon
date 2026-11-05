<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos\Efectos;

use InvalidArgumentException;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\UsoObjetos\EfectoItem;
use Src\Battle\Domain\UsoObjetos\ResultadoEfectoItem;

/**
 * Aplica un multiplicador temporal a una estadística. Reaplicar refresca
 * la duración sin acumular el factor.
 */
final readonly class EfectoModificarStat implements EfectoItem
{
    public function __construct(
        private StatClave $stat,
        private float $factor,
    ) {
        if ($factor <= 0.0) {
            throw new InvalidArgumentException('El factor de estadística debe ser positivo.');
        }
    }

    public function clave(): string
    {
        return 'modificar_stat';
    }

    public function objetivos(): ObjetivosItem
    {
        return $this->factor >= 1.0
            ? ObjetivosItem::ALIADO_VIVO
            : ObjetivosItem::RIVAL_VIVO;
    }

    public function validar(Combatiente $objetivo): ?string
    {
        if (! $objetivo->estaVivo()) {
            return "{$objetivo->nombre()} está debilitado.";
        }

        return null;
    }

    public function aplicar(Combatiente $objetivo, Combatiente $actor): ResultadoEfectoItem
    {
        $objetivo->setMultiplicadores(
            $objetivo->multiplicadores()->refrescarTemporal($this->stat, $this->factor),
        );

        $verbo = $this->factor >= 1.0 ? 'subió' : 'bajó';
        $porcentaje = (int) round(abs(($this->factor - 1.0) * 100));

        return new ResultadoEfectoItem(
            texto: "{$objetivo->nombre()} {$verbo} {$this->stat->label()} un {$porcentaje} %.",
        );
    }
}
