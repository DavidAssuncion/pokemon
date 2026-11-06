<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Ejecucion;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\ValueObjects\AjusteGolpes;
use Src\Battle\Domain\ValueObjects\PerfilMovimiento;
use Src\Shared\Domain\Ports\GeneradorAleatorio;

/**
 * Resuelve el número de golpes del movimiento y la fracción de potencia por
 * golpe, incluidos el encadenamiento y los efectos que ajustan la secuencia.
 */
final class CalculadorGolpes
{
    public function __construct(
        private readonly GeneradorAleatorio $aleatorio,
    ) {
    }

    public function calcular(AccionBatalla $accion, PerfilMovimiento $perfil): AjusteGolpes
    {
        $golpes = $accion->attacker->tieneEfecto('encadenamiento')
            ? $perfil->golpesMax
            : $this->aleatorio->entero($perfil->golpesMin, $perfil->golpesMax);

        $fraccionPotencia = $perfil->fraccionPotenciaPorGolpe;
        $ajusteGolpes = $accion->attacker->ajustarGolpes($accion);
        if ($ajusteGolpes !== null) {
            $golpes = $ajusteGolpes->golpes;
            $fraccionPotencia = $ajusteGolpes->fraccionPotencia;
        }

        return new AjusteGolpes(
            golpes: $golpes,
            fraccionPotencia: $fraccionPotencia,
        );
    }
}
