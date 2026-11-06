<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Finalizacion;

use App\Models\ExploracionActiva;
use Carbon\Carbon;
use Src\Exploraciones\Domain\CalculadorFinExploracion;
use Src\Exploraciones\Domain\Tiempos\CalculadoraDuracionExploracion;
use Src\Exploraciones\Domain\Tiempos\VentanaExploracion;
use Src\Shared\Domain\Ports\Reloj;

/**
 * Construye la ventana temporal de una expedición con el reloj del dominio y
 * delega los cálculos puros en `CalculadoraDuracionExploracion`.
 */
final class CalculadoraTiemposExpedicion
{
    public function __construct(
        private readonly Reloj $reloj,
    ) {
    }

    /**
     * Instante actual con la semántica del helper temporal de Laravel, pero
     * obtenido a través del puerto `Reloj`.
     */
    public function ahora(): Carbon
    {
        return Carbon::instance($this->reloj->ahora());
    }

    public function duracionReal(ExploracionActiva $exploracion, int $tiempoPerdido): int
    {
        return (new CalculadoraDuracionExploracion())->duracionReal(
            $this->ventana($exploracion),
            $tiempoPerdido,
        );
    }

    public function factorTiempo(ExploracionActiva $exploracion): float
    {
        return (new CalculadoraDuracionExploracion())->factorTiempo(
            $this->ventana($exploracion),
        );
    }

    /**
     * Construye la ventana temporal de la expedición con el reloj del dominio.
     */
    private function ventana(ExploracionActiva $exploracion): VentanaExploracion
    {
        $ahora = $this->ahora();
        $inicio = $exploracion->inicio_exploracion?->copy()
            ?? $exploracion->created_at?->copy()
            ?? $ahora;
        $fin = CalculadorFinExploracion::calcular(
            $exploracion->hora_limite,
            $exploracion->duracion_horas,
            $inicio,
        );

        return new VentanaExploracion(
            inicio: $inicio,
            fin: $fin,
            ahora: $ahora,
        );
    }
}
