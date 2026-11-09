<?php

declare(strict_types=1);

namespace App\Livewire\Combate\Soporte;

use App\Livewire\Combate\Soporte\DTO\ResultadoAccionBatalla;
use App\Livewire\Combate\Soporte\DTO\SnapshotBatalla;
use Src\Battle\App\UsarObjetoEnCombate;
use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AccionObjeto;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Ejecucion\EjecutorEfectoAliado;
use Src\Battle\Domain\EquipoBatalla;
use Src\Battle\Domain\MovimientoBatalla;
use Src\Battle\Domain\ServicioEjecucionBatalla;
use Src\Battle\Domain\ValueObjects\EfectoAliado;

/**
 * Aplica una acción de batalla ya resuelta y devuelve el log y los eventos
 * que produciría. Sin estado: no toca sesión, `pendingAction`, animación ni
 * la vista del componente.
 */
final class ResolutorAccionBatalla
{
    public function __construct(
        private readonly GrabadorEventosBatalla $grabador,
        private readonly EjecutorEfectoAliado $ejecutorAliado,
    ) {
    }

    public function aplicarMovimiento(
        AgregadoBatalla $battle,
        Combatiente $actor,
        Combatiente $objetivo,
        MovimientoBatalla $movimiento,
        ServicioEjecucionBatalla $servicio,
        string $prefijoMovimiento,
        string $prefijoBase,
        bool $grabando,
        int $tickEvento,
        int $secuenciaEvento,
    ): ResultadoAccionBatalla {
        /** @var list<string> $log */
        $log = [];
        /** @var list<array<string, mixed>> $eventos */
        $eventos = [];
        $secuencia = $secuenciaEvento;

        $defenderTeam = $this->defenderTeam($battle, $objetivo);

        $accion = new AccionBatalla(
            attacker: $actor,
            defender: $objetivo,
            move: $movimiento,
            defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
            weather: $battle->weather(),
            terreno: $battle->terreno(),
        );

        $previo = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();
        $actor->effects()->dispararInicioTurno($actor, $battle);
        $resultado = $servicio->calcularYAplicarDano($accion, $battle);
        $fallo = $resultado->golpes === 0 && $movimiento->perfil->turnosDanoDiferido <= 0;
        $actual = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();

        $this->acumularEvento($eventos, 'damage', $previo, $actual, [
            'actorId' => $actor->id(),
            'objetivoId' => $objetivo->id(),
            'movimiento' => $movimiento->nombre,
            'texto' => $fallo ? '¡Falló!' : '',
            'forzar' => $fallo,
        ], $grabando, $tickEvento, $secuencia);
        $previo = $actual;

        $daño = $resultado->dano;
        $directPct = $resultado->directPct;

        $log[] = $prefijoMovimiento.$servicio->generarLogMovimiento(
            $actor,
            $objetivo,
            $movimiento,
            $daño,
            $directPct,
            $defenderTeam->tieneVanguardiaViva(),
        );
        if ($fallo) {
            $log[] = $prefijoBase."¡{$movimiento->nombre} falla!";
        }

        // El log y el evento se derivan del delta real de `status`, no del
        // movimiento: el estado puede fallar la tirada o redirigirse.
        $objetivos = $resultado->objetivos;
        $antesEstado = $grabando ? $previo : $this->grabador->capturar($battle);
        foreach ($objetivos as $target) {
            $servicio->aplicarEstado($target, $movimiento, $battle, $actor);
        }

        $actual = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();
        $cambiosEstado = $this->grabador->cambiosDeEstadoEnLog($battle, $antesEstado, $prefijoBase);
        if ($cambiosEstado !== null) {
            $log[] = $cambiosEstado;
        }

        $this->acumularEvento($eventos, 'status', $antesEstado, $actual, [
            'actorId' => $actor->id(),
            'objetivoId' => $objetivo->id(),
            'texto' => $cambiosEstado ?? '',
        ], $grabando, $tickEvento, $secuencia);
        $previo = $actual;

        if ($resultado->golpes > 0) {
            $this->aplicarCambiosStats($log, $actor, $movimiento, true);
            foreach ($objetivos as $target) {
                $this->aplicarCambiosStats($log, $target, $movimiento, false);
            }
        }

        $battle->subject()->notifyDamaged($objetivo, $daño);
        $objetivo->dispararDanioRecibido($daño, $battle);
        $actor->dispararDanioInfligido($objetivo, $daño, $battle);
        $despuesEfectos = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();
        $this->acumularEvento($eventos, 'effect', $previo, $despuesEfectos, [
            'actorId' => $actor->id(),
            'objetivoId' => $objetivo->id(),
            'texto' => 'Efecto de combate',
        ], $grabando, $tickEvento, $secuencia);

        $battle->turnManager()->consumeAction($actor);
        $actor->consumirMovimiento($movimiento);
        $actor->effects()->dispararFinTurno($actor, $battle);

        if (! $objetivo->estaVivo()) {
            $log[] = "¡{$objetivo->nombre()} se ha debilitado!";
        }

        $nuevosDebilitados = $battle->procesarDebilitamientos();
        if ($nuevosDebilitados > 0 && $actor->estaVivo()) {
            $actor->otorgarAccionExtra();
        }

        if (! $actor->estaVivo()) {
            $log[] = "¡{$actor->nombre()} se ha debilitado!";
        }

        return new ResultadoAccionBatalla($log, $eventos);
    }

    /**
     * Aplica un movimiento de apoyo sobre un aliado vivo. No pasa por daño,
     * precisión, crítico, inmunidades ni reacciones: solo ejecuta el efecto,
     * consume turno/PP y emite un único evento `effect`.
     */
    public function aplicarMovimientoAliado(
        AgregadoBatalla $battle,
        Combatiente $actor,
        Combatiente $aliado,
        MovimientoBatalla $movimiento,
        EfectoAliado $efecto,
        bool $grabando,
        int $tickEvento,
        int $secuenciaEvento,
    ): ResultadoAccionBatalla {
        /** @var list<string> $log */
        $log = [];
        /** @var list<array<string, mixed>> $eventos */
        $eventos = [];
        $secuencia = $secuenciaEvento;

        $defenderTeam = $this->defenderTeam($battle, $aliado);
        $accion = new AccionBatalla(
            attacker: $actor,
            defender: $aliado,
            move: $movimiento,
            defenderTeamHasVanguard: $defenderTeam->tieneVanguardiaViva(),
            weather: $battle->weather(),
            equipoDefensor: $defenderTeam,
            terreno: $battle->terreno(),
        );

        $previo = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();
        $actor->effects()->dispararInicioTurno($actor, $battle);
        $actor->romperCombo();

        $objetivoLog = $aliado->id() === $actor->id() ? 'sí mismo' : $aliado->nombre();
        $log[] = "{$actor->nombre()} usa {$movimiento->nombre} sobre {$objetivoLog}";
        $texto = $this->ejecutorAliado->ejecutar($accion, $efecto, $battle);

        $actual = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();
        $this->acumularEvento($eventos, 'effect', $previo, $actual, [
            'actorId' => $actor->id(),
            'objetivoId' => $aliado->id(),
            'texto' => $texto,
            'forzar' => true,
        ], $grabando, $tickEvento, $secuencia);

        $battle->turnManager()->consumeAction($actor);
        $actor->consumirMovimiento($movimiento);
        $actor->effects()->dispararFinTurno($actor, $battle);
        $battle->procesarDebilitamientos();

        return new ResultadoAccionBatalla($log, $eventos);
    }

    public function resolverObjeto(
        AgregadoBatalla $battle,
        Combatiente $actor,
        Combatiente $objetivo,
        AccionObjeto $accion,
        UsarObjetoEnCombate $usarObjeto,
        int $usuarioId,
        bool $grabando,
        int $tickEvento,
        int $secuenciaEvento,
    ): ResultadoAccionBatalla {
        /** @var list<string> $log */
        $log = [];
        /** @var list<array<string, mixed>> $eventos */
        $eventos = [];
        $secuencia = $secuenciaEvento;

        $previo = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();
        $resultado = $usarObjeto->handle($usuarioId, $battle, $accion);

        if (! $resultado->exito) {
            $log[] = $resultado->motivo ?? 'No se pudo usar el objeto.';

            return new ResultadoAccionBatalla($log, $eventos);
        }

        $log[] = $resultado->log;
        $battle->turnManager()->consumeAction($actor);
        $actor->effects()->dispararFinTurno($actor, $battle);

        if (! $objetivo->estaVivo()) {
            $log[] = "¡{$objetivo->nombre()} se ha debilitado!";
        }

        $nuevosDebilitados = $battle->procesarDebilitamientos();
        if ($nuevosDebilitados > 0 && $actor->estaVivo()) {
            $actor->otorgarAccionExtra();
        }

        $despues = $grabando ? $this->grabador->capturar($battle) : SnapshotBatalla::vacia();
        $this->acumularEvento($eventos, 'item', $previo, $despues, [
            'actorId' => $actor->id(),
            'objetivoId' => $objetivo->id(),
            'texto' => $resultado->log,
        ], $grabando, $tickEvento, $secuencia);

        return new ResultadoAccionBatalla($log, $eventos);
    }

    private function defenderTeam(AgregadoBatalla $battle, Combatiente $defender): EquipoBatalla
    {
        return $battle->team1->findCombatant($defender) !== null
            ? $battle->team1
            : $battle->team2;
    }

    /**
     * @param  list<string>  $log
     */
    private function aplicarCambiosStats(
        array &$log,
        Combatiente $combatant,
        MovimientoBatalla $move,
        bool $isActor,
    ): void {
        $changes = $isActor ? $move->selfStatChanges : $move->targetStatChanges;
        if ($changes->isEmpty()) {
            return;
        }

        foreach ($changes as $cambio) {
            $label = $cambio->stat->label();
            $factor = $cambio->factorTemporal();
            $combatant->setMultiplicadores($cambio->aplicadoTemporalEn($combatant->multiplicadores()));
            $verb = $factor > 1.0 ? 'subió' : 'bajó';
            $porcentaje = (int) round(abs(($factor - 1.0) * 100));
            $log[] = "{$combatant->nombre()} {$verb} {$label} en {$porcentaje}%!";
        }
    }

    /**
     * @param  list<array<string, mixed>>  $eventos
     * @param  array<string, mixed>  $extra
     */
    private function acumularEvento(
        array &$eventos,
        string $tipo,
        SnapshotBatalla $antes,
        SnapshotBatalla $despues,
        array $extra,
        bool $grabando,
        int $tick,
        int &$secuencia,
    ): void {
        if (! $grabando) {
            return;
        }

        $evento = $this->grabador->registrar($tipo, $antes, $despues, $tick, $secuencia, $extra);
        if ($evento !== null) {
            $eventos[] = $evento;
            $secuencia++;
        }
    }
}
