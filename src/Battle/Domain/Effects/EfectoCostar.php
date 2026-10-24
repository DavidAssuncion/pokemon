<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Unísono: copia los aumentos de estadísticas de un aliado al entrar. */
class EfectoCostar implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->equipoDe($battle, $portador)?->combatientesCollection()->vivos() ?? [] as $aliado) {
            if ($aliado === $portador) {
                continue;
            }
            foreach ($aliado->multiplicadores()->obtenerEtapas() as $statValue => $etapa) {
                if ($etapa <= 0) {
                    continue;
                }
                $stat = StatClave::tryFrom($statValue);
                if ($stat !== null) {
                    $portador->setMultiplicadores(CambioStat::desdeEtapas($stat, $etapa)->aplicadoTemporalEn($portador->multiplicadores()));
                }
            }
        }
        $battle->agregarLog("{$portador->nombre()} copia los refuerzos de su aliado");
    }
}
