<?php

declare(strict_types=1);

namespace Tests\Support\Battle;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Observer\ObservadorEjecucionBatalla;

/**
 * Observador de ejecución que verifica invariantes sin interrumpir la batalla.
 *
 * Las violaciones seguras se acumulan para que el test rompa CI; las
 * diagnósticas se conservan para el informe de la campaña.
 */
final class ObservadorInvariantes implements ObservadorEjecucionBatalla
{
    /** @var list<ViolacionInvariante> */
    private array $violacionesSeguras = [];

    /** @var list<ViolacionInvariante> */
    private array $violacionesDiagnosticas = [];

    /** @var array<string, float> */
    private array $hpAnterior = [];

    private int $entradasLogAnterior = 0;

    private bool $tieneLineaBase = false;

    public function __construct(
        private readonly VerificadorInvariantes $verificador,
    ) {
    }

    public function alFinalizarTurno(int $ronda, Combatiente $actor, AgregadoBatalla $batalla): void
    {
        $this->acumular($this->verificador->verificarTurno($batalla, $ronda));
        $this->revisarReduccionesDeHp($batalla, $ronda);
    }

    public function alFinalizarRonda(int $ronda, AgregadoBatalla $batalla): void
    {
        $this->acumular($this->verificador->verificarRonda($batalla, $ronda));
    }

    /**
     * Comprobación final tras la batalla o tras alcanzar el tope de rondas.
     */
    public function verificarFin(AgregadoBatalla $batalla, int $ronda): void
    {
        $this->acumular($this->verificador->verificarFin($batalla, $ronda));
    }

    /** @return list<ViolacionInvariante> */
    public function seguras(): array
    {
        return $this->violacionesSeguras;
    }

    /** @return list<ViolacionInvariante> */
    public function diagnosticas(): array
    {
        return $this->violacionesDiagnosticas;
    }

    /** @param list<ViolacionInvariante> $violaciones */
    private function acumular(array $violaciones): void
    {
        foreach ($violaciones as $violacion) {
            if ($violacion->segura) {
                $this->violacionesSeguras[] = $violacion;

                continue;
            }

            $this->violacionesDiagnosticas[] = $violacion;
        }
    }

    private function revisarReduccionesDeHp(AgregadoBatalla $batalla, int $ronda): void
    {
        $entradasLog = $batalla->log()->entries()->count();

        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            $hpAnterior = $this->hpAnterior[$combatiente->id()] ?? $combatiente->hpActual();
            $hpActual = $combatiente->hpActual();

            if ($this->tieneLineaBase
                && $hpActual < $hpAnterior - 0.000001
                && $entradasLog === $this->entradasLogAnterior) {
                $this->violacionesDiagnosticas[] = new ViolacionInvariante(
                    invariante: '#11',
                    momento: 'turno',
                    ronda: $ronda,
                    combatienteId: $combatiente->id(),
                    detalle: "el HP bajó de {$hpAnterior} a {$hpActual} sin nuevas entradas de log",
                    segura: false,
                );
            }

            $this->hpAnterior[$combatiente->id()] = $hpActual;
        }

        $this->entradasLogAnterior = $entradasLog;
        $this->tieneLineaBase = true;
    }
}
