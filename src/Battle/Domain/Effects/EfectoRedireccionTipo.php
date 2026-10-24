<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\RedirigeObjetivo;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Redirige a su portador los ataques de un tipo que afectan a un solo
 * objetivo (Colector: Agua; Pararrayos: Eléctrico) y sube una estadística.
 *
 * La selección real del objetivo la hace el motor de turnos; esta capacidad
 * expone la intención para que el selector la consulte.
 */
class EfectoRedireccionTipo implements InterfazEfecto, RedirigeObjetivo
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly TipoPokemon $tipo,
        private readonly ?StatClave $statBoost = null,
        private readonly int $etapas = 1,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function redirige(AccionBatalla $accion): bool
    {
        return $accion->move->tipo === $this->tipo
            && $accion->defender !== $accion->attacker
            && $accion->move->potencia > 0;
    }

    public function alRedirigir(Combatiente $portador, AccionBatalla $accion, AgregadoBatalla $battle): void
    {
        if ($this->statBoost === null || $this->etapas === 0) {
            return;
        }

        $cambio = CambioStat::desdeEtapas($this->statBoost, $this->etapas);
        $portador->setMultiplicadores($cambio->aplicadoTemporalEn($portador->multiplicadores()));
        $battle->agregarLog("{$portador->nombre()} redirige el ataque y sube su {$this->statBoost->label()}");
    }
}
