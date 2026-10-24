<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDebilitarRival;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Ultraimpulso: sube la estadística más alta al debilitar a un rival. */
class EfectoUltraimpulso implements InterfazEfecto, ReaccionaAlDebilitarRival
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alDebilitarRival(Combatiente $portador, Combatiente $rival, AgregadoBatalla $battle): void
    {
        $mejor = StatClave::ATAQUE;
        $valor = 0.0;
        foreach ([StatClave::ATAQUE, StatClave::DEFENSA, StatClave::ATAQUE_ESPECIAL, StatClave::DEFENSA_ESPECIAL, StatClave::VELOCIDAD] as $stat) {
            $actual = $portador->obtenerStatEfectivo($stat);
            if ($actual > $valor) {
                $valor = $actual;
                $mejor = $stat;
            }
        }

        $portador->setMultiplicadores(CambioStat::desdeEtapas($mejor, 1)->aplicadoTemporalEn($portador->multiplicadores()));
        $battle->agregarLog("{$portador->nombre()} sube su {$mejor->label()} por Ultraimpulso");
    }
}
