<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\InmunidadTipo;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Inmunidad a un tipo de movimiento con reacción al absorberlo:
 * cura un porcentaje del HP máximo y/o sube una estadística.
 * Cubre Absorción Agua/Eléctrico, Colector y Pararrayos.
 */
class EfectoInmunidadTipo implements InterfazEfecto, InmunidadTipo
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly TipoPokemon $tipo,
        private readonly float $curacionFraccion = 0.0,
        private readonly ?StatClave $statBoost = null,
        private readonly int $etapas = 0,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function esInmuneA(TipoPokemon $tipo): bool
    {
        return $this->tipo === $tipo;
    }

    public function alAbsorber(Combatiente $portador, TipoPokemon $tipo, AgregadoBatalla $battle): void
    {
        if ($this->curacionFraccion > 0.0) {
            $cantidad = $portador->pokemon()->battleStats()->hp * $this->curacionFraccion;
            $portador->curarConExcedente($cantidad);
            $battle->agregarLog("{$portador->nombre()} absorbe el ataque y recupera ".round($cantidad).' PS');
        }

        if ($this->statBoost !== null && $this->etapas !== 0) {
            $cambio = CambioStat::desdeEtapas($this->statBoost, $this->etapas);
            $portador->setMultiplicadores($cambio->aplicadoTemporalEn($portador->multiplicadores()));
            $battle->agregarLog("{$portador->nombre()} sube su {$this->statBoost->label()}");
        }
    }
}
