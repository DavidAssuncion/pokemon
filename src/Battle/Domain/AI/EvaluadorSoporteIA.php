<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Catalog\CatalogoEfectosAliado;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Ejecucion\CalculadoraCuracionAliada;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoEfectoAliado;
use Src\Battle\Domain\ValueObjects\EfectoAliado;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;

/**
 * Valora una acción de apoyo sobre un aliado con una compuerta conservadora:
 * solo puntúa cuando el efecto tiene impacto real (PS bajos, déficit de
 * escudo, buff con delta neto, estado activo o crítico refrescable).
 *
 * No calcula daño ni KO: el soporte nunca debe competir con un ataque letal.
 */
final class EvaluadorSoporteIA
{
    private const float FRACCION_RECUPERACION = 0.5;

    private const float FRACCION_APORTE_AQUA_RING = 0.05;

    private const float DELTA_MINIMO_BUFF = 0.3;

    public function __construct(
        private readonly CalculadoraDanioIA $calculadoraDanio,
        private readonly CalculadoraCuracionAliada $calculadoraCuracion,
        private readonly PesosAmenaza $pesos,
    ) {
    }

    public function valor(ContextoDecisionIA $contexto, AccionBatalla $accion): float
    {
        $efecto = CatalogoEfectosAliado::para($accion->move->slug);
        if ($efecto === null) {
            return 0.0;
        }

        $aliado = $accion->defender;
        if (! $aliado->estaVivo()) {
            return 0.0;
        }

        $hpMax = $aliado->pokemon()->battleStats()->hp;
        if ($hpMax <= 0.0) {
            return 0.0;
        }

        $hp = $aliado->hpActual();
        $faltante = max(0.0, $hpMax - $hp);
        $ratio = $hp / $hpMax;
        $golpeMax = match ($efecto->tipo) {
            TipoEfectoAliado::CURAR_PS,
            TipoEfectoAliado::ESCUDO_FISICO,
            TipoEfectoAliado::ESCUDO_ESPECIAL,
            TipoEfectoAliado::AQUA_RING => $this->golpeMaximo($contexto, $aliado),
            TipoEfectoAliado::BUFF_STATS,
            TipoEfectoAliado::CURAR_ESTADO,
            TipoEfectoAliado::CRITICO_MEJORADO => 0.0,
        };
        $enRiesgoKO = $golpeMax >= $hp;

        $valor = match ($efecto->tipo) {
            TipoEfectoAliado::CURAR_PS => $this->valorCuracionPs(
                $accion,
                $hp,
                $hpMax,
                $faltante,
                $ratio,
                $golpeMax,
                $enRiesgoKO,
            ),
            TipoEfectoAliado::ESCUDO_FISICO => $this->valorEscudo($accion, $hp, $golpeMax, false),
            TipoEfectoAliado::ESCUDO_ESPECIAL => $this->valorEscudo($accion, $hp, $golpeMax, true),
            TipoEfectoAliado::AQUA_RING => $this->valorAquaRing(
                $accion,
                $efecto,
                $hpMax,
                $faltante,
                $ratio,
                $enRiesgoKO,
            ),
            TipoEfectoAliado::BUFF_STATS => $this->valorBuffStats($accion),
            TipoEfectoAliado::CURAR_ESTADO => $this->valorCuracionEstado($accion->defender),
            TipoEfectoAliado::CRITICO_MEJORADO => $this->valorCriticoMejorado($accion->defender),
        };

        if ($valor < $this->pesos->puntosMinimosSoporte) {
            return 0.0;
        }

        return $valor;
    }

    /**
     * Máximo golpe esperado que cualquier enemigo vivo puede infligir al aliado.
     */
    private function golpeMaximo(ContextoDecisionIA $contexto, Combatiente $aliado): float
    {
        $golpeMax = 0.0;

        foreach ($contexto->enemigos as $enemigo) {
            $estimacion = $this->calculadoraDanio->mejorEstimacionContra(
                $enemigo,
                $aliado,
                $contexto->battle,
            );

            if ($estimacion !== null && $estimacion->esperado > $golpeMax) {
                $golpeMax = $estimacion->esperado;
            }
        }

        return $golpeMax;
    }

    private function valorCuracionPs(
        AccionBatalla $accion,
        float $hp,
        float $hpMax,
        float $faltante,
        float $ratio,
        float $golpeMax,
        bool $enRiesgoKO,
    ): float {
        if ($faltante <= 0.0
            || ($ratio > $this->pesos->umbralHpSoporte && ! $enRiesgoKO)) {
            return 0.0;
        }

        $fraccion = $accion->move->perfil->curacionFraccion;
        if ($fraccion <= 0.0) {
            $fraccion = self::FRACCION_RECUPERACION;
        }

        $curado = min($hpMax * $fraccion, $faltante);
        $base = $curado / $hpMax * $this->pesos->puntosCuracion;
        $evitaKO = $enRiesgoKO && $golpeMax < $hp + $curado;

        return $base + ($evitaKO ? $this->pesos->puntosCuracionCritica : 0.0);
    }

    private function valorEscudo(
        AccionBatalla $accion,
        float $hp,
        float $golpeMax,
        bool $especial,
    ): float {
        $aliado = $accion->defender;
        $stats = $aliado->pokemon()->battleStats();

        $maxEscudo = $especial ? $stats->spDefenseHp : $stats->defenseHp;
        if ($maxEscudo <= 0.0) {
            return 0.0;
        }

        $actual = $especial ? $aliado->defensaEspHpActual() : $aliado->defensaHpActual();
        $statLanzador = $especial ? StatClave::DEFENSA_ESPECIAL : StatClave::DEFENSA;

        $incremento = $this->calculadoraCuracion->incrementoEscudo(
            $maxEscudo,
            $accion->attacker->obtenerStatEfectivo($statLanzador),
        );
        $deficit = max(0.0, $maxEscudo - $actual);
        $efectivo = min($incremento, $deficit);
        $enRiesgoKO = $golpeMax >= $hp + $actual;

        if ($deficit <= 0.0
            || ($deficit / $maxEscudo < $this->pesos->umbralDeficitEscudo && ! $enRiesgoKO)) {
            return 0.0;
        }

        $base = $efectivo / $maxEscudo * $this->pesos->puntosEscudo;
        $evitaKO = $enRiesgoKO && $golpeMax < $hp + $actual + $efectivo;

        return $base + ($evitaKO ? $this->pesos->puntosCuracionCritica : 0.0);
    }

    private function valorBuffStats(AccionBatalla $accion): float
    {
        $multiplicadores = $accion->defender->multiplicadores();
        $delta = 0.0;

        foreach ($accion->move->selfStatChanges as $cambio) {
            $actual = $multiplicadores->obtenerMultiplicador($cambio->stat);
            $nuevo = min(
                MultiplicadoresStats::DEFAULT_MAX,
                $actual * $cambio->factorTemporal(),
            );
            $delta += max(0.0, $nuevo - $actual);
        }

        if ($delta < self::DELTA_MINIMO_BUFF) {
            return 0.0;
        }

        return $delta * $this->pesos->puntosBuffPorMultiplicador;
    }

    private function valorAquaRing(
        AccionBatalla $accion,
        EfectoAliado $efecto,
        float $hpMax,
        float $faltante,
        float $ratio,
        bool $enRiesgoKO,
    ): float {
        $aliado = $accion->defender;
        $condicion = $aliado->condicion(ClaveCondicion::AQUA_RING);

        if ($faltante <= 0.0
            || ($ratio > $this->pesos->umbralHpSoporte && ! $enRiesgoKO)
            || ($condicion !== null && $condicion->turnos > 1)) {
            return 0.0;
        }

        $aporte = $accion->attacker->pokemon()->battleStats()->hp * self::FRACCION_APORTE_AQUA_RING;
        $porRonda = $this->calculadoraCuracion->curacionConAporteFijo($hpMax, $aporte);
        $curadoEsperado = min($faltante, $porRonda * $efecto->turnos);

        return $curadoEsperado / $hpMax * $this->pesos->puntosAquaRing;
    }

    private function valorCuracionEstado(Combatiente $aliado): float
    {
        if ($aliado->estadoMayor() === EstadoPokemon::NONE) {
            return 0.0;
        }

        return $this->pesos->puntosCuracionEstado;
    }

    private function valorCriticoMejorado(Combatiente $aliado): float
    {
        $condicion = $aliado->condicion(ClaveCondicion::CRITICO_MEJORADO);

        if ($condicion !== null) {
            return 0.0;
        }

        return $this->pesos->puntosCriticoMejorado;
    }
}
