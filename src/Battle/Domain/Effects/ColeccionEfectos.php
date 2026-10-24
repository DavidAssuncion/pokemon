<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\AjustaCritico;
use Src\Battle\Domain\Effects\Capacidades\AjustaGolpes;
use Src\Battle\Domain\Effects\Capacidades\BonusStab;
use Src\Battle\Domain\Effects\Capacidades\InmuneAClima;
use Src\Battle\Domain\Effects\Capacidades\InmuneADanioIndirecto;
use Src\Battle\Domain\Effects\Capacidades\InmuneAFlag;
use Src\Battle\Domain\Effects\Capacidades\InmuneARecoil;
use Src\Battle\Domain\Effects\Capacidades\InmuneCritico;
use Src\Battle\Domain\Effects\Capacidades\InmunidadTipo;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioEscudo;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioInfligido;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioRecibido;
use Src\Battle\Domain\Effects\Capacidades\ModificaEstadistica;
use Src\Battle\Domain\Effects\Capacidades\ModificaPrecision;
use Src\Battle\Domain\Effects\Capacidades\ModificaPrecisionEstado;
use Src\Battle\Domain\Effects\Capacidades\ProbabilidadEsquiva;
use Src\Battle\Domain\Effects\Capacidades\PuedeContraatacar;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlContacto;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDebilitamiento;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDebilitarRival;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlEscudoRoto;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlEstado;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlSerObjetivo;
use Src\Battle\Domain\Effects\Capacidades\RedirigeObjetivo;
use Src\Battle\Domain\Effects\Capacidades\TransformaTipoMovimiento;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\FlagMovimiento;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Battle\Domain\ValueObjects\AjusteGolpes;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Shared\Tipos\TipoPokemon;

/**
 * @implements \IteratorAggregate<int, InterfazEfecto>
 */
class ColeccionEfectos implements \Countable, \IteratorAggregate
{
    /** @var InterfazEfecto[] */
    private array $effects = [];

    /** @return \ArrayIterator<int, InterfazEfecto> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->effects);
    }

    public function add(InterfazEfecto $effect): void
    {
        $this->effects[] = $effect;
    }

    public function first(): ?InterfazEfecto
    {
        return $this->effects[0] ?? null;
    }

    /**
     * Sustituye la colección completa. La usan la supresión/copia/intercambio
     * de habilidades y los efectos que deben retirarse temporalmente.
     *
     * @param  list<InterfazEfecto>  $effects
     */
    public function reemplazar(array $effects): void
    {
        $this->effects = $effects;
    }

    public function all(): array
    {
        return $this->effects;
    }

    /**
     * Efectos marcados como únicos, en orden de registro.
     *
     * Equivalencia exacta con el filtrado previo (array_filter + array_values):
     * mismo orden y mismo reindexado (append secuencial).
     */
    public function unicos(): self
    {
        $resultado = new self();

        foreach ($this->effects as $e) {
            if ($e->esUnico()) {
                $resultado->add($e);
            }
        }

        return $resultado;
    }

    public function find(string $clave): ?InterfazEfecto
    {
        foreach ($this->effects as $e) {
            if ($e->obtenerClave() === $clave) {
                return $e;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->effects);
    }

    public function isEmpty(): bool
    {
        return empty($this->effects);
    }

    // ─── Capacidades (interfaces segregadas) ─────────────────

    public function multiplicadorDanioInfligido(AccionBatalla $accion): float
    {
        $factor = 1.0;
        foreach ($this->effects as $e) {
            if ($e instanceof ModificaDanioInfligido) {
                $factor *= $e->multiplicadorDanioInfligido($accion);
            }
        }

        return $factor;
    }

    public function multiplicadorDanioEscudo(AccionBatalla $accion): float
    {
        $factor = 1.0;
        foreach ($this->effects as $e) {
            if ($e instanceof ModificaDanioEscudo) {
                $factor *= $e->multiplicadorDanioEscudo($accion);
            }
        }

        return $factor;
    }

    public function multiplicadorDanioRecibido(AccionBatalla $accion): float
    {
        $factor = 1.0;
        foreach ($this->effects as $e) {
            if ($e instanceof ModificaDanioRecibido) {
                $factor *= $e->multiplicadorDanioRecibido($accion);
            }
        }

        return $factor;
    }

    public function multiplicadorEstadistica(Combatiente $portador, StatClave $estadistica): float
    {
        $factor = 1.0;
        foreach ($this->effects as $e) {
            if ($e instanceof ModificaEstadistica) {
                $factor *= $e->multiplicadorEstadistica($portador, $estadistica);
            }
        }

        return $factor;
    }

    public function inmunidadTipo(TipoPokemon $tipo): ?InmunidadTipo
    {
        foreach ($this->effects as $e) {
            if ($e instanceof InmunidadTipo && $e->esInmuneA($tipo)) {
                return $e;
            }
        }

        return null;
    }

    public function esInmuneACritico(): bool
    {
        foreach ($this->effects as $e) {
            if ($e instanceof InmuneCritico && $e->esInmuneACritico()) {
                return true;
            }
        }

        return false;
    }

    public function ajustarGolpes(AccionBatalla $accion): ?AjusteGolpes
    {
        foreach ($this->effects as $efecto) {
            if ($efecto instanceof AjustaGolpes) {
                $ajuste = $efecto->ajustarGolpes($accion);
                if ($ajuste !== null) {
                    return $ajuste;
                }
            }
        }

        return null;
    }

    public function multiplicadorProbabilidadCritico(): float
    {
        $factor = 1.0;
        foreach ($this->effects as $e) {
            if ($e instanceof AjustaCritico) {
                $factor *= $e->multiplicadorProbabilidadCritico();
            }
        }

        return $factor;
    }

    public function multiplicadorStab(AccionBatalla $accion): float
    {
        $factor = 1.0;
        foreach ($this->effects as $e) {
            if ($e instanceof BonusStab) {
                $factor *= $e->multiplicadorStab($accion);
            }
        }

        return $factor;
    }

    public function redireccion(AccionBatalla $accion): ?RedirigeObjetivo
    {
        foreach ($this->effects as $e) {
            if ($e instanceof RedirigeObjetivo && $e->redirige($accion)) {
                return $e;
            }
        }

        return null;
    }

    public function tipoEfectivoMovimiento(AccionBatalla $accion): ?TipoPokemon
    {
        foreach ($this->effects as $e) {
            if ($e instanceof TransformaTipoMovimiento) {
                $tipo = $e->tipoEfectivo($accion);
                if ($tipo !== null) {
                    return $tipo;
                }
            }
        }

        return null;
    }

    public function multiplicadorPrecisionAtaque(): float
    {
        $factor = 1.0;
        foreach ($this->effects as $efecto) {
            if ($efecto instanceof ModificaPrecision) {
                $factor *= $efecto->multiplicadorPrecision();
            }
        }

        return $factor;
    }

    public function multiplicadorPrecisionEstado(): float
    {
        $factor = 1.0;
        foreach ($this->effects as $efecto) {
            if ($efecto instanceof ModificaPrecisionEstado) {
                $factor *= $efecto->multiplicadorPrecisionEstado();
            }
        }

        return $factor;
    }

    public function inmuneAFlag(FlagMovimiento $flag): bool
    {
        foreach ($this->effects as $efecto) {
            if ($efecto instanceof InmuneAFlag && $efecto->esInmuneAFlag($flag)) {
                return true;
            }
        }

        return false;
    }

    public function probabilidadEsquiva(?Combatiente $portador = null): float
    {
        $probabilidad = 0.0;
        foreach ($this->effects as $e) {
            if ($e instanceof ProbabilidadEsquiva) {
                $probabilidad = max($probabilidad, $e->probabilidadEsquiva($portador));
            }
        }

        return min(1.0, $probabilidad);
    }

    public function esInmuneARecoil(): bool
    {
        foreach ($this->effects as $e) {
            if ($e instanceof InmuneARecoil && $e->esInmuneARecoil()) {
                return true;
            }
        }

        return false;
    }

    public function bloqueaDanioIndirecto(): bool
    {
        foreach ($this->effects as $e) {
            if ($e instanceof InmuneADanioIndirecto && $e->bloqueaDanioIndirecto()) {
                return true;
            }
        }

        return false;
    }

    public function esInmuneAClima(TipoClima $clima): bool
    {
        foreach ($this->effects as $e) {
            if ($e instanceof InmuneAClima && $e->esInmuneAClima($clima)) {
                return true;
            }
        }

        return false;
    }

    public function permiteEstado(Combatiente $portador, EstadoPokemon $estado, AgregadoBatalla $battle): bool
    {
        foreach ($this->effects as $e) {
            if ($e instanceof ReaccionaAlEstado && ! $e->permiteEstado($portador, $estado, $battle)) {
                return false;
            }
        }

        return true;
    }

    public function dispararContacto(Combatiente $portador, Combatiente $atacante, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            if ($e instanceof ReaccionaAlContacto) {
                $e->alRecibirContacto($portador, $atacante, $battle);
            }
        }
    }

    public function alDebilitarRival(Combatiente $portador, Combatiente $rival, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $efecto) {
            if ($efecto instanceof ReaccionaAlDebilitarRival) {
                $efecto->alDebilitarRival($portador, $rival, $battle);
            }
        }
    }

    public function dispararDebilitamiento(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onFainted($portador);
            if ($e instanceof ReaccionaAlDebilitamiento) {
                $e->alDebilitar($portador, $battle);
            }
        }
    }

    public function dispararEscudoRoto(
        Combatiente $portador,
        Combatiente $atacante,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        foreach ($this->effects as $e) {
            if ($e instanceof ReaccionaAlEscudoRoto) {
                $e->alRomperEscudo($portador, $atacante, $resultado, $battle);
            }
        }
    }

    public function dispararSerObjetivo(Combatiente $portador, AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            if ($e instanceof ReaccionaAlSerObjetivo) {
                $e->alSerObjetivo($portador, $accion, $battle);
            }
        }
    }

    public function dispararDanioRecibidoCompleto(
        Combatiente $portador,
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        foreach ($this->effects as $e) {
            if ($e instanceof ReaccionaAlDanioRecibido) {
                $e->alRecibirDanio($portador, $accion, $resultado, $battle);
            }
        }
    }

    public function contraatacar(
        Combatiente $portador,
        AccionBatalla $accion,
        float $danoRecibido,
        AgregadoBatalla $battle,
    ): void {
        foreach ($this->effects as $e) {
            if ($e instanceof PuedeContraatacar) {
                $e->contraatacar($portador, $accion, $danoRecibido, $battle);
            }
        }
    }

    // ─── Event triggers ──────────────────────────────────────

    public function triggerBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onBattleStart($portador, $battle);
        }
    }

    public function triggerRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onRoundStart($portador, $battle);
        }
    }

    public function triggerRoundEnd(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onRoundEnd($portador, $battle);
        }
    }

    public function triggerRoundEndFinal(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onRoundEndFinal($portador, $battle);
        }
    }

    public function dispararDanioInfligido(Combatiente $portador, Combatiente $target, float $daño, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onDamageDealt($portador, $target, $daño, $battle);
        }
    }

    public function dispararDanioRecibido(Combatiente $portador, float $daño, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onDamageReceived($portador, $daño, $battle);
        }
    }

    public function triggerHealed(Combatiente $portador, float $cantidad): void
    {
        foreach ($this->effects as $e) {
            $e->onHealed($portador, $cantidad);
        }
    }

    public function dispararDebilitado(Combatiente $portador): void
    {
        foreach ($this->effects as $e) {
            $e->onFainted($portador);
        }
    }

    public function dispararInicioTurno(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onTurnStart($portador, $battle);
        }
    }

    public function dispararFinTurno(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->effects as $e) {
            $e->onTurnEnd($portador, $battle);
        }
    }
}
