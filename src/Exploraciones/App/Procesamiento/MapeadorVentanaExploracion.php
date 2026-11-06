<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\ExploracionActiva;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\CalculadorFinExploracion;
use Src\Exploraciones\Domain\CalculadorVueltaExploracion;
use Src\Exploraciones\Domain\Tick\CalculadoraVentanaTick;
use Src\Exploraciones\Domain\Tick\VentanaTick;
use Src\Shared\Domain\Ports\Reloj;

/**
 * Mapea el modelo Eloquent de exploración a la ventana de dominio del tick.
 *
 * Conserva el orden de cálculo original: inicio, fin, inicio de vuelta,
 * último procesado y límite. El reloj se inyecta por el puerto `Reloj`.
 */
final class MapeadorVentanaExploracion
{
    public function __construct(
        private readonly Reloj $reloj,
    ) {
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    public function mapear(ExploracionActiva $exploracion, Collection $eventos): VentanaTick
    {
        $inicio = $this->inicio($exploracion);
        $fin = CalculadorFinExploracion::calcular(
            $exploracion->hora_limite,
            $exploracion->duracion_horas,
            $inicio,
        );
        $inicioVuelta = CalculadorVueltaExploracion::inicioVuelta($inicio, $fin);

        $desde = CalculadoraVentanaTick::ultimoProcesado($eventos->get('ultimo_procesado')) ?? $inicio;
        $hasta = CalculadoraVentanaTick::limite($this->ahora(), $fin, $inicioVuelta);

        return new VentanaTick(
            inicio: $inicio,
            fin: $fin,
            inicioVuelta: $inicioVuelta,
            desde: $desde,
            hasta: $hasta,
        );
    }

    /**
     * Instante actual del reloj inyectado como Carbon (semántica de now()).
     */
    public function ahora(): CarbonInterface
    {
        return Carbon::instance($this->reloj->ahora());
    }

    private function inicio(ExploracionActiva $exploracion): CarbonInterface
    {
        if ($exploracion->inicio_exploracion !== null) {
            return $exploracion->inicio_exploracion->copy();
        }

        if ($exploracion->created_at !== null) {
            return $exploracion->created_at->copy();
        }

        return $this->ahora();
    }
}
