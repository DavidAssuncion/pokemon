<?php

declare(strict_types=1);

namespace Src\Avistamientos\Domain\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Src\Avistamientos\Domain\ReglasAvistamiento;
use Src\Avistamientos\Domain\ValueObjects\VentanaAvistamiento;

/**
 * Resuelve la ventana de avistamiento que contiene un instante.
 *
 * Los días son naturales en la zona horaria del juego, no bloques absolutos
 * de 72 horas: una ventana que cruza un cambio de hora dura 71 o 73 horas
 * reales.
 */
final class CalculadoraVentanaAvistamiento
{
    public function actual(DateTimeImmutable $ahora): VentanaAvistamiento
    {
        $zona = new DateTimeZone(ReglasAvistamiento::ZONA_HORARIA);
        $ancla = new DateTimeImmutable(ReglasAvistamiento::ANCLA, $zona);
        $hoy = $ahora->setTimezone($zona)->setTime(0, 0, 0);

        $dias = $this->diasNaturales($ancla, $hoy);
        $indice = $dias <= 0 ? 0 : intdiv($dias, ReglasAvistamiento::DURACION_DIAS);
        $desplazamiento = $indice * ReglasAvistamiento::DURACION_DIAS;

        $inicio = $ancla->add($this->diasIntervalo($desplazamiento));
        $fin = $inicio->add($this->diasIntervalo(ReglasAvistamiento::DURACION_DIAS));

        return new VentanaAvistamiento($indice, $inicio, $fin);
    }

    public function en(DateTimeImmutable $instante): VentanaAvistamiento
    {
        return $this->actual($instante);
    }

    /**
     * Días naturales entre dos medianoches locales; negativo si la fecha es
     * anterior al ancla.
     */
    private function diasNaturales(DateTimeImmutable $ancla, DateTimeImmutable $fecha): int
    {
        $diferencia = $ancla->diff($fecha);

        return $diferencia->invert === 1 ? -$diferencia->days : $diferencia->days;
    }

    private function diasIntervalo(int $dias): DateInterval
    {
        return new DateInterval(sprintf('P%dD', $dias));
    }
}
