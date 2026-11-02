<?php

declare(strict_types=1);

namespace Tests\Support\Battle;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\TipoTerreno;

/**
 * Violación detectada por el arnés.
 *
 * Las violaciones seguras rompen CI; las diagnósticas solo se acumulan y se
 * reportan porque documentan deuda conocida del motor.
 */
final readonly class ViolacionInvariante
{
    public function __construct(
        public string $invariante,
        public string $momento,
        public int $ronda,
        public ?string $combatienteId,
        public string $detalle,
        public bool $segura,
    ) {
    }

    public function describir(): string
    {
        return sprintf(
            '[%s] %s (%s) ronda %d, combatiente %s: %s',
            $this->segura ? 'segura' : 'diagnóstico',
            $this->invariante,
            $this->momento,
            $this->ronda,
            $this->combatienteId ?? 'batalla',
            $this->detalle,
        );
    }
}

/**
 * Comprueba los invariantes del motor leyendo solo accesores públicos.
 *
 * No lanza excepciones: devuelve la lista de violaciones para que el
 * observador decida qué acumular y el test qué debe romper CI.
 */
final class VerificadorInvariantes
{
    private const float EPSILON = 0.000001;

    /** @return list<ViolacionInvariante> */
    public function verificarTurno(AgregadoBatalla $batalla, int $ronda): array
    {
        return $this->verificarCombatientes($batalla, $ronda, 'turno', false);
    }

    /** @return list<ViolacionInvariante> */
    public function verificarRonda(AgregadoBatalla $batalla, int $ronda): array
    {
        $violaciones = $this->verificarCombatientes($batalla, $ronda, 'ronda', true);

        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            if ($combatiente->estaVivo() && $combatiente->vecesActuadoEstaRonda() > 1) {
                $violaciones[] = $this->diagnostica(
                    '#10',
                    'ronda',
                    $ronda,
                    $combatiente,
                    "actuó {$combatiente->vecesActuadoEstaRonda()} veces en la misma ronda",
                );
            }
        }

        return $violaciones;
    }

    /** @return list<ViolacionInvariante> */
    public function verificarFin(AgregadoBatalla $batalla, int $ronda): array
    {
        $violaciones = $this->verificarCombatientes($batalla, $ronda, 'fin', true);

        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            foreach ($combatiente->pokemon()->moves() as $movimiento) {
                if (! $movimiento instanceof \Src\Battle\Domain\MovimientoBatalla) {
                    continue;
                }

                $usos = $combatiente->usosRestantes($movimiento);
                $maximos = $movimiento->perfil->usosMaximos;

                if ($maximos <= 0 && $usos !== -1) {
                    $violaciones[] = $this->segura(
                        '#7',
                        'fin',
                        $ronda,
                        $combatiente,
                        "{$movimiento->nombre} sin usos máximos devuelve {$usos} (esperado -1)",
                    );
                }

                if ($maximos > 0 && ($usos < 0 || $usos > $maximos)) {
                    $violaciones[] = $this->segura(
                        '#7',
                        'fin',
                        $ronda,
                        $combatiente,
                        "{$movimiento->nombre} tiene {$usos} usos (rango 0..{$maximos})",
                    );
                }
            }
        }

        return $violaciones;
    }

    /** @return list<ViolacionInvariante> */
    private function verificarCombatientes(
        AgregadoBatalla $batalla,
        int $ronda,
        string $momento,
        bool $incluirTerreno,
    ): array {
        $violaciones = [];

        foreach ($batalla->turnManager()->allCombatants() as $combatiente) {
            $stats = $combatiente->pokemon()->battleStats();
            $hp = $combatiente->hpActual();
            $defensaHp = $combatiente->defensaHpActual();
            $defensaEspHp = $combatiente->defensaEspHpActual();

            if ($hp < 0.0 || $hp > $stats->hp + self::EPSILON) {
                $violaciones[] = $this->segura(
                    '#1',
                    $momento,
                    $ronda,
                    $combatiente,
                    "HP {$hp} fuera de [0, {$stats->hp}]",
                );
            }

            if ($defensaHp < 0.0 || $defensaHp > $stats->defenseHp + self::EPSILON) {
                $violaciones[] = $this->segura(
                    '#2',
                    $momento,
                    $ronda,
                    $combatiente,
                    "barrera física {$defensaHp} fuera de [0, {$stats->defenseHp}]",
                );
            }

            if ($defensaEspHp < 0.0 || $defensaEspHp > $stats->spDefenseHp + self::EPSILON) {
                $violaciones[] = $this->segura(
                    '#2',
                    $momento,
                    $ronda,
                    $combatiente,
                    "barrera especial {$defensaEspHp} fuera de [0, {$stats->spDefenseHp}]",
                );
            }

            $hpEnCero = abs($hp) < self::EPSILON;
            if ($hpEnCero !== ! $combatiente->estaVivo()) {
                $violaciones[] = $this->segura(
                    '#3',
                    $momento,
                    $ronda,
                    $combatiente,
                    'hp==0 y estaVivo() no son equivalentes',
                );
            }

            $contadores = [
                'turnosEstado' => $combatiente->turnosEstado(),
                'turnosEstadoMayor' => $combatiente->turnosEstadoMayor(),
                'contadorVenenoGrave' => $combatiente->contadorVenenoGrave(),
                'cargasVeneno' => $combatiente->cargasVeneno(),
                'turnosTemporales' => $combatiente->multiplicadores()->turnosTemporales(),
            ];

            foreach ($contadores as $nombre => $valor) {
                if ($valor < 0) {
                    $violaciones[] = $this->segura(
                        '#5',
                        $momento,
                        $ronda,
                        $combatiente,
                        "{$nombre} negativo ({$valor})",
                    );
                }
            }

            foreach ($combatiente->condiciones()->all() as $condicion) {
                if ($condicion->turnos < 0) {
                    $violaciones[] = $this->segura(
                        '#5',
                        $momento,
                        $ronda,
                        $combatiente,
                        "condición {$condicion->clave->value} con turnos negativos ({$condicion->turnos})",
                    );
                }
            }

            if (! $combatiente->estaVivo() && $combatiente->estado() !== EstadoPokemon::NONE) {
                $violaciones[] = $this->diagnostica(
                    '#4',
                    $momento,
                    $ronda,
                    $combatiente,
                    "conserva el estado {$combatiente->estado()->value} tras debilitarse",
                );
            }

            if (! $combatiente->estaVivo() && ! $combatiente->condiciones()->isEmpty()) {
                $violaciones[] = $this->diagnostica(
                    '#12',
                    $momento,
                    $ronda,
                    $combatiente,
                    'conserva condiciones volátiles tras debilitarse',
                );
            }
        }

        if ($incluirTerreno) {
            $sinTerreno = $batalla->terreno() === TipoTerreno::NONE;
            $sinTurnos = $batalla->turnosTerreno() === 0;

            if ($sinTerreno !== $sinTurnos) {
                $violaciones[] = new ViolacionInvariante(
                    invariante: '#6',
                    momento: $momento,
                    ronda: $ronda,
                    combatienteId: null,
                    detalle: "terreno={$batalla->terreno()->value} con turnosTerreno={$batalla->turnosTerreno()}",
                    segura: true,
                );
            }
        }

        return $violaciones;
    }

    private function segura(
        string $invariante,
        string $momento,
        int $ronda,
        Combatiente $combatiente,
        string $detalle,
    ): ViolacionInvariante {
        return new ViolacionInvariante(
            invariante: $invariante,
            momento: $momento,
            ronda: $ronda,
            combatienteId: $combatiente->id(),
            detalle: $detalle,
            segura: true,
        );
    }

    private function diagnostica(
        string $invariante,
        string $momento,
        int $ronda,
        Combatiente $combatiente,
        string $detalle,
    ): ViolacionInvariante {
        return new ViolacionInvariante(
            invariante: $invariante,
            momento: $momento,
            ronda: $ronda,
            combatienteId: $combatiente->id(),
            detalle: $detalle,
            segura: false,
        );
    }
}
