<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;

/** Hospitalidad: cura un poco a los aliados al entrar en combate. */
class EfectoHospitality implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(private readonly string $clave, private readonly float $fraccion = 0.25)
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
            $aliado->curarConExcedente($aliado->pokemon()->battleStats()->hp * $this->fraccion);
        }
        $battle->agregarLog("{$portador->nombre()} ofrece hospitalidad a su equipo");
    }
}
