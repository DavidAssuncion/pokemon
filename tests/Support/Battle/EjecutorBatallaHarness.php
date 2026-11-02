<?php

declare(strict_types=1);

namespace Tests\Support\Battle;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Exceptions\LimiteRondasAlcanzado;

/**
 * Ejecuta un escenario con semilla fija, tope de rondas y observador.
 *
 * El generador MT se siembra después de construir la batalla y antes de los
 * efectos de inicio. Siempre se reseeda al terminar para no contaminar otros
 * tests.
 */
final class EjecutorBatallaHarness
{
    public function __construct(
        private readonly ConstructorBatallaHarness $constructor = new ConstructorBatallaHarness(),
        private readonly VerificadorInvariantes $verificador = new VerificadorInvariantes(),
    ) {
    }

    public function ejecutar(EscenarioBatalla $escenario): ResultadoBatallaHarness
    {
        $batalla = $this->constructor->construir($escenario);
        $observador = new ObservadorInvariantes($this->verificador);

        mt_srand($escenario->semilla);

        try {
            $batalla->triggerBattleStartEffects();

            if ($escenario->clima !== null) {
                $batalla->setWeather($escenario->clima);
            }

            if ($escenario->terreno !== null) {
                $batalla->setTerreno($escenario->terreno);
            }

            $estancada = false;

            try {
                $batalla->ejecutarBatalla($escenario->maxRondas, $observador);
            } catch (LimiteRondasAlcanzado) {
                $estancada = true;
            }

            $observador->verificarFin($batalla, $batalla->turnManager()->round());

            return new ResultadoBatallaHarness(
                escenario: $escenario->nombre,
                semilla: $escenario->semilla,
                log: $batalla->log(),
                rondas: $batalla->turnManager()->round(),
                maxRondas: $escenario->maxRondas,
                estancada: $estancada,
                hpFinales: $this->hpFinales($batalla),
                violacionesSeguras: $observador->seguras(),
                violacionesDiagnosticas: $observador->diagnosticas(),
            );
        } finally {
            mt_srand();
        }
    }

    /** @return array<string, float> */
    private function hpFinales(AgregadoBatalla $batalla): array
    {
        $hp = [];

        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            $hp[$combatiente->id()] = $combatiente->hpActual();
        }

        return $hp;
    }
}
