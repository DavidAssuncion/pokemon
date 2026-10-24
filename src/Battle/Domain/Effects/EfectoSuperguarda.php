<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioRecibido;

/** Superguarda: solo recibe daño de movimientos supereficaces. */
class EfectoSuperguarda implements InterfazEfecto, ModificaDanioRecibido
{
    use ComportamientosPorDefecto;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function multiplicadorDanioRecibido(AccionBatalla $accion): float
    {
        $tipo = $accion->attacker->tipoEfectivoMovimiento($accion);

        return $accion->defender->efectividadContra($tipo) > 1.0 ? 1.0 : 0.0;
    }
}
