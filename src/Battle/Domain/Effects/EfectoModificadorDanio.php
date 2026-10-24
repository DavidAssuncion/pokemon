<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioInfligido;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioRecibido;
use Src\Battle\Domain\Enums\AmbitoDanio;
use Src\Battle\Domain\Enums\CondicionDanio;
use Src\Battle\Domain\Enums\EstadoPokemon;
use Src\Battle\Domain\Enums\FlagMovimiento;
use Src\Battle\Domain\Enums\TipoClima;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Modificador de daño declarativo para habilidades.
 *
 * Cubre los casos “+X % al daño infligido” y “-X % al daño recibido” con
 * condiciones de vida, estado del objetivo, tipo de movimiento o clima.
 * Para multiplicadores dinámicos (Depredador) existe un efecto dedicado.
 */
class EfectoModificadorDanio implements InterfazEfecto, ModificaDanioInfligido, ModificaDanioRecibido
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly AmbitoDanio $ambito,
        private readonly float $multiplicador,
        private readonly CondicionDanio $condicion = CondicionDanio::SIEMPRE,
        private readonly ?TipoPokemon $tipo = null,
        private readonly float $umbralVida = 0.33,
        private readonly ?TipoClima $clima = null,
        private readonly ?FlagMovimiento $flag = null,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorDanioInfligido(AccionBatalla $accion): float
    {
        if ($this->ambito !== AmbitoDanio::INFLIGIDO) {
            return 1.0;
        }

        return $this->seCumple($accion, $accion->attacker) ? $this->multiplicador : 1.0;
    }

    public function multiplicadorDanioRecibido(AccionBatalla $accion): float
    {
        if ($this->ambito !== AmbitoDanio::RECIBIDO) {
            return 1.0;
        }

        return $this->seCumple($accion, $accion->defender) ? $this->multiplicador : 1.0;
    }

    private function seCumple(AccionBatalla $accion, Combatiente $portador): bool
    {
        if ($this->clima !== null && $portador->clima() !== $this->clima) {
            return false;
        }

        return match ($this->condicion) {
            CondicionDanio::SIEMPRE => true,
            CondicionDanio::VIDA_BAJA => $portador->hpActual()
                <= $portador->pokemon()->battleStats()->hp * $this->umbralVida,
            CondicionDanio::VIDA_COMPLETA => $portador->hpActual()
                >= $portador->pokemon()->battleStats()->hp,
            CondicionDanio::OBJETIVO_CON_ESTADO => $accion->defender->estado() !== EstadoPokemon::NONE,
            CondicionDanio::TIPO_MOVIMIENTO => $this->tipo !== null
                && $accion->attacker->tipoEfectivoMovimiento($accion) === $this->tipo,
            CondicionDanio::TIPO_MOVIMIENTO_VIDA_BAJA => $this->tipo !== null
                && $accion->attacker->tipoEfectivoMovimiento($accion) === $this->tipo
                && $portador->hpActual() <= $portador->pokemon()->battleStats()->hp * $this->umbralVida,
            CondicionDanio::MOVIMIENTO_FISICO => $accion->move->esFisico(),
            CondicionDanio::MOVIMIENTO_ESPECIAL => $accion->move->esEspecial(),
            CondicionDanio::FLAG_MOVIMIENTO => $this->flag !== null && $accion->move->flags->tiene($this->flag),
            CondicionDanio::CLIMA => true,
            CondicionDanio::MOVIMIENTO_SUPEREFICAZ => $accion->defender->efectividadContra(
                $accion->attacker->tipoEfectivoMovimiento($accion),
            ) > 1.0,
            CondicionDanio::MOVIMIENTO_CON_SECUNDARIO => ! $accion->move->secundarios->isEmpty()
                || ! $accion->move->autoEfectos->isEmpty(),
            CondicionDanio::MOVIMIENTO_POTENCIA_BAJA => $accion->move->potencia <= 60,
            CondicionDanio::MOVIMIENTO_POCO_EFICAZ => $accion->defender->efectividadContra(
                $accion->attacker->tipoEfectivoMovimiento($accion),
            ) < 1.0,
            CondicionDanio::MOVIMIENTO_CON_RECOIL => $accion->move->perfil->recoilFraccion > 0.0
                || $accion->move->perfil->recoilHpMaximoFraccion > 0.0,
        };
    }
}
