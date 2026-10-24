<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects\Movimiento;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Enums\ClaveEfectoMovimiento;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Punto de extensión para los ataques con lógica propia (Drenadoras, Puas,
 * Maldición, Canto Mortal, Desafío…). El perfil del movimiento lleva la clave
 * y el ejecutor aplica el efecto de campo/estado correspondiente.
 */
interface InterfazEjecutorMovimiento
{
    public function clave(): ClaveEfectoMovimiento;

    /**
     * Potencia efectiva antes de la cadena de daño (Persecución, Brecha Negra).
     * null = usar la potencia del movimiento.
     */
    public function potenciaEfectiva(AccionBatalla $accion, AgregadoBatalla $battle): ?int;

    /**
     * Tipo efectivo antes de la cadena (Meteorobola, Pulso de Campo).
     * null = conservar el tipo del catálogo.
     */
    public function tipoEfectivo(AccionBatalla $accion, AgregadoBatalla $battle): ?TipoPokemon;

    public function ejecutar(AccionBatalla $accion, float $danoAplicado, AgregadoBatalla $battle): void;
}
