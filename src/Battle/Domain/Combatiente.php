<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Collections\CondicionesVolatilesCollection;
use Src\Battle\Domain\Componentes\Combatiente\CondicionesCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\DelegacionEfectosCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\DelegacionIdentidadCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\DelegacionObjetoCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\DelegacionTurnoCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\EfectosCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\EstadosCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\HabilidadCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\IdentidadCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\InvocacionCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\JefeCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\MovimientosCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\ObjetoCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\RecursosCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\RegistroDanio;
use Src\Battle\Domain\Componentes\Combatiente\SerializacionCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\StatsCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\TurnoCombatiente;
use Src\Battle\Domain\Componentes\Combatiente\VitalidadCombatiente;
use Src\Battle\Domain\Effects\ColeccionEfectos;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;
use Src\Battle\Domain\ValueObjects\JefeEstado;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;
use Src\Battle\Domain\ValueObjects\ResistenciasTemporales;
use Src\Battle\Domain\ValueObjects\ResultadoAccion;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Battle\Domain\ValueObjects\ResultadoSustituto;
use Src\Pokemon\Domain\PokemonEntity;
use Src\Shared\Domain\Sendas\MapaSendaJugador;
use Src\Shared\Domain\ValueObjects\MapaConteo;
use Src\Shared\Tipos\TipoPokemon;
use Src\Shared\Tipos\TiposCollection;

class Combatiente
{
    use SerializacionCombatiente;
    use DelegacionIdentidadCombatiente;
    use DelegacionObjetoCombatiente;
    use DelegacionTurnoCombatiente;
    use DelegacionEfectosCombatiente;

    private PokemonEntity $pokemon;
    private MapaSendaJugador $senda;
    private TipoClima $clima = TipoClima::NONE;
    private float $bonificadorDanio = 1.0;
    private IdentidadCombatiente $identidad;
    private VitalidadCombatiente $vitalidad;
    private EstadosCombatiente $estados;
    private CondicionesCombatiente $condiciones;
    private TurnoCombatiente $turno;
    private MovimientosCombatiente $movimientos;
    private ObjetoCombatiente $objeto;
    private HabilidadCombatiente $habilidad;
    private EfectosCombatiente $efectos;
    private StatsCombatiente $stats;
    private RegistroDanio $registro;
    private RecursosCombatiente $recursos;
    private InvocacionCombatiente $invocacion;
    private JefeCombatiente $jefe;

    public function __construct(PokemonEntity $pokemon, Posicion $posicion)
    {
        $this->pokemon = $pokemon;
        $this->senda = MapaSendaJugador::vacio();
        $this->identidad = new IdentidadCombatiente(posicion: $posicion);
        $this->vitalidad = VitalidadCombatiente::desdePokemon($pokemon);
        $this->estados = new EstadosCombatiente();
        $this->condiciones = new CondicionesCombatiente();
        $this->turno = new TurnoCombatiente();
        $this->movimientos = new MovimientosCombatiente();
        $this->objeto = new ObjetoCombatiente();
        $this->habilidad = new HabilidadCombatiente();
        $this->efectos = new EfectosCombatiente();
        $this->stats = StatsCombatiente::vacia();
        $this->registro = new RegistroDanio();
        $this->recursos = new RecursosCombatiente();
        $this->invocacion = new InvocacionCombatiente();
        $this->jefe = new JefeCombatiente();
    }

    public function hpActual(): float
    {
        return $this->vitalidad->hpActual();
    }

    public function defensaHpActual(): float
    {
        return $this->vitalidad->defensaHpActual();
    }

    public function defensaEspHpActual(): float
    {
        return $this->vitalidad->defensaEspHpActual();
    }

    public function estado(): EstadoPokemon
    {
        return $this->condiciones->tiene(ClaveCondicion::CONFUSION)
            ? EstadoPokemon::CONFUSION
            : $this->estados->estadoMayor();
    }

    public function estadoMayor(): EstadoPokemon
    {
        return $this->estados->estadoMayor();
    }

    public function turnosEstadoMayor(): int
    {
        return $this->estados->turnosEstadoMayor();
    }

    public function contadorVenenoGrave(): int
    {
        return $this->estados->contadorVenenoGrave();
    }

    public function cargasVeneno(): int
    {
        return $this->estados->cargasVeneno();
    }

    public function aplicarCargaVeneno(int $cargas = 1): void
    {
        $this->estados->aplicarCargaVeneno($cargas);
    }

    public function combo(): int
    {
        return $this->recursos->combo();
    }

    public function comboMovimiento(): string
    {
        return $this->recursos->comboMovimiento();
    }

    public function comboMultiplicador(): float
    {
        return $this->recursos->comboMultiplicador();
    }

    public function comboMultiplicadorPara(MovimientoBatalla $movimiento): float
    {
        return $this->recursos->comboMultiplicadorPara($movimiento->nombre, $movimiento->perfil->potenciaCreciente);
    }

    public function prepararCombo(MovimientoBatalla $movimiento): void
    {
        $this->recursos->prepararCombo($movimiento->nombre, $movimiento->perfil->potenciaCreciente);
    }

    public function registrarGolpeCombo(MovimientoBatalla $movimiento): void
    {
        $this->recursos->registrarGolpe($movimiento->nombre, $movimiento->perfil->potenciaCreciente);
    }

    public function romperCombo(): void
    {
        $this->recursos->romperCombo();
    }

    public function esInvocacion(): bool
    {
        return $this->invocacion->esInvocacion();
    }

    public function invocadoPorId(): string
    {
        return $this->invocacion->invocadoPorId();
    }

    public function turnosInvocacion(): int
    {
        return $this->invocacion->turnosInvocacion();
    }

    public function avanzarTurnoInvocacion(): bool
    {
        return $this->invocacion->avanzarTurnoInvocacion();
    }

    public function prepararComoInvocacion(
        string $id,
        string $nombre,
        string $invocadoPorId,
        int $turnos,
        float $fraccionHp,
    ): void {
        $this->invocacion->prepararComoInvocacion(
            $id,
            $nombre,
            $invocadoPorId,
            $turnos,
            $fraccionHp,
            max(1.0, $this->pokemon->battleStats()->hp),
            $this->identidad,
            $this->vitalidad,
            $this->estados,
            $this->condiciones,
            $this->efectos,
            $this->stats,
            $this->recursos,
            $this->movimientos,
            $this->turno,
            $this->registro,
            $this->objeto,
            $this->habilidad,
            $this->jefe,
        );
        $this->bonificadorDanio = 1.0;
    }

    public function revivir(float $fraccion): bool
    {
        if ($this->estaVivo()) {
            return false;
        }

        $this->vitalidad->revivir($fraccion, max(1.0, $this->pokemon->battleStats()->hp));
        $this->estados->reiniciar();
        $this->condiciones->reiniciar();
        $this->stats->reiniciar();
        $this->recursos->reiniciar();
        $this->turno->reiniciarParaRevivir();
        $this->registro->marcarDebilidadNotificada(false);

        return true;
    }

    public function avanzarCargasMovimiento(): void
    {
        $this->movimientos->avanzarCargas($this->pokemon->moves()->all());
    }

    public function cargaMovimiento(MovimientoBatalla $movimiento): int
    {
        return $this->movimientos->carga($movimiento);
    }

    public function usosRestantes(MovimientoBatalla $movimiento): int
    {
        return $this->movimientos->usosRestantes($movimiento);
    }

    public function movimientoDisponible(MovimientoBatalla $movimiento): bool
    {
        return $this->motivoMovimientoNoDisponible($movimiento) === null;
    }

    public function motivoMovimientoNoDisponible(MovimientoBatalla $movimiento): ?string
    {
        return $this->movimientos->motivoMovimientoNoDisponible(
            $movimiento,
            $this->tieneCondicion(ClaveCondicion::SILENCIO) || $this->tieneCondicion(ClaveCondicion::MOFA),
            $this->itemActivo(),
            $this->item(),
            $this->movimientoBloqueado(),
        );
    }

    public function consumirMovimiento(MovimientoBatalla $movimiento): void
    {
        $this->movimientos->consumir(
            $movimiento,
            $this->itemActivo(),
            $this->item(),
            $this->objeto,
            $this->condiciones,
            $this->recursos,
        );
    }

    public function cancelarCargasMovimiento(): void
    {
        $this->movimientos->cancelarCargas();
    }

    public function cargasMovimientoPorNombre(): MapaConteo
    {
        return $this->movimientos->cargas();
    }

    public function usosMovimientoPorNombre(): MapaConteo
    {
        return $this->movimientos->usos();
    }

    public function turnosEstado(): int
    {
        return $this->estados->turnosEstado($this->condiciones);
    }

    public function condiciones(): CondicionesVolatilesCollection
    {
        return $this->condiciones->coleccion();
    }

    public function tieneCondicion(ClaveCondicion $clave): bool
    {
        return $this->condiciones->tiene($clave);
    }

    public function condicion(ClaveCondicion $clave): ?CondicionVolatil
    {
        return $this->condiciones->buscar($clave);
    }

    public function agregarCondicion(CondicionVolatil $condicion): void
    {
        $this->condiciones->agregar($condicion);
    }

    public function reemplazarCondicion(CondicionVolatil $condicion): void
    {
        $this->condiciones->reemplazar($condicion);
    }

    public function quitarCondicion(ClaveCondicion $clave): void
    {
        $this->condiciones->quitar($clave);
    }

    public function aplicarSilencio(int $turnos = 2): void
    {
        $this->condiciones->aplicarSilencio($turnos);
    }

    public function aplicarInterrupcion(): void
    {
        $this->condiciones->aplicarInterrupcion($this->turno, $this->movimientos, $this->recursos);
    }

    public function aturdir(): void
    {
        $this->condiciones->aturdir($this->turno, $this->recursos);
    }

    public function multiplicadores(): MultiplicadoresStats
    {
        return $this->stats->multiplicadores();
    }

    public function habilidadAnulada(): bool
    {
        return $this->habilidad->anulada() || $this->tieneCondicion(ClaveCondicion::HABILIDAD_ANULADA);
    }

    public function habilidadAnuladaPorGas(): bool
    {
        return $this->habilidad->anuladaPorGas();
    }

    public function anularHabilidad(int $turnos = 5, bool $porGas = false): void
    {
        $this->habilidad->anular($turnos, $porGas, $this->efectos, $this->condiciones);
    }

    public function restaurarHabilidad(): void
    {
        $this->habilidad->restaurar($this->efectos, $this->condiciones);
    }

    public function efectosHabilidad(): ColeccionEfectos
    {
        return $this->habilidad->efectosHabilidad($this->efectos);
    }

    public function copiarHabilidadDe(Combatiente $otro): void
    {
        $this->habilidad->copiarDe($otro->habilidad, $this->efectos, $this->condiciones, $otro->efectos);
    }

    public function intercambiarHabilidadesCon(Combatiente $otro): void
    {
        $this->habilidad->intercambiarCon(
            $otro->habilidad,
            $this->efectos,
            $this->condiciones,
            $otro->efectos,
            $otro->condiciones,
        );
    }

    public function ignorarHabilidadRival(int $turnos = 5): void
    {
        $this->condiciones->reemplazar(new CondicionVolatil(
            clave: ClaveCondicion::IGNORA_HABILIDAD,
            turnos: max(1, $turnos),
        ));
    }

    public function ignoraHabilidadRival(): bool
    {
        return $this->efectos->ignoraHabilidadRival($this->condiciones);
    }

    public function aplicarTipoOverride(TipoPokemon $tipo, int $turnos = 0): void
    {
        $this->condiciones->aplicarTipoOverride($tipo, $turnos);
    }

    public function tiposDefensivos(): TiposCollection
    {
        return $this->condiciones->tiposDefensivos($this->pokemon->tiposCollection());
    }

    public function efectividadContra(TipoPokemon $tipo): float
    {
        return $this->condiciones->efectividadContra($tipo, $this->pokemon->tiposCollection());
    }

    public function aplicarSustituto(float $fraccion = 0.25, int $turnos = 0, bool $pagarCoste = true): float
    {
        return $this->condiciones->aplicarSustituto(
            $fraccion,
            $turnos,
            $pagarCoste,
            max(1.0, $this->pokemon->battleStats()->hp),
            $this->vitalidad,
        );
    }

    public function absorberConSustituto(float $dano): ResultadoSustituto
    {
        return $this->condiciones->absorberConSustituto($dano);
    }

    /**
     * @param  list<array<string, mixed>>  $fases
     * @param  list<array<string, mixed>>  $partes
     */
    public function configurarJefe(array $fases, array $partes): void
    {
        $this->jefe->configurar($fases, $partes, max(1.0, $this->pokemon->battleStats()->hp));
    }

    public function aplicarBonusInicialStats(float $porcentaje): void
    {
        $this->stats->aplicarBonusInicialStats($porcentaje);
    }

    public function esJefe(): bool
    {
        return $this->jefe->esJefe();
    }

    public function jefe(): ?JefeEstado
    {
        return $this->jefe->jefe();
    }

    public function procesarDanoJefe(float $dano, AgregadoBatalla $battle): void
    {
        $this->jefe->procesarDanio(
            $dano,
            $this->hpActual(),
            $this->pokemon->battleStats()->hp,
            $this->nombre(),
            fn (string $mensaje) => $battle->agregarLog($mensaje),
            $this->condiciones,
        );
    }

    public function multiplicadorDanioJefe(): float
    {
        return $this->jefe->multiplicadorDanioInfligido();
    }

    public function multiplicadorVelocidadJefe(): float
    {
        return $this->jefe->multiplicadorVelocidad();
    }

    public function bonificadorDanio(): float
    {
        return $this->bonificadorDanio;
    }

    public function clima(): TipoClima
    {
        return $this->clima;
    }

    public function ultimoDanoRecibido(): float
    {
        return $this->registro->ultimoDanoRecibido();
    }

    public function ultimaRondaProteccion(): int
    {
        return $this->registro->ultimaRondaProteccion();
    }

    public function setUltimaRondaProteccion(int $ronda): void
    {
        $this->registro->setUltimaRondaProteccion($ronda);
    }

    public function ultimoAtacanteId(): string
    {
        return $this->registro->ultimoAtacanteId();
    }

    public function registrarDanoRecibido(float $dano, string $atacanteId): void
    {
        $this->registro->registrarDanoRecibido($dano, $atacanteId);
    }

    public function danoRecibidoEstaRonda(): float
    {
        return $this->registro->danoRecibidoEstaRonda();
    }

    public function golpesRecibidos(): int
    {
        return $this->registro->golpesRecibidos();
    }

    public function bonificacionesPositivas(): int
    {
        return $this->stats->bonificacionesPositivas();
    }

    public function peso(): float
    {
        $factor = $this->tieneEfecto('metal_pesado') ? 2.0 : ($this->tieneEfecto('metal_liviano') ? 0.5 : 1.0);

        return max(0.1, $this->pokemon->peso() * $factor);
    }

    public function setClima(TipoClima $clima): void
    {
        $this->clima = $clima;
    }

    public function effects(): ColeccionEfectos
    {
        return $this->efectos->coleccion();
    }

    public function pokemon(): PokemonEntity
    {
        return $this->pokemon;
    }

    public function setSenda(MapaSendaJugador $senda): void
    {
        $this->senda = $senda;
    }

    public function senda(): MapaSendaJugador
    {
        return $this->senda;
    }

    public function setHpActual(float $hpActual): void
    {
        $this->vitalidad->setHpActual($hpActual);
    }

    public function setDefensaHpActual(float $defensaHpActual): void
    {
        $this->vitalidad->setDefensaHpActual($defensaHpActual);
    }

    public function setDefensaEspHpActual(float $defensaEspHpActual): void
    {
        $this->vitalidad->setDefensaEspHpActual($defensaEspHpActual);
    }

    public function setVecesActuadoEstaRonda(int $vecesActuadoEstaRonda): void
    {
        if ($vecesActuadoEstaRonda === 0) {
            $this->registro->reiniciarDanioRonda();
        }

        $this->turno->setVecesActuado($vecesActuadoEstaRonda);
    }

    public function setEstado(EstadoPokemon $estado): void
    {
        $this->estados->setEstado($estado, $this->condiciones);
    }

    public function setContadorVenenoGrave(int $contadorVenenoGrave): void
    {
        $this->estados->setContadorVenenoGrave($contadorVenenoGrave);
    }

    public function setTurnosEstado(int $turnosEstado): void
    {
        $this->estados->setTurnosEstado($turnosEstado, $this->condiciones);
    }

    public function curarEstadoMayor(): void
    {
        $this->setEstado(EstadoPokemon::NONE);
        $this->setTurnosEstado(0);
        $this->setContadorVenenoGrave(0);
    }

    public function setMultiplicadores(MultiplicadoresStats $multiplicadores): void
    {
        $this->stats->setMultiplicadores($multiplicadores);
    }

    public function avanzarMultiplicadoresTemporales(): void
    {
        $this->stats->avanzarTemporales();
    }

    public function restaurarUsos(): void
    {
        $this->movimientos->reiniciar();
    }

    public function aplicarResistenciaTemporal(TipoPokemon $tipo, float $factor): void
    {
        $this->stats->aplicarResistenciaTemporal($tipo, $factor);
    }

    public function multiplicadorResistenciaTemporal(AccionBatalla $accion): float
    {
        return $this->stats->multiplicadorResistenciaTemporal($accion);
    }

    public function resistenciasTemporales(): ResistenciasTemporales
    {
        return $this->stats->resistenciasTemporales();
    }

    public function setItem(string $item): void
    {
        $this->objeto->asignar($item);
    }

    public function setBonificadorDanio(float $bonificador): void
    {
        $this->bonificadorDanio = $bonificador;
    }

    /**
     * @return array<string, mixed>
     */
    public function aArrayVista(int $teamIdx): array
    {
        return $this->toView($teamIdx);
    }

    /**
     * Contrato de vista del combatiente (alias canónico de aArrayVista).
     *
     * @return array<string, mixed>
     */
    public function toView(int $teamIdx): array
    {
        return CombatienteVista::para($this, $teamIdx);
    }

    public function debilidadNotificada(): bool
    {
        return $this->registro->debilidadNotificada();
    }

    public function estaVivo(): bool
    {
        return $this->vitalidad->estaVivo();
    }

    public function obtenerStatEfectivo(StatClave $stat): float
    {
        return $this->stats->obtenerStatEfectivo(
            $stat,
            $this->pokemon,
            $this->estados->estadoMayor(),
            $this->efectos->coleccion()->multiplicadorEstadistica($this, $stat),
        );
    }

    public function velocidadEfectiva(): float
    {
        return $this->stats->velocidadEfectiva(
            $this->obtenerStatEfectivo(StatClave::VELOCIDAD),
            $this->turno,
            $this->multiplicadorVelocidadJefe(),
        );
    }

    public function puedeActuar(): ResultadoAccion
    {
        return $this->estados->puedeActuar(
            $this->condiciones,
            $this->vitalidad,
            $this->movimientos,
            $this->recursos,
            fn (StatClave $stat): float => $this->obtenerStatEfectivo($stat),
        );
    }

    public function tieneEfecto(string $clave): bool
    {
        return $this->efectos->tieneEfecto($clave);
    }

    public function obtenerPorcentajeDanioDirecto(): float
    {
        return $this->efectos->obtenerPorcentajeDanioDirecto();
    }

    public function recibirDaño(
        float $daño,
        bool $isSpecial,
        float $directPct = 0.0,
        float $multiplicadorEscudo = 1.0,
        float $multiplicadorSalud = 1.0,
    ): ResultadoDanio {
        return $this->vitalidad->recibirDano(
            $daño,
            $isSpecial,
            $directPct,
            $multiplicadorEscudo,
            $multiplicadorSalud,
            $this->tieneEfecto('divide_dolor'),
        );
    }

    public function curarHp(float $porcentaje): void
    {
        $this->vitalidad->curarHp($porcentaje, $this->pokemon->battleStats()->hp);
    }

    public function curarHpCantidad(float $cantidad): float
    {
        return $this->vitalidad->curarHpCantidad($cantidad, $this->pokemon->battleStats()->hp);
    }

    public function curarConExcedente(float $cantidad, bool $excedenteAEscudoEspecial = true): float
    {
        return $this->vitalidad->curarConExcedente(
            $cantidad,
            $this->pokemon->battleStats()->hp,
            $this->pokemon->battleStats()->defenseHp,
            $this->pokemon->battleStats()->spDefenseHp,
            $excedenteAEscudoEspecial,
        );
    }

    public function daniarBarreras(float $cantidad, bool $especial = true): float
    {
        return $this->vitalidad->daniarBarreras($cantidad, $especial);
    }

    public function aplicarDañoStatus(): float
    {
        return $this->estados->aplicarDanoStatus(
            $this->pokemon->battleStats()->hp,
            $this->vitalidad,
            $this->tieneEfecto('divide_dolor'),
        );
    }

    public function curarBarreras(float $porcentaje): void
    {
        $this->vitalidad->curarBarreras(
            $porcentaje,
            $this->pokemon->battleStats()->defenseHp,
            $this->pokemon->battleStats()->spDefenseHp,
        );
    }

}
