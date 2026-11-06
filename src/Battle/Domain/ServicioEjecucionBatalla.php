<?php

declare(strict_types=1);

namespace Src\Battle\Domain;

use Src\Battle\Domain\Chain\CadenaDanio;
use Src\Battle\Domain\Collections\CombatientesCollection;
use Src\Battle\Domain\Danio\AplicadorPosturaDanio;
use Src\Battle\Domain\Danio\GestorEfectosMovimiento;
use Src\Battle\Domain\Danio\ResolutorObjetivosDanio;
use Src\Battle\Domain\Effects\Movimiento\ColeccionEjecutoresMovimiento;
use Src\Battle\Domain\Ejecucion\AplicadorCosteVelocidad;
use Src\Battle\Domain\Ejecucion\AplicadorCuracion;
use Src\Battle\Domain\Ejecucion\AplicadorDrenaje;
use Src\Battle\Domain\Ejecucion\AplicadorEfectosPostImpacto;
use Src\Battle\Domain\Ejecucion\AplicadorEstadoMovimiento;
use Src\Battle\Domain\Ejecucion\AplicadorRecoil;
use Src\Battle\Domain\Ejecucion\CalculadorGolpes;
use Src\Battle\Domain\Ejecucion\EvaluadorPrecondicionesMovimiento;
use Src\Battle\Domain\Ejecucion\GeneradorLogMovimiento;
use Src\Battle\Domain\Ejecucion\ResolutorImpacto;
use Src\Battle\Domain\Ejecucion\ResolutorPrecision;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Battle\Presentation\DTOResultadoDanio;
use Src\Shared\Domain\Ports\GeneradorAleatorio;
use Src\Shared\Domain\Random\GeneradorAleatorioPredeterminado;

/**
 * Fachada del cálculo de movimientos: delega en los componentes de Ejecucion
 * y conserva el contrato público de Combate (Livewire) y AgregadoBatalla.
 */
class ServicioEjecucionBatalla
{
    private readonly GeneradorLogMovimiento $generadorLogMovimiento;
    private readonly ResolutorImpacto $resolutorImpacto;
    private readonly AplicadorEfectosPostImpacto $aplicadorEfectosPostImpacto;
    private readonly AplicadorEstadoMovimiento $aplicadorEstadoMovimiento;

    public function __construct(
        private readonly CadenaDanio $damageChain,
        ?ColeccionEjecutoresMovimiento $ejecutores = null,
        private readonly AplicadorPosturaDanio $aplicadorPostura = new AplicadorPosturaDanio(),
        private readonly GestorEfectosMovimiento $gestorEfectos = new GestorEfectosMovimiento(),
        ?GeneradorAleatorio $aleatorio = null,
    ) {
        $ejecutores ??= ColeccionEjecutoresMovimiento::conEjecutoresPorDefecto();
        $aleatorio ??= new GeneradorAleatorioPredeterminado();

        $this->aplicadorEfectosPostImpacto = new AplicadorEfectosPostImpacto(
            $ejecutores,
            new AplicadorDrenaje(),
            new AplicadorRecoil(),
            new AplicadorCuracion(),
            new AplicadorCosteVelocidad(),
            $gestorEfectos,
        );
        $this->resolutorImpacto = new ResolutorImpacto(
            $damageChain,
            $aplicadorPostura,
            new CalculadorGolpes($aleatorio),
            new ResolutorPrecision($aleatorio),
            $ejecutores,
            new EvaluadorPrecondicionesMovimiento($ejecutores),
            $this->aplicadorEfectosPostImpacto,
        );
        $this->aplicadorEstadoMovimiento = new AplicadorEstadoMovimiento($gestorEfectos);
        $this->generadorLogMovimiento = new GeneradorLogMovimiento();
    }

    public function calcularYAplicarDano(AccionBatalla $accion, ?AgregadoBatalla $battle = null): DTOResultadoDanio
    {
        if ($battle === null) {
            return $this->resolverIndividual($accion, null);
        }

        $defensores = ResolutorObjetivosDanio::resolver($accion, $battle);
        if ($defensores === null) {
            return $this->resolverIndividual($accion, $battle);
        }

        if ($defensores->isEmpty()) {
            $this->aplicadorEfectosPostImpacto->aplicarAutodestruccion($accion, $battle);

            return new DTOResultadoDanio(dano: 0.0, directPct: 0.0, resultado: ResultadoDanio::sinDano(), golpes: 0);
        }

        return $this->resolverMultiobjetivo($accion, $battle, $defensores);
    }

    private function resolverIndividual(AccionBatalla $accion, ?AgregadoBatalla $battle): DTOResultadoDanio
    {
        $resultado = $this->calcularYAplicarDanoContra($accion, $battle);
        $this->aplicadorEfectosPostImpacto->aplicarAutodestruccion($accion, $battle);

        return $resultado;
    }

    private function calcularYAplicarDanoContra(AccionBatalla $accion, ?AgregadoBatalla $battle = null, bool $aplicarEfectosGlobales = true): DTOResultadoDanio
    {
        $resultado = $this->resolutorImpacto->ejecutarContra($accion, $battle, $aplicarEfectosGlobales);

        return new DTOResultadoDanio(
            dano: $resultado['dano'],
            directPct: $resultado['directPct'],
            resultado: $resultado['resultado'],
            golpes: $resultado['golpes'],
            curado: $resultado['curado'],
            recoil: $resultado['recoil'],
            objetivos: new CombatientesCollection($resultado['objetivos']),
        );
    }

    private function resolverMultiobjetivo(AccionBatalla $accion, AgregadoBatalla $battle, CombatientesCollection $defensores): DTOResultadoDanio
    {
        $dano = 0.0;
        $danoHpTotal = 0.0;
        $golpes = 0;
        $total = new ResultadoDanio();
        $directPct = 0.0;
        /** @var list<Combatiente> $objetivos */
        $objetivos = [];

        foreach ($defensores as $defensor) {
            if (! $accion->attacker->estaVivo()) {
                break;
            }

            $resultado = $this->calcularYAplicarDanoContra($this->accionContra($accion, $defensor, $battle), $battle, false);

            if ($resultado->golpes > 0) {
                $objetivos[] = $defensor;
            }

            $dano += $resultado->dano;
            $danoHpTotal += $resultado->resultado->danoHp ?? 0.0;
            $golpes += $resultado->golpes;
            $directPct = $resultado->directPct;
            $total = $this->acumularResultado($total, $resultado->resultado);
        }

        return $this->cerrarMultiobjetivo($accion, $battle, $dano, $danoHpTotal, $golpes, $directPct, $total, new CombatientesCollection($objetivos));
    }

    private function accionContra(AccionBatalla $accion, Combatiente $defensor, AgregadoBatalla $battle): AccionBatalla
    {
        $equipoDefensor = $battle->equipoDe($defensor);

        return new AccionBatalla(
            attacker: $accion->attacker,
            defender: $defensor,
            move: $accion->move,
            defenderTeamHasVanguard: $equipoDefensor?->tieneVanguardiaViva() ?? false,
            weather: $accion->weather,
            isPreview: $accion->isPreview,
            equipoDefensor: $equipoDefensor,
            terreno: $accion->terreno,
        );
    }

    private function acumularResultado(ResultadoDanio $total, ResultadoDanio $resultado): ResultadoDanio
    {
        return new ResultadoDanio(
            danoHp: $total->danoHp + $resultado->danoHp,
            danoEscudo: $total->danoEscudo + $resultado->danoEscudo,
            excedente: $total->excedente + $resultado->excedente,
            rompioEscudo: $total->rompioEscudo || $resultado->rompioEscudo,
        );
    }

    private function cerrarMultiobjetivo(
        AccionBatalla $accion,
        AgregadoBatalla $battle,
        float $dano,
        float $danoHpTotal,
        int $golpes,
        float $directPct,
        ResultadoDanio $total,
        CombatientesCollection $objetivos,
    ): DTOResultadoDanio {
        $globales = $this->aplicadorEfectosPostImpacto->cerrarMultiobjetivo($accion, $dano, $danoHpTotal, $battle);

        return new DTOResultadoDanio(
            dano: $dano,
            directPct: $directPct,
            resultado: $total,
            golpes: $golpes,
            curado: $globales['curado'],
            recoil: $globales['recoil'],
            objetivos: $objetivos,
        );
    }

    public function aplicarEstado(Combatiente $objetivo, MovimientoBatalla $movimiento, ?AgregadoBatalla $battle = null, ?Combatiente $atacante = null): bool
    {
        return $this->aplicadorEstadoMovimiento->aplicarEstado($objetivo, $movimiento, $battle, $atacante);
    }

    public function aplicarStatChanges(Combatiente $actor, Combatiente $objetivo, MovimientoBatalla $movimiento): void
    {
        $this->aplicadorEstadoMovimiento->aplicarStatChanges($actor, $objetivo, $movimiento);
    }

    public function aplicarEstadoYStatChanges(AccionBatalla $accion, ?AgregadoBatalla $battle = null, ?CombatientesCollection $objetivos = null): void
    {
        $this->aplicadorEstadoMovimiento->aplicarEstadoYStatChanges($accion, $battle, $objetivos);
    }

    public function generarLogMovimiento(Combatiente $actor, Combatiente $objetivo, MovimientoBatalla $movimiento, float $daño, float $directPct, bool $defenderTeamHasVanguard): string
    {
        return $this->generadorLogMovimiento->generar($actor, $objetivo, $movimiento, $daño, $directPct, $defenderTeamHasVanguard);
    }
}
