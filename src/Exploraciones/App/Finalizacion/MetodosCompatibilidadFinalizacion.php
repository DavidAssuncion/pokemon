<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Finalizacion;

use App\Models\ExploracionActiva;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\Domain\Recompensas\CalculadoraRecompensasExpedicion;
use Src\Exploraciones\Domain\Recompensas\ClasificadorEventosExploracion;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Domain\ValueObjects\ColeccionEventosExploracion;

/**
 * Métodos privados que los tests invocan por Reflection. Se conservan con la
 * misma firma y delegan en los componentes de cálculo de B1.
 */
trait MetodosCompatibilidadFinalizacion
{
    private readonly CalculadoraTiemposExpedicion $tiempos;
    private readonly ClasificadorEventosExploracion $clasificador;
    private readonly CalculadoraRecompensasExpedicion $calculadora;
    private readonly ResolutorBotinExploracion $botin;

    /**
     * @return list<int>
     */
    private function idsDerrotados(ColeccionEventosExploracion $bitacora): array
    {
        return $this->clasificador->idsDerrotados($bitacora);
    }

    private function duracionReal(ExploracionActiva $exploracion, int $tiempoPerdido): int
    {
        return $this->tiempos->duracionReal($exploracion, $tiempoPerdido);
    }

    private function factorTiempo(ExploracionActiva $exploracion): float
    {
        return $this->tiempos->factorTiempo($exploracion);
    }

    /**
     * @param  list<array<string, mixed>>  $bitacora
     * @param  BaseCollection<string, mixed>  $eventos
     * @return list<\Src\Exploraciones\Domain\Perdidas\ObjetoPerdido>
     */
    private function objetosPerdidos(array $bitacora, BaseCollection $eventos, ResultadoRecompensas $recompensas): array
    {
        return $this->calculadora->calcularPerdidas(
            $recompensas,
            $this->botin->terminoPorDerrota($bitacora, $eventos),
        );
    }

    /**
     * @param  list<\Src\Exploraciones\Domain\Perdidas\ObjetoPerdido>  $perdidas
     */
    private function restarPerdidas(ResultadoRecompensas $recompensas, array $perdidas): ResultadoRecompensas
    {
        return $this->calculadora->restarPerdidas($recompensas, $perdidas);
    }
}
