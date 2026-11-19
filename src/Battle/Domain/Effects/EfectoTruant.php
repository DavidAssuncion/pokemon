<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlPerderTurno;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;

/**
 * Ausente: alterna un turno de acción y otro de descanso.
 *
 * La ausencia se anuncia al inicio de la ronda con la condición volátil
 * `AUSENTE`; `puedeActuar()` la consume en el hueco del portador. Si recibe
 * daño directo antes de su hueco, se enfada: cancela la ausencia, gana un
 * boost temporal de Ataque y actúa ese turno. Si descansa sin enfadarse,
 * recupera 1/16 de ambas barreras en el momento de perder el turno.
 */
final class EfectoTruant implements InterfazEfecto, ReaccionaAlDanioRecibido, ReaccionaAlPerderTurno
{
    use ComportamientosPorDefecto;

    /** 1/16 de las barreras máximas, en puntos porcentuales. */
    private const float PORCENTAJE_ESCUDOS_DESCANSO = 6.25;

    /** Etapas de Ataque del enfado (×1.30 temporal). */
    private const int ETAPAS_ENFADO = 1;

    /** La próxima ronda será de descanso. */
    private bool $descansar = false;

    /** Esta ronda ha sido anunciada y aún no se ha cobrado el descanso. */
    private bool $ausenciaAnunciada = false;

    /** Se aplicó el boost temporal de Ataque y hay que retirarlo al terminar el turno. */
    private bool $enfadado = false;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->retirarEnfadoSiProcede($portador);
        $portador->quitarCondicion(ClaveCondicion::AUSENTE);
        $this->ausenciaAnunciada = false;

        if (! $this->descansar || ! $portador->estaVivo()) {
            return;
        }

        $this->descansar = false;
        $this->ausenciaAnunciada = true;
        $portador->agregarCondicion(new CondicionVolatil(ClaveCondicion::AUSENTE, 1));
    }

    public function alRecibirDanio(
        Combatiente $portador,
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        if (! $this->ausenciaAnunciada
            || ! $portador->tieneCondicion(ClaveCondicion::AUSENTE)
            || ! $resultado->huboDano()
            || ! $portador->estaVivo()) {
            return;
        }

        $portador->quitarCondicion(ClaveCondicion::AUSENTE);
        $this->ausenciaAnunciada = false;
        $this->enfadado = true;

        $cambio = CambioStat::desdeEtapas(StatClave::ATAQUE, self::ETAPAS_ENFADO);
        $portador->setMultiplicadores(
            $cambio->aplicadoTemporalEn($portador->multiplicadores()),
        );

        $battle->agregarLog(
            "¡{$portador->nombre()} se enfada por el daño y cancela su ausencia! Su Ataque sube.",
        );
    }

    public function alPerderTurno(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if (! $this->ausenciaAnunciada || $portador->tieneCondicion(ClaveCondicion::AUSENTE)) {
            return;
        }

        $this->ausenciaAnunciada = false;

        if (! $portador->estaVivo()) {
            return;
        }

        $antes = $portador->defensaHpActual() + $portador->defensaEspHpActual();
        $portador->curarBarreras(self::PORCENTAJE_ESCUDOS_DESCANSO);
        if ($portador->defensaHpActual() + $portador->defensaEspHpActual() > $antes) {
            $battle->agregarLog(
                "{$portador->nombre()} recupera escudos mientras descansa.",
            );
        }
    }

    public function onTurnEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->retirarEnfadoSiProcede($portador);

        if (! $portador->estaVivo()) {
            return;
        }

        if (! $this->tieneCargasPendientes($portador)) {
            $this->descansar = true;
        }
    }

    public function onRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $this->retirarEnfadoSiProcede($portador);
        $this->ausenciaAnunciada = false;
        $portador->quitarCondicion(ClaveCondicion::AUSENTE);
    }

    public function onFainted(Combatiente $portador): void
    {
        $this->retirarEnfadoSiProcede($portador);
        $portador->quitarCondicion(ClaveCondicion::AUSENTE);

        $this->ausenciaAnunciada = false;
        $this->descansar = false;
        $this->enfadado = false;
    }

    public function __clone(): void
    {
        $this->ausenciaAnunciada = false;
        $this->enfadado = false;
        $this->descansar = false;
    }

    private function tieneCargasPendientes(Combatiente $portador): bool
    {
        foreach ($portador->cargasMovimientoPorNombre()->toArray() as $carga) {
            if ($carga > 0) {
                return true;
            }
        }

        return false;
    }

    private function retirarEnfadoSiProcede(Combatiente $portador): void
    {
        if (! $this->enfadado) {
            return;
        }

        $this->enfadado = false;
        $portador->setMultiplicadores(
            $portador->multiplicadores()->retirarTemporal(
                StatClave::ATAQUE,
                CambioStat::desdeEtapas(StatClave::ATAQUE, self::ETAPAS_ENFADO)->factorTemporal(),
            ),
        );
    }
}
