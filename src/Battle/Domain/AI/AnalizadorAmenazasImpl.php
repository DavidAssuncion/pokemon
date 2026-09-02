<?php

declare(strict_types=1);

namespace Src\Battle\Domain\AI;

use Src\Battle\Domain\AI\ValueObjects\EvaluacionAmenaza;
use Src\Battle\Domain\Combatiente;

/**
 * Analiza la amenaza de cada enemigo vivo calculando ThreatScore compuesto.
 * Usa PesosAmenaza para que los valores sean configurables y escalables.
 */
class AnalizadorAmenazasImpl implements AnalizadorAmenazas
{
    public function __construct(
        private readonly CalculadoraDanioIA $calculadoraDanio,
        private readonly PesosAmenaza $pesos,
    ) {
    }

    public function analizar(ContextoDecisionIA $contexto): array
    {
        $amenazas = [];

        foreach ($contexto->enemigos as $enemigo) {
            $amenazas[] = $this->evaluarEnemigo($contexto, $enemigo);
        }

        // Ordenar por score descendente
        usort($amenazas, fn (EvaluacionAmenaza $a, EvaluacionAmenaza $b) => $b->score <=> $a->score);

        return array_values($amenazas);
    }

    private function evaluarEnemigo(ContextoDecisionIA $contexto, Combatiente $enemigo): EvaluacionAmenaza
    {
        $amenazaOfensiva = $this->calcularAmenazaOfensiva($contexto, $enemigo);
        $amenazaKO = $this->calcularAmenazaKO($contexto, $enemigo);
        $amenazaVelocidad = $this->calcularAmenazaVelocidad($contexto, $enemigo);
        $amenazaSetup = $this->calcularAmenazaSetup($enemigo);
        $amenazaEstrategica = $this->calcularAmenazaEstrategica($enemigo);

        $score = ($amenazaOfensiva * $this->pesos->pesoOfensiva)
            + ($amenazaKO * $this->pesos->pesoKO)
            + ($amenazaVelocidad * $this->pesos->pesoVelocidad)
            + ($amenazaSetup * $this->pesos->pesoSetup)
            + ($amenazaEstrategica * $this->pesos->pesoEstrategica);

        return new EvaluacionAmenaza(
            enemigo: $enemigo,
            amenazaOfensiva: $amenazaOfensiva,
            amenazaKO: $amenazaKO,
            amenazaVelocidad: $amenazaVelocidad,
            amenazaSetup: $amenazaSetup,
            amenazaEstrategica: $amenazaEstrategica,
            score: $score,
        );
    }

    /**
     * Máximo daño que puede infligir el enemigo a cualquier aliado.
     */
    private function calcularAmenazaOfensiva(ContextoDecisionIA $contexto, Combatiente $enemigo): float
    {
        $maxDano = 0.0;

        foreach ($contexto->aliados as $aliado) {
            $estimacion = $this->calculadoraDanio->mejorEstimacionContra($enemigo, $aliado, $contexto->battle);
            if ($estimacion !== null && $estimacion->esperado > $maxDano) {
                $maxDano = $estimacion->esperado;
            }
        }

        return $maxDano;
    }

    /**
     * Puntos de KO si puede derribar a algún aliado, 0 si no.
     */
    private function calcularAmenazaKO(ContextoDecisionIA $contexto, Combatiente $enemigo): float
    {
        foreach ($contexto->aliados as $aliado) {
            $estimacion = $this->calculadoraDanio->mejorEstimacionContra($enemigo, $aliado, $contexto->battle);
            if ($estimacion !== null && $estimacion->probabilidadKO > 0) {
                return $this->pesos->puntosKOPosible;
            }
        }

        return 0.0;
    }

    /**
     * Puntos si el enemigo actúa antes que el actor.
     */
    private function calcularAmenazaVelocidad(ContextoDecisionIA $contexto, Combatiente $enemigo): float
    {
        return $enemigo->velocidadAcumulada() > $contexto->actor->velocidadAcumulada()
            ? $this->pesos->puntosVelocidadSuperior
            : 0.0;
    }

    /**
     * Puntos por etapas positivas del enemigo.
     */
    private function calcularAmenazaSetup(Combatiente $enemigo): float
    {
        $etapas = $enemigo->etapas()->toArray();
        $totalPositivas = 0;
        foreach ($etapas as $valor) {
            if ($valor > 0) {
                $totalPositivas += $valor;
            }
        }

        return $this->pesos->puntosPorEtapaPositiva * $totalPositivas;
    }

    /**
     * Puntos por objetos y efectos estratégicos del enemigo.
     * Usa PesosAmenaza para que sea escalable.
     */
    private function calcularAmenazaEstrategica(Combatiente $enemigo): float
    {
        $puntos = 0.0;

        // Objetos registrados en PesosAmenaza
        if ($enemigo->item() !== '') {
            $puntos += $this->pesos->amenazaItem($enemigo->item());
        }

        // Efectos/habilidades registradas en PesosAmenaza
        foreach ($this->pesos->efectosRegistrados() as $clave => $pesoEfecto) {
            if ($enemigo->tieneEfecto($clave)) {
                $puntos += $pesoEfecto;
            }
        }

        return $puntos;
    }
}
