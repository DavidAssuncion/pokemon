<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;

/** Tinovictoria: mejora la precisión de todo el equipo. */
class EfectoAuraPrecision implements InterfazEfecto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(private readonly string $clave, private readonly float $factor = 1.10)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        foreach ($this->equipoDe($battle, $portador)?->combatientesCollection()->vivos() ?? [] as $aliado) {
            $aliado->effects()->add(new EfectoPrecision($this->clave, $this->factor));
        }
    }
}
