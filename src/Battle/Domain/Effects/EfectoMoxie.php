<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDebilitarRival;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Autoestima y variantes: suben una estadística al debilitar a un rival. */
class EfectoMoxie implements InterfazEfecto, ReaccionaAlDebilitarRival
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly StatClave $stat = StatClave::ATAQUE,
        private readonly int $etapas = 1,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alDebilitarRival(Combatiente $portador, Combatiente $rival, AgregadoBatalla $battle): void
    {
        $portador->setMultiplicadores(CambioStat::desdeEtapas($this->stat, $this->etapas)->aplicadoTemporalEn($portador->multiplicadores()));
        $battle->agregarLog("¡{$portador->nombre()} sube su {$this->stat->label()}!");
    }
}
