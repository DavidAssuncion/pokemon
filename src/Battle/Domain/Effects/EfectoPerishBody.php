<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlContacto;
use Src\Battle\Domain\Enums\ClaveCondicion;
use Src\Battle\Domain\ValueObjects\CondicionVolatil;

/** Cuerpo Mortal: el contacto sella el destino de ambos combatientes. */
class EfectoPerishBody implements InterfazEfecto, ReaccionaAlContacto
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirContacto(Combatiente $portador, Combatiente $atacante, AgregadoBatalla $battle): void
    {
        $portador->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::CANTO_MORTAL, 3));
        $atacante->reemplazarCondicion(new CondicionVolatil(ClaveCondicion::CANTO_MORTAL, 3));
        $battle->agregarLog('¡Cuerpo Mortal sella el destino de ambos!');
    }
}
