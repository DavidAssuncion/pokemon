<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Sube o baja estadísticas al entrar en combate. */
class EfectoEntradaStats implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    /** @param list<array{StatClave, int}> $cambios */
    public function __construct(
        private readonly string $clave,
        private readonly array $cambios,
        private readonly bool $rivales = false,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $objetivos = $this->rivales
            ? ($this->equipoRivalDe($battle, $portador)?->combatientesCollection()->vivos()->all() ?? [])
            : [$portador];

        foreach ($objetivos as $objetivo) {
            foreach ($this->cambios as [$stat, $etapas]) {
                $objetivo->setMultiplicadores(CambioStat::desdeEtapas($stat, $etapas)->aplicadoTemporalEn($objetivo->multiplicadores()));
            }
        }

        $battle->agregarLog("{$portador->nombre()} activa {$this->clave}");
    }
}
