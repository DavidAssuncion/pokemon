<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\ValueObjects\MultiplicadoresStats;

/** Medicina Extraña: al entrar, borra los cambios de stats de los aliados. */
class EfectoCuriousMedicine implements InterfazEfecto
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
            if ($aliado !== $portador) {
                $aliado->setMultiplicadores(MultiplicadoresStats::vacia());
            }
        }
        $battle->agregarLog("{$portador->nombre()} neutraliza los cambios de su equipo");
    }
}
