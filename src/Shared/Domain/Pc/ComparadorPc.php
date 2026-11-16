<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Pc;

/**
 * Comparador puro de PC. Centraliza el cálculo del ratio y la aplicación de
 * los umbrales para que los tres contextos (ruta, entrenador, gimnasio)
 * compartan una única regla.
 */
final class ComparadorPc
{
    private const DECIMALES_RATIO = 2;

    public function ratio(?int $pcEquipo, ?int $pcRival): ?float
    {
        if ($pcEquipo === null || $pcRival === null || $pcEquipo <= 0) {
            return null;
        }

        return round($pcRival / $pcEquipo, self::DECIMALES_RATIO);
    }

    public function comparar(
        ?int $pcEquipo,
        ?int $pcRival,
        ?UmbralesVeredictoPc $umbrales = null,
    ): ?VeredictoPc {
        $ratio = $this->ratio($pcEquipo, $pcRival);

        if ($ratio === null) {
            return null;
        }

        $umbrales ??= new UmbralesVeredictoPc();

        if ($ratio < $umbrales->favorableMaximo) {
            return VeredictoPc::FAVORABLE;
        }

        if ($ratio > $umbrales->desfavorableMinimo) {
            return VeredictoPc::DESFAVORABLE;
        }

        return VeredictoPc::PAREJO;
    }
}
