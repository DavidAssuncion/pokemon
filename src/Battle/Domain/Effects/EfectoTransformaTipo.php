<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Capacidades\TransformaTipoMovimiento;
use Src\Battle\Domain\Enums\FlagMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Cambia el tipo del movimiento. Si el tipo original haría más daño, conserva
 * el tipo original para no perder efectividad.
 */
class EfectoTransformaTipo implements InterfazEfecto, TransformaTipoMovimiento
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly TipoPokemon $tipo,
        private readonly bool $soloNormal = true,
        private readonly ?FlagMovimiento $flag = null,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function tipoEfectivo(AccionBatalla $accion): ?TipoPokemon
    {
        $original = $accion->move->tipo;
        if ($this->soloNormal && $original !== TipoPokemon::NORMAL) {
            return null;
        }

        if ($this->flag !== null && ! $accion->move->flags->tiene($this->flag)) {
            return null;
        }

        if ($original === $this->tipo) {
            return null;
        }

        $originalEff = $accion->defender->efectividadContra($original);
        $nuevaEff = $accion->defender->efectividadContra($this->tipo);

        return $nuevaEff >= $originalEff ? $this->tipo : null;
    }
}
