<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use Carbon\CarbonInterface;

/**
 * Minutos restantes hasta el fin de una expedición y su etiqueta legible.
 *
 * Centraliza el formato que ya consumía la vista de exploraciones
 * (`Menos de 1 min`, `X min`, `X h`, `X h Y min`) y el redondeo hacia arriba
 * de los minutos, para que panel y página no diverjan.
 */
final class FormateadorTiempoRestante
{
    /**
     * Minutos restantes (0 si el fin ya pasó) o null si no hay fin.
     */
    public function minutos(?CarbonInterface $fin, ?CarbonInterface $ahora = null): ?int
    {
        if ($fin === null) {
            return null;
        }

        return max(0, (int) ceil(($ahora ?? now())->diffInMinutes($fin, false)));
    }

    public function etiqueta(int $minutos): string
    {
        if ($minutos <= 0) {
            return 'Menos de 1 min';
        }

        if ($minutos < 60) {
            return "{$minutos} min";
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto === 0 ? "{$horas} h" : "{$horas} h {$resto} min";
    }
}
