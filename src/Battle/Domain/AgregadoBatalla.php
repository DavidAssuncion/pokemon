<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\AI\NivelDificultad;
use Src\Battle\Domain\AI\SelectorAccionIA;
use Src\Battle\Domain\Catalog\CatalogoEfectosAliado;
use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Componentes\Agregado\EjecutorAccion;
use Src\Battle\Domain\Componentes\Agregado\GestorClimaTerreno;
use Src\Battle\Domain\Componentes\Agregado\GestorGasReactivo;
use Src\Battle\Domain\Componentes\Agregado\GestorInvocaciones;
use Src\Battle\Domain\Componentes\Agregado\MotorRondas;
use Src\Battle\Domain\Componentes\Agregado\ProcesadorDanioDiferido;
use Src\Battle\Domain\DataTransferObjects\DTOAccionObjeto;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\Enums\TipoTerreno;
use Src\Battle\Domain\Observer\ObservadorEjecucionBatalla;
use Src\Battle\Domain\Observer\SujetoBatalla;
use Src\Battle\Domain\ValueObjects\BattleLog;
use Src\Battle\Presentation\DTOAccionBatalla;

class AgregadoBatalla
{
    private GestorTurnos $turnManager;

    private CadenaDanio $damageChain;

    private SujetoBatalla $subject;

    private BattleLog $log;

    private DTOAccionBatalla|DTOAccionObjeto|null $pendingAction = null;

    private TipoClima $weather = TipoClima::NONE;

    private TipoTerreno $terreno = TipoTerreno::NONE;

    private int $turnosTerreno = 0;

    /** @var list<array<string, mixed>> */
    private array $danioDiferido = [];

    /**
     * Detonaciones ocurridas desde la última lectura. La capa de presentación
     * las consume para reproducir la explosión; no participan en el cálculo.
     *
     * @var list<array{portadorId: string, objetivos: list<array{id: string, dano: float}>}>
     */
    private array $detonaciones = [];

    /**
     * Cambios de vitales producidos por efectos residuales (clima, estado,
     * condiciones, trampas, habilidades). La presentación los reproduce en
     * orden, uno detrás de otro.
     *
     * @var list<array{objetivoId: string, hpAntes: float, hpDespues: float, defAntes: float, defDespues: float, spDefAntes: float, spDefDespues: float, tipo: string, label: string, movimientoSlug: string, origenId: string}>
     */
    private array $residuos = [];

    private ?CalculadorDañoClima $calculadorClima = null;

    private ?SelectorAccionIA $selectorAccion = null;

    private NivelDificultad $dificultad = NivelDificultad::PERFECTA;

    private int $rondaPreparada = 0;

    private int $trickRoomTurnos = 0;

    /**
     * Id del combatiente cuyo turno está activo. Se fija en servidor al avanzar
     * el turno y permite autorizar acciones sin confiar en propiedades
     * públicas de Livewire. El default cubre payloads de sesión anteriores.
     */
    private ?string $actorActualId = null;

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

    public function pendingAction(): DTOAccionBatalla|DTOAccionObjeto|null
    {
        return $this->pendingAction;
    }

    public function setPendingAction(DTOAccionBatalla|DTOAccionObjeto|null $pendingAction): void
    {
        $this->pendingAction = $pendingAction;
    }

    public function actorActualId(): ?string
    {
        return $this->actorActualId;
    }

    public function setActorActualId(?string $id): void
    {
        $this->actorActualId = $id;
    }

    public function weather(): TipoClima
    {
        return $this->weather;
    }

    public function setWeather(TipoClima $weather): void
    {
        $this->weather = $weather;
        (new GestorClimaTerreno())->aplicarClima($this->turnManager, $weather);
    }

    public function terreno(): TipoTerreno
    {
        return $this->terreno;
    }

    public function setTerreno(TipoTerreno $terreno, int $turnos = 5): void
    {
        $this->terreno = $terreno;
        $this->turnosTerreno = (new GestorClimaTerreno())->normalizarTurnosTerreno($terreno, $turnos);
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
        $this->danioDiferido = (new ProcesadorDanioDiferido())->procesar($this, $this->danioDiferido);
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
        $this->gasReactivoId = (new GestorGasReactivo())->activar($this, $portador, $this->gasReactivoId);
    }

    /**
     * Restaura las habilidades si el portador del gas ya no está vivo.
     */
    public function sincronizarGasReactivo(): void
    {
        $this->gasReactivoId = (new GestorGasReactivo())->sincronizar($this, $this->gasReactivoId);
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
        $this->trickRoomTurnos = (new GestorClimaTerreno())->fijarTrickRoom($this->turnManager, $turnos);
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
        $gestor = new GestorInvocaciones();
        $equipo = $gestor->equipoDisponible($this, $invocador, $maxInvocaciones);
        if ($equipo === null) {
            return null;
        }

        $invocacion = clone $invocador;
        $numero = ++$this->contadorInvocaciones;

        return $gestor->preparar(
            $this,
            $invocacion,
            $invocador,
            $equipo,
            $turnos,
            $fraccionHp,
            $nombre,
            $numero,
        );
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

    /**
     * Rivales vivos a los que el actor puede dirigir un ataque según la regla
     * de fila: vanguardia → vanguardia rival mientras quede viva; retaguardia
     * o vanguardia rival caída → cualquier rival vivo.
     */
    public function objetivosRivalesLegalesPara(Combatiente $actor): CombatientesCollection
    {
        $equipoRival = $this->equipoRivalDe($actor);
        if ($equipoRival === null) {
            return new CombatientesCollection();
        }

        $rivales = $equipoRival->combatientesCollection()->vivos();

        if (! $actor->estaEnVanguardia() || ! $equipoRival->tieneVanguardiaViva()) {
            return $rivales;
        }

        $vanguardiaRival = $rivales->filter(
            fn (Combatiente $rival): bool => $rival->estaEnVanguardia()
        );

        return $vanguardiaRival->isEmpty() ? $rivales : $vanguardiaRival;
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
        (new MotorRondas())->triggerBattleStartEffects($this);
    }

    /**
     * Dispara onRoundStart en todos los efectos de combatientes vivos.
     */
    public function triggerRoundStartEffects(): void
    {
        (new MotorRondas())->triggerRoundStartEffects($this);
    }

    /**
     * Dispara onRoundEnd en todos los efectos de combatientes vivos
     * y aplica daño por estado (quemadura/envenenamiento).
     */
    public function triggerRoundEndEffects(): void
    {
        (new MotorRondas())->triggerRoundEndEffects($this, $this->getCalculadorClima(...));
    }

    /**
     * Procesa las muertes pendientes y dispara sus efectos una sola vez.
     * Repite hasta estabilizarse por si Detonación u otro efecto mata a más
     * combatientes durante la notificación.
     */
    public function procesarDebilitamientos(): int
    {
        return (new MotorRondas())->procesarDebilitamientos($this);
    }

    /**
     * Registra una detonación para que la presentación pueda animarla.
     *
     * @param  list<array{id: string, dano: float}>  $objetivos
     */
    public function registrarDetonacion(string $portadorId, array $objetivos): void
    {
        if ($objetivos === []) {
            return;
        }

        $this->detonaciones[] = [
            'portadorId' => $portadorId,
            'objetivos' => $objetivos,
        ];
    }

    /**
     * Devuelve y vacía las detonaciones pendientes.
     *
     * @return list<array{portadorId: string, objetivos: list<array{id: string, dano: float}>}>
     */
    public function consumirDetonaciones(): array
    {
        $detonaciones = $this->detonaciones;
        $this->detonaciones = [];

        return $detonaciones;
    }

    /**
     * Ejecuta una acción residual midiendo el cambio de vitales del objetivo.
     */
    public function medirResiduo(
        Combatiente $objetivo,
        string $tipo,
        string $label,
        callable $accion,
        ?string $movimientoSlug = null,
        ?string $origenId = null,
    ): void {
        $hpAntes = $objetivo->hpActual();
        $defAntes = $objetivo->defensaHpActual();
        $spDefAntes = $objetivo->defensaEspHpActual();

        $accion();

        $hpDespues = $objetivo->hpActual();
        $defDespues = $objetivo->defensaHpActual();
        $spDefDespues = $objetivo->defensaEspHpActual();

        if (abs($hpDespues - $hpAntes) < 0.001
            && abs($defDespues - $defAntes) < 0.001
            && abs($spDefDespues - $spDefAntes) < 0.001) {
            return;
        }

        $this->residuos[] = [
            'objetivoId' => $objetivo->id(),
            'hpAntes' => $hpAntes,
            'hpDespues' => $hpDespues,
            'defAntes' => $defAntes,
            'defDespues' => $defDespues,
            'spDefAntes' => $spDefAntes,
            'spDefDespues' => $spDefDespues,
            'tipo' => $tipo,
            'label' => $label,
            'movimientoSlug' => $movimientoSlug ?? '',
            'origenId' => $origenId ?? '',
        ];
    }

    /**
     * Devuelve y vacía los cambios residuales pendientes.
     *
     * @return list<array{objetivoId: string, hpAntes: float, hpDespues: float, defAntes: float, defDespues: float, spDefAntes: float, spDefDespues: float, tipo: string, label: string, movimientoSlug: string, origenId: string}>
     */
    public function consumirResiduos(): array
    {
        $residuos = $this->residuos;
        $this->residuos = [];

        return $residuos;
    }

    /**
     * Ejecuta la batalla hasta que un equipo quede sin combatientes vivos.
     *
     * @param  int|null  $maxRondas  Tope opcional de rondas. Sin valor conserva
     *                               el comportamiento histórico (sin tope).
     * @param  ObservadorEjecucionBatalla|null  $observador  Observador opcional
     *                               de ejecución. Se pasa por parámetro y nunca
     *                               se guarda como propiedad del agregado.
     *
     * @throws \InvalidArgumentException si `$maxRondas` es menor que 1.
     * @throws \Src\Battle\Domain\Exceptions\LimiteRondasAlcanzado si se alcanza el tope antes de terminar.
     */
    public function ejecutarBatalla(
        ?int $maxRondas = null,
        ?ObservadorEjecucionBatalla $observador = null,
    ): BattleLog {
        return (new MotorRondas())->ejecutarBatalla(
            $this,
            function (Combatiente $actor): void {
                $this->ejecutarAccion($actor);
            },
            $maxRondas,
            $observador,
        );
    }

    private function ejecutarAccion(Combatiente $actor): void
    {
        (new EjecutorAccion())->ejecutar(
            $this,
            $actor,
            $this->getSelectorAccion(),
            $this->dificultad,
        );
    }

    /**
     * Indica si el actor tiene al menos un aliado vivo alcanzable y un
     * movimiento disponible que el catálogo de apoyo admita.
     *
     * Es la guarda que decide si la presentación puede usar una única
     * decisión de IA (objetivo + movimiento coherentes) o conservar el
     * camino legacy de objetivo/movimiento separados.
     */
    public function actorAdmiteSoporte(Combatiente $actor): bool
    {
        $equipo = $this->equipoDe($actor);
        if ($equipo === null) {
            return false;
        }

        $aliados = $equipo->combatientesCollection()->vivos()->filter(
            fn (Combatiente $combatiente) => $combatiente->id() !== $actor->id()
        );

        if ($aliados->isEmpty()) {
            return false;
        }

        if ($equipo->tieneVanguardiaViva()) {
            $aliados = $aliados->filter(
                fn (Combatiente $combatiente) => ! $combatiente->estaEnRetaguardia()
            );

            if ($aliados->isEmpty()) {
                return false;
            }
        }

        foreach ($actor->pokemon()->moves() as $movimiento) {
            if ($movimiento instanceof MovimientoBatalla
                && $actor->movimientoDisponible($movimiento)
                && CatalogoEfectosAliado::admite($movimiento->slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decisión completa de la IA (atacante + movimiento + objetivo).
     */
    public function elegirAccionPara(Combatiente $actor): ?AccionBatalla
    {
        return $this->getSelectorAccion()
            ->elegirAccion($this, $actor, $this->dificultad)
            ->accion;
    }

    /**
     * Elige un objetivo enemigo para el actor dado (público para IA/Livewire).
     */
    public function elegirObjetivoPara(Combatiente $actor): ?Combatiente
    {
        return $this->getSelectorAccion()
            ->elegirAccion($this, $actor, $this->dificultad, incluirSoporte: false)
            ->accion->defender;
    }

    public function elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla
    {
        return $this->getSelectorAccion()
            ->elegirAccion($this, $attacker, $this->dificultad, incluirSoporte: false)
            ->accion->move;
    }
}
