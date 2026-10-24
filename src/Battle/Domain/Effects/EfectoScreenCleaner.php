<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;

/** Antibarrera: elimina las pantallas de ambos equipos al entrar. */
class EfectoScreenCleaner implements InterfazEfecto
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
        $battle->equipoDe($portador)?->limpiarPantallas();
        $this->equipoRivalDe($battle, $portador)?->limpiarPantallas();
        $battle->agregarLog("{$portador->nombre()} limpia las pantallas del campo");
    }
}
