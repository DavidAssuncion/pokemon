<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Componentes\Combatiente;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\InmunidadTipo;
use Src\Battle\Domain\Enums\FlagMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\ValueObjects\AjusteGolpes;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Shared\Tipos\TipoPokemon;

trait DelegacionEfectosCombatiente
{
    public function dispararDanioInfligido(Combatiente $target, float $daño, AgregadoBatalla $battle): void
    {
        $this->effects()->dispararDanioInfligido($this, $target, $daño, $battle);
    }

    public function dispararDanioRecibido(float $daño, AgregadoBatalla $battle): void
    {
        $this->effects()->dispararDanioRecibido($this, $daño, $battle);
    }

    public function dispararDanioRecibidoCompleto(
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        $this->effects()->dispararDanioRecibidoCompleto($this, $accion, $resultado, $battle);
    }

    public function contraatacar(AccionBatalla $accion, float $danoRecibido, AgregadoBatalla $battle): void
    {
        $this->effects()->contraatacar($this, $accion, $danoRecibido, $battle);
    }

    public function dispararContacto(Combatiente $atacante, AgregadoBatalla $battle): void
    {
        $this->effects()->dispararContacto($this, $atacante, $battle);
    }

    public function dispararDebilitamiento(AgregadoBatalla $battle): void
    {
        $this->effects()->dispararDebilitamiento($this, $battle);
    }

    public function notificarDebilitamiento(AgregadoBatalla $battle): bool
    {
        if ($this->estaVivo() || $this->debilidadNotificada()) {
            return false;
        }

        $this->registro->marcarDebilidadNotificada(true);
        $this->dispararDebilitamiento($battle);
        $battle->subject()->notifyFainted($this);
        $battle->sincronizarGasReactivo();

        return true;
    }

    public function dispararEscudoRoto(
        Combatiente $atacante,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        $this->effects()->dispararEscudoRoto($this, $atacante, $resultado, $battle);
    }

    public function dispararSerObjetivo(AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        $this->effects()->dispararSerObjetivo($this, $accion, $battle);
    }

    public function ajustarGolpes(AccionBatalla $accion): ?AjusteGolpes
    {
        return $this->effects()->ajustarGolpes($accion);
    }

    public function multiplicadorDanioInfligido(AccionBatalla $accion): float
    {
        return $this->effects()->multiplicadorDanioInfligido($accion);
    }

    public function multiplicadorDanioEscudo(AccionBatalla $accion): float
    {
        return $this->effects()->multiplicadorDanioEscudo($accion);
    }

    public function multiplicadorEfectoEstado(): float
    {
        return $this->efectos->multiplicadorEfectoEstado();
    }

    public function multiplicadorDanioRecibido(AccionBatalla $accion): float
    {
        return $this->effects()->multiplicadorDanioRecibido($accion);
    }

    public function multiplicadorPrecisionAtaque(): float
    {
        return $this->effects()->multiplicadorPrecisionAtaque();
    }

    public function multiplicadorPrecisionEstado(): float
    {
        return $this->effects()->multiplicadorPrecisionEstado();
    }

    public function esInmuneAFlag(FlagMovimiento $flag): bool
    {
        return $this->effects()->inmuneAFlag($flag);
    }

    public function inmunidadTipo(TipoPokemon $tipo): ?InmunidadTipo
    {
        return $this->effects()->inmunidadTipo($tipo);
    }

    public function esInmuneACritico(): bool
    {
        return $this->effects()->esInmuneACritico();
    }

    public function multiplicadorProbabilidadCritico(): float
    {
        return $this->effects()->multiplicadorProbabilidadCritico();
    }

    public function multiplicadorStab(AccionBatalla $accion): float
    {
        return $this->effects()->multiplicadorStab($accion);
    }

    public function tipoEfectivoMovimiento(AccionBatalla $accion): TipoPokemon
    {
        return $this->effects()->tipoEfectivoMovimiento($accion) ?? $accion->move->tipo;
    }

    public function probabilidadEsquiva(): float
    {
        return $this->effects()->probabilidadEsquiva($this);
    }

    public function esInmuneARecoil(): bool
    {
        return $this->effects()->esInmuneARecoil();
    }

    public function bloqueaDanioIndirecto(): bool
    {
        return $this->effects()->bloqueaDanioIndirecto();
    }

    public function esInmuneAClima(TipoClima $clima): bool
    {
        return $this->effects()->esInmuneAClima($clima);
    }
}
