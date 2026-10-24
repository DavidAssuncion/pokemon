<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\AI\NivelDificultad;
use Src\Battle\Domain\AI\SelectorAccionIA;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Battle\Domain\Observer\SujetoBatalla;
use Src\Battle\Domain\ValueObjects\BattleLog;
use Src\Battle\Presentation\DTOAccionBatalla;

class AgregadoBatalla
{
    private GestorTurnos $turnManager;

    private CadenaDanio $damageChain;

    private SujetoBatalla $subject;

    private BattleLog $log;

    private ?DTOAccionBatalla $pendingAction = null;

    private TipoClima $weather = TipoClima::NONE;

    private TipoTerreno $terreno = TipoTerreno::NONE;

    private int $turnosTerreno = 0;

    /** @var list<array<string, mixed>> */
    private array $danioDiferido = [];

    private ?CalculadorDañoClima $calculadorClima = null;

    private ?SelectorAccionIA $selectorAccion = null;

    private NivelDificultad $dificultad = NivelDificultad::PERFECTA;

    private int $rondaPreparada = 0;

    private int $trickRoomTurnos = 0;

    /**
     * Secuencia para generar ids únicos de invocaciones. Se serializa con el
     * agregado para que dos invocaciones de la misma batalla no colisionen.
     */
    private int $contadorInvocaciones = 0;

    /**
     * Id del portador de Gas Reactivo. Mientras esté vivo, el resto de
     * combatientes tienen su habilidad anulada.
     */
    private ?string $gasReactivoId = null;

    public function __construct(
        public readonly EquipoBatalla $team1,
        public readonly EquipoBatalla $team2,
    ) {
        $this->turnManager = new GestorTurnos($team1, $team2);
        $this->damageChain = new CadenaDanio();
        $this->subject = new SujetoBatalla();
        $this->log = BattleLog::vacia();
    }

    public function __clone(): void
    {
        $this->team1 = clone $this->team1;
        $this->team2 = clone $this->team2;
        $this->turnManager = new GestorTurnos($this->team1, $this->team2);
        $this->subject = new SujetoBatalla();
        $this->selectorAccion = null;
    }

    // ─── Getters ──────────────────────────────────────────────

    public function turnManager(): GestorTurnos
    {
        return $this->turnManager;
    }

    public function damageChain(): CadenaDanio
    {
        return $this->damageChain;
    }

    public function subject(): SujetoBatalla
    {
        return $this->subject;
    }

    public function log(): BattleLog
    {
        return $this->log;
    }

    public function pendingAction(): ?DTOAccionBatalla
    {
        return $this->pendingAction;
    }

    public function setPendingAction(?DTOAccionBatalla $pendingAction): void
    {
        $this->pendingAction = $pendingAction;
    }

    public function weather(): TipoClima
    {
        return $this->weather;
    }

    public function setWeather(TipoClima $weather): void
    {
        $this->weather = $weather;

        foreach ($this->turnManager->allCombatants() as $combatiente) {
            $combatiente->setClima($weather);
        }
    }

    public function terreno(): TipoTerreno
    {
        return $this->terreno;
    }

    public function setTerreno(TipoTerreno $terreno, int $turnos = 5): void
    {
        $this->terreno = $terreno;
        $this->turnosTerreno = $terreno === TipoTerreno::NONE ? 0 : max(1, $turnos);
    }

    public function turnosTerreno(): int
    {
        return $this->turnosTerreno;
    }

    /**
     * @param  array<string, mixed>  $dato
     */
    public function agregarDanioDiferido(array $dato): void
    {
        $this->danioDiferido[] = $dato;
    }

    public function procesarDanioDiferido(): void
    {
        $ronda = $this->turnManager->round();
        $pendientes = [];
        foreach ($this->danioDiferido as $dato) {
            $rondaDato = isset($dato['ronda']) ? (int) $dato['ronda'] : 0;
            if ($rondaDato <= $ronda) {
                $objId = isset($dato['objetivoId']) ? $dato['objetivoId'] : '';
                $objetivo = $this->buscarCombatientePorId($objId);
                if ($objetivo !== null && ! $objetivo->estaVivo()) {
                    $rival = $this->equipoDe($objetivo);
                    $objetivo = $rival?->combatientesCollection()->vivos()->primero();
                }

                if ($objetivo !== null && $objetivo->estaVivo()) {
                    $atacante = $this->buscarCombatientePorId(
                        isset($dato['atacanteId']) ? $dato['atacanteId'] : '',
                    );
                    $potencia = max(1, (int) ($dato['potencia'] ?? 1));
                    $nivel = max(1, (int) ($dato['nivel'] ?? 50));
                    $atk = max(1.0, (float) ($dato['atk'] ?? 1.0));
                    $def = max(1.0, $objetivo->obtenerStatEfectivo(
                        \Src\Battle\Domain\Enums\StatClave::DEFENSA_ESPECIAL,
                    ));
                    $tipo = \Src\Shared\Tipos\TipoPokemon::tryFrom((int) ($dato['tipo'] ?? 1))
                        ?? \Src\Shared\Tipos\TipoPokemon::NORMAL;
                    $dano = \Src\Battle\Domain\Chain\ManejadorDanioBase::formulaBase(
                        $nivel,
                        $potencia,
                        $atk,
                        $def,
                    );
                    $dano *= $objetivo->efectividadContra($tipo);
                    $dano *= (float) ($dato['stab'] ?? 1.0);
                    $objetivo->recibirDaño(max(1.0, floor($dano)), true, 0.0);
                    $mov = isset($dato['movimiento']) ? $dato['movimiento'] : 'Ataque diferido';
                    $this->agregarLog($mov.' inflige daño a '.$objetivo->nombre());
                    $objetivo->registrarDanoRecibido(max(1.0, floor($dano)), $atacante?->id() ?? '');
                    if (! $objetivo->estaVivo()) {
                        $this->agregarLog('¡'.$objetivo->nombre().' se ha debilitado!');
                        $objetivo->notificarDebilitamiento($this);
                    }
                }
            } else {
                $pendientes[] = $dato;
            }
        }
        $this->danioDiferido = $pendientes;
    }

    /**
     * Al deserializar de sesión, rehidrata el clima en todos los combatientes
     * (el payload guarda el clima del agregado y de cada combatiente).
     */
    public function __wakeup(): void
    {
        $this->setWeather($this->weather);
        $this->setTrickRoom($this->trickRoomTurnos);
        $this->sincronizarGasReactivo();
    }

    public function activarGasReactivo(Combatiente $portador): void
    {
        if ($this->gasReactivoId === $portador->id()) {
            return;
        }

        if ($this->gasReactivoId !== null) {
            $this->sincronizarGasReactivo();
        }

        $this->gasReactivoId = $portador->id();

        foreach ($this->turnManager->allCombatants() as $combatiente) {
            if ($combatiente !== $portador) {
                $combatiente->anularHabilidad(999, true);
            }
        }

        $this->agregarLog("¡{$portador->nombre()} libera Gas Reactivo!");
    }

    /**
     * Restaura las habilidades si el portador del gas ya no está vivo.
     */
    public function sincronizarGasReactivo(): void
    {
        if ($this->gasReactivoId === null) {
            return;
        }

        $portador = $this->buscarCombatientePorId($this->gasReactivoId);
        if ($portador !== null && $portador->estaVivo()) {
            return;
        }

        $this->gasReactivoId = null;
        foreach ($this->turnManager->allCombatants() as $combatiente) {
            // No restaurar habilidades anuladas por otra fuente (Jugo Gástrico).
            if ($combatiente->habilidadAnuladaPorGas()) {
                $combatiente->restaurarHabilidad();
            }
        }
        $this->agregarLog('El Gas Reactivo se disipa');
    }

    private function getCalculadorClima(): CalculadorDañoClima
    {
        return $this->calculadorClima ??= new CalculadorDañoClima();
    }

    private function getSelectorAccion(): SelectorAccionIA
    {
        return $this->selectorAccion ??= new SelectorAccionIA();
    }

    public function setDificultad(NivelDificultad $dificultad): void
    {
        $this->dificultad = $dificultad;
    }

    public function rondaPreparada(): int
    {
        return $this->rondaPreparada;
    }

    public function setRondaPreparada(int $ronda): void
    {
        $this->rondaPreparada = $ronda;
    }

    public function setTrickRoom(int $turnos): void
    {
        $this->trickRoomTurnos = max(0, $turnos);
        $this->turnManager->setInvertirVelocidad($this->trickRoomTurnos > 0);
    }

    public function trickRoomActivo(): bool
    {
        return $this->trickRoomTurnos > 0;
    }

    public function trickRoomTurnos(): int
    {
        return $this->trickRoomTurnos;
    }

    /**
     * Incorpora un combatiente a un equipo durante la batalla en curso
     * (invocaciones; permite más de 3 combatientes por bando).
     *
     * No dispara efectos de entrada ni registra log: el combatiente entra en
     * el orden de la ronda actual (contador de acciones a 0) y comparte la
     * colección que ya gestiona GestorTurnos.
     */
    public function incorporarCombatiente(EquipoBatalla $equipo, Combatiente $combatiente, Posicion $posicion): void
    {
        $equipo->agregarCombatiente($combatiente, $posicion);
        $combatiente->setVecesActuadoEstaRonda(0);
    }

    /**
     * Crea una invocación temporal a partir de una copia del invocador.
     * Mantiene la apariencia y las estadísticas base, pero limpia estados,
     * objetos, condiciones y medidores. El ciclo de vida se descuenta en
     * triggerRoundEndEffects().
     */
    public function invocarCombatiente(
        Combatiente $invocador,
        int $turnos,
        float $fraccionHp,
        int $maxInvocaciones,
        string $nombre,
    ): ?Combatiente {
        $equipo = $this->equipoDe($invocador);
        if ($equipo === null || ! $invocador->estaVivo()) {
            return null;
        }

        $activas = 0;
        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            if ($aliado->esInvocacion() && $aliado->invocadoPorId() === $invocador->id()) {
                $activas++;
            }
        }

        $limite = max(1, $maxInvocaciones);
        if ($activas >= $limite) {
            $this->agregarLog("{$invocador->nombre()} ya alcanzó el límite de invocaciones ({$limite})");

            return null;
        }

        $invocacion = clone $invocador;
        $numero = ++$this->contadorInvocaciones;
        $id = $invocador->id().'-inv-'.$numero;
        $nombreFinal = $nombre !== '' ? $nombre : "Esbirro de {$invocador->nombre()}";

        $invocacion->prepararComoInvocacion(
            id: $id,
            nombre: $nombreFinal,
            invocadoPorId: $invocador->id(),
            turnos: max(1, $turnos),
            fraccionHp: $fraccionHp,
        );
        $invocacion->setRondaActual($this->turnManager->round());
        $invocacion->setClima($this->weather);

        $this->incorporarCombatiente($equipo, $invocacion, $invocador->posicion());
        $equipo->normalizarCubridores();
        $this->agregarLog("{$invocador->nombre()} invoca a {$nombreFinal}");

        return $invocacion;
    }

    public function equipoDe(Combatiente $combatiente): ?EquipoBatalla
    {
        if ($this->team1->findCombatant($combatiente) !== null) {
            return $this->team1;
        }

        if ($this->team2->findCombatant($combatiente) !== null) {
            return $this->team2;
        }

        return null;
    }

    public function equipoRivalDe(Combatiente $combatiente): ?EquipoBatalla
    {
        $equipo = $this->equipoDe($combatiente);

        return $equipo === null
            ? null
            : ($equipo === $this->team1 ? $this->team2 : $this->team1);
    }

    // ─── Log ──────────────────────────────────────────────────

    public function agregarLog(string $entry): void
    {
        $this->log = $this->log->agregar($entry);
    }

    public function limpiarLog(): void
    {
        $this->log = $this->log->limpiar();
    }

    /**
     * Dispara onBattleStart en todos los efectos de todos los combatientes.
     */
    public function triggerBattleStartEffects(): void
    {
        foreach ($this->turnManager->allCombatants() as $c) {
            $c->effects()->triggerBattleStart($c, $this);
        }
    }

    /**
     * Dispara onRoundStart en todos los efectos de combatientes vivos.
     */
    public function triggerRoundStartEffects(): void
    {
        // Si el portador del gas era una invocación retirada al final de la
        // ronda anterior, las habilidades deben volver antes de actuar.
        $this->sincronizarGasReactivo();

        $this->team1->onRoundStart($this);
        $this->team2->onRoundStart($this);

        foreach ($this->turnManager->combatientesVivos() as $c) {
            $c->effects()->triggerRoundStart($c, $this);
        }

        // Trampas de equipo o efectos de inicio pueden dejar KO pendientes.
        $this->procesarDebilitamientos();
    }

    /**
     * Dispara onRoundEnd en todos los efectos de combatientes vivos
     * y aplica daño por estado (quemadura/envenenamiento).
     */
    public function triggerRoundEndEffects(): void
    {
        $this->sincronizarGasReactivo();

        foreach ($this->turnManager->combatientesVivos() as $c) {
            $c->effects()->triggerRoundEnd($c, $this);

            if (! $this->equipoProtegido($c)) {
                $this->procesarCondicionesVolatiles($c);

                // Daño por estado al final de la ronda
                $dañoStatus = $c->aplicarDañoStatus();
                if ($dañoStatus > 0) {
                    $label = $c->estado()->label();
                    $this->agregarLog("{$c->nombre()} sufre {$dañoStatus} de daño por {$label}");
                    if (! $c->estaVivo()) {
                        $this->agregarLog("¡{$c->nombre()} se ha debilitado por {$label}!");
                    }
                }

                // Daño por clima (granizo / tormenta arena): golpea primero la barrera física
                $dañoClima = $this->getCalculadorClima()->calcular($c, $this->weather);
                if ($dañoClima > 0) {
                    $c->recibirDaño($dañoClima, false);
                    $climaLabel = $this->weather->label();
                    $this->agregarLog("{$c->nombre()} sufre {$dañoClima} de daño por {$climaLabel}");
                    if (! $c->estaVivo()) {
                        $this->agregarLog("¡{$c->nombre()} se ha debilitado por {$climaLabel}!");
                    }
                }
            }

            // Los multiplicadores temporales deben avanzar aunque Muro Mágico
            // bloquee el daño indirecto.
            $c->avanzarMultiplicadoresTemporales();
        }

        $this->team1->onRoundEnd($this);
        $this->team2->onRoundEnd($this);
        $this->procesarDanioDiferido();
        $this->procesarDebilitamientos();

        // Cierre final: Comealmas y efectos similares observan todas las
        // muertes de la ronda, incluidas las de estado/clima/Detonación.
        foreach ($this->turnManager->combatientesVivos() as $c) {
            $c->effects()->triggerRoundEndFinal($c, $this);
        }

        $this->retirarInvocacionesExpiradas();

        if ($this->trickRoomTurnos > 0) {
            $this->setTrickRoom($this->trickRoomTurnos - 1);
        }

        if ($this->turnosTerreno > 0) {
            $this->turnosTerreno--;
            if ($this->turnosTerreno === 0) {
                $this->terreno = TipoTerreno::NONE;
            }
        }
    }

    /**
     * Procesa las muertes pendientes y dispara sus efectos una sola vez.
     * Repite hasta estabilizarse por si Detonación u otro efecto mata a más
     * combatientes durante la notificación.
     */
    public function procesarDebilitamientos(): int
    {
        $total = 0;

        do {
            $nuevos = 0;
            foreach ($this->turnManager->allCombatants() as $combatiente) {
                if ($combatiente->notificarDebilitamiento($this)) {
                    $nuevos++;
                    $total++;
                }
            }
        } while ($nuevos > 0);

        return $total;
    }

    /**
     * Descuenta la duración de las invocaciones y retira las caducadas o
     * debilitadas. Se ejecuta al final de la ronda, después de los efectos
     * de equipo, para que una invocación recién creada no caduque en el
     * mismo turno.
     */
    private function retirarInvocacionesExpiradas(): void
    {
        foreach ([$this->team1, $this->team2] as $equipo) {
            foreach ($equipo->combatientesCollection()->all() as $combatiente) {
                if (! $combatiente->esInvocacion()) {
                    continue;
                }

                $estabaVivo = $combatiente->estaVivo();
                $sigueActiva = $combatiente->avanzarTurnoInvocacion();
                if ($sigueActiva && $combatiente->estaVivo()) {
                    continue;
                }

                $equipo->quitarCombatiente($combatiente);
                $equipo->normalizarCubridores();
                if ($estabaVivo) {
                    $this->agregarLog("{$combatiente->nombre()} se desvanece");
                }
            }
        }
    }

    private function procesarCondicionesVolatiles(Combatiente $portador): void
    {
        foreach ($portador->condiciones()->all() as $condicion) {
            switch ($condicion->clave) {
                case ClaveCondicion::DRENADORAS:
                    $dano = $portador->pokemon()->battleStats()->hp
                        * ($condicion->fraccionDano > 0.0 ? $condicion->fraccionDano : 0.125);
                    $resultado = $portador->recibirDaño($dano, true, 1.0);
                    $origen = $this->buscarCombatientePorId($condicion->origenId);
                    if ($origen !== null && $origen->estaVivo() && $resultado->danoHp > 0.0) {
                        $origen->curarHpCantidad($resultado->danoHp);
                    }
                    $this->agregarLog("{$portador->nombre()} pierde PS por Drenadoras");

                    break;

                case ClaveCondicion::MALDICION:
                    $dano = $portador->pokemon()->battleStats()->hp
                        * ($condicion->fraccionDano > 0.0 ? $condicion->fraccionDano : 0.0625);
                    $portador->recibirDaño($dano, true, 1.0);
                    $this->agregarLog("{$portador->nombre()} sufre la maldición");

                    break;

                case ClaveCondicion::CANTO_MORTAL:
                    $condicion->turnos--;
                    if ($condicion->turnos <= 0) {
                        $portador->setHpActual(0);
                        $this->agregarLog("¡{$portador->nombre()} cae por Canto Mortal!");
                    }

                    break;

                case ClaveCondicion::ATRAPADO:
                    $dano = $portador->pokemon()->battleStats()->hp
                        * ($condicion->fraccionDano > 0.0 ? $condicion->fraccionDano : 0.125);
                    $portador->recibirDaño($dano, ! $condicion->esFisico, 0.0);
                    $condicion->turnos--;
                    if ($condicion->turnos <= 0) {
                        $portador->quitarCondicion(ClaveCondicion::ATRAPADO);
                    }
                    $this->agregarLog("{$portador->nombre()} sufre daño por estar atrapado");

                    break;

                case ClaveCondicion::PROTEGIDO:
                    $portador->quitarCondicion(ClaveCondicion::PROTEGIDO);
                    break;

                case ClaveCondicion::AGUANTE:
                    $portador->quitarCondicion(ClaveCondicion::AGUANTE);
                    break;

                case ClaveCondicion::AQUA_RING:
                    $portador->curarConExcedente($portador->pokemon()->battleStats()->hp * 0.0625);
                    $this->agregarLog("{$portador->nombre()} recupera PS por Acua Aro");
                    break;

                case ClaveCondicion::LEVITACION:
                    $condicion->turnos--;
                    if ($condicion->turnos <= 0) {
                        $portador->quitarCondicion(ClaveCondicion::LEVITACION);
                    }
                    break;

                case ClaveCondicion::DESAFIO:
                    $condicion->turnos--;
                    if ($condicion->turnos <= 0) {
                        $portador->quitarCondicion(ClaveCondicion::DESAFIO);
                    }

                    break;

                case ClaveCondicion::SILENCIO:
                case ClaveCondicion::MARCADO:
                case ClaveCondicion::VINCULO_DANO:
                case ClaveCondicion::EMBARGO:
                case ClaveCondicion::IGNORA_HABILIDAD:
                case ClaveCondicion::VULNERABLE:
                case ClaveCondicion::MOFA:
                case ClaveCondicion::CRITICO_MEJORADO:
                    $condicion->turnos--;
                    if ($condicion->turnos <= 0) {
                        $portador->quitarCondicion($condicion->clave);
                    }

                    break;

                case ClaveCondicion::TIPO_ALTERADO:
                    if ($condicion->turnos > 0) {
                        $condicion->turnos--;
                        if ($condicion->turnos <= 0) {
                            $portador->quitarCondicion($condicion->clave);
                        }
                    }

                    break;

                case ClaveCondicion::HABILIDAD_ANULADA:
                    $condicion->turnos--;
                    if ($condicion->turnos <= 0) {
                        $portador->restaurarHabilidad();
                    }

                    break;

                case ClaveCondicion::SUSTITUTO:
                    if ($condicion->turnos > 0) {
                        $condicion->turnos--;
                        if ($condicion->turnos <= 0) {
                            $portador->quitarCondicion(ClaveCondicion::SUSTITUTO);
                        }
                    }

                    break;

                case ClaveCondicion::MARIONETA_INERTE:
                    $condicion->turnos--;
                    if ($condicion->turnos <= 0) {
                        $portador->setHpActual(0.0);
                        $this->agregarLog("¡El control de {$portador->nombre()} termina!");
                    }

                    break;

                case ClaveCondicion::INTERRUPCION:
                case ClaveCondicion::ATURDIDO:
                case ClaveCondicion::RECARGA:
                    break;

                case ClaveCondicion::FLINCH:
                    break;

                case ClaveCondicion::CONFUSION:
                    break;
            }

            if ($portador->hpActual() <= 0.0) {
                $this->agregarLog("¡{$portador->nombre()} se ha debilitado por una condición!");
                $portador->notificarDebilitamiento($this);

                break;
            }
        }
    }

    private function buscarCombatientePorId(string $id): ?Combatiente
    {
        foreach ($this->turnManager->allCombatants() as $combatiente) {
            if ($combatiente->id() === $id) {
                return $combatiente;
            }
        }

        return null;
    }

    /**
     * True si algún aliado vivo del combatiente bloquea el daño indirecto
     * (Muro Mágico).
     */
    private function equipoProtegido(Combatiente $combatiente): bool
    {
        $equipo = $this->team1->findCombatant($combatiente) !== null ? $this->team1 : $this->team2;

        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            if ($aliado->bloqueaDanioIndirecto()) {
                return true;
            }
        }

        return false;
    }

    public function ejecutarBatalla(): BattleLog
    {
        $this->agregarLog('¡Comienza la batalla!');

        while ($this->turnManager->bothTeamsAlive()) {
            $this->turnManager->startNewRound();
            $this->triggerRoundStartEffects();
            $this->agregarLog("--- Ronda {$this->turnManager->round()} ---");

            while ($this->turnManager->hayAlgunoConAccionPendiente() && $this->turnManager->bothTeamsAlive()) {
                $actor = $this->turnManager->getNextActor();

                if ($actor === null) {
                    break;
                }

                $actor->effects()->dispararInicioTurno($actor, $this);
                $puedeActuar = $actor->puedeActuar();
                if (! $puedeActuar->esPermitida()) {
                    $this->agregarLog("{$actor->nombre()} no puede actuar: {$puedeActuar->motivo()}");
                    $this->turnManager->consumeAction($actor);

                    continue;
                }
                $this->ejecutarAccion($actor);
                $actor->effects()->dispararFinTurno($actor, $this);
                $this->procesarDebilitamientos();
                $this->turnManager->consumeAction($actor);
            }

            // Efectos de fin de ronda + daño por estado
            $this->triggerRoundEndEffects();

            foreach ($this->turnManager->combatientesVivos() as $c) {
                $this->subject->notifyEndTurn($c);
            }
        }

        $winner = ! $this->team1->todosDebilitados() ? $this->team1->name : $this->team2->name;
        $this->agregarLog("¡{$winner} gana la batalla!");

        return $this->log;
    }

    private function ejecutarAccion(Combatiente $actor): void
    {
        $objetivo = $this->getSelectorAccion()->elegirObjetivoPara($this, $actor);
        if ($objetivo === null) {
            $actor->romperCombo();

            return;
        }

        $movimiento = $this->elegirMejorMovimiento($actor, $objetivo);
        if ($movimiento === null || ! $actor->movimientoDisponible($movimiento)) {
            $actor->romperCombo();

            return;
        }

        $enemigo = $actor->estaEnVanguardia() ? $this->team2 : $this->team1;
        $defenderTeamHasVanguard = $enemigo->tieneVanguardiaViva();

        $accion = new AccionBatalla(
            attacker: $actor,
            defender: $objetivo,
            move: $movimiento,
            defenderTeamHasVanguard: $defenderTeamHasVanguard,
            weather: $this->weather,
            terreno: $this->terreno,
        );

        $servicio = new ServicioEjecucionBatalla($this->damageChain);
        $resultado = $servicio->calcularYAplicarDano($accion, $this);

        $daño = $resultado->dano;
        $servicio->aplicarEstadoYStatChanges($accion, $this, $resultado->objetivos);
        $actor->consumirMovimiento($movimiento);

        // Alimentar la memoria con la acción observada
        $this->getSelectorAccion()->registrarAccionEnemiga($accion, $daño, $this->turnManager->round());

        $this->agregarLog(
            "{$actor->velocidadEfectiva()}vel [{$actor->hpActual()}hp] "
            ."ataca a [{$objetivo->hpActual()}hp] "
            ."con {$movimiento->nombre} -> {$daño} de daño"
        );

        $this->subject->notifyDamaged($objetivo, $daño);
        $objetivo->dispararDanioRecibido($daño, $this);
        $actor->dispararDanioInfligido($objetivo, $daño, $this);

        if (! $objetivo->estaVivo()) {
            $this->agregarLog("¡{$objetivo->nombre()} se ha debilitado!");
            $this->getSelectorAccion()->memoria()->registrarKO(
                $this->turnManager->round(),
                $actor->id(),
                $objetivo->id(),
            );
        }

        // Cubre objetivos secundarios, cubridores, vínculos y muertes por
        // reacciones. La notificación es idempotente por combatiente.
        $nuevosDebilitados = $this->procesarDebilitamientos();
        if ($nuevosDebilitados > 0 && $actor->estaVivo()) {
            $actor->otorgarAccionExtra();
        }

        if (! $actor->estaVivo()) {
            $this->agregarLog("¡{$actor->nombre()} se ha debilitado por el efecto!");
        }
    }

    /**
     * Elige un objetivo enemigo para el actor dado (público para IA/Livewire).
     */
    public function elegirObjetivoPara(Combatiente $actor): ?Combatiente
    {
        return $this->getSelectorAccion()->elegirAccion($this, $actor, $this->dificultad)->accion->defender;
    }

    public function elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla
    {
        return $this->getSelectorAccion()->elegirAccion($this, $attacker, $this->dificultad)->accion->move;
    }
}
