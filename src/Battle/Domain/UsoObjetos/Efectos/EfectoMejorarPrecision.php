<?php

declare(strict_types=1);

namespace Src\Battle\Domain\UsoObjetos\Efectos;

use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\ObjetivosItem;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\UsoObjetos\EfectoItem;
use Src\Battle\Domain\UsoObjetos\ResultadoEfectoItem;

/**
 * Mejora precisión y evasión del aliado durante cinco rondas.
 */
final readonly class EfectoMejorarPrecision implements EfectoItem
{
    private const float FACTOR = 1.30;

    public function clave(): string
    {
        return 'mejorar_precision';
    }

    public function objetivos(): ObjetivosItem
    {
        return ObjetivosItem::ALIADO_VIVO;
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
        $multiplicadores = $objetivo->multiplicadores()
            ->refrescarTemporal(StatClave::PRECISION, self::FACTOR)
            ->refrescarTemporal(StatClave::EVASION, self::FACTOR);

        $objetivo->setMultiplicadores($multiplicadores);

        return new ResultadoEfectoItem(
            texto: "{$objetivo->nombre()} mejoró su precisión y su evasión.",
        );
    }
}
