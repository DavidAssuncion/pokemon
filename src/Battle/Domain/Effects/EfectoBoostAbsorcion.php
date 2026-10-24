<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ModificaDanioInfligido;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Absorción Fuego: al recibir un ataque del tipo, el siguiente ataque de ese
 * tipo del portador hace +50 % (una sola vez).
 */
class EfectoBoostAbsorcion implements InterfazEfecto, ModificaDanioInfligido, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;

    private bool $activo = false;

    public function __construct(
        private readonly string $clave,
        private readonly TipoPokemon $tipo,
        private readonly float $multiplicador = 1.5,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirDanio(
        Combatiente $portador,
        AccionBatalla $accion,
        ResultadoDanio $resultado,
        AgregadoBatalla $battle,
    ): void {
        if ($accion->move->tipo === $this->tipo) {
            $this->activo = true;
        }
    }

    public function multiplicadorDanioInfligido(AccionBatalla $accion): float
    {
        if (! $this->activo) {
            return 1.0;
        }

        return $accion->attacker->tipoEfectivoMovimiento($accion) === $this->tipo
            ? $this->multiplicador
            : 1.0;
    }

    public function onDamageDealt(Combatiente $portador, Combatiente $target, float $daño, AgregadoBatalla $battle): void
    {
        // atajo: consume el boost en el primer ataque con daño, no en el primer ataque del tipo, revisar si el rival fuerza ataques de otro tipo
        if ($this->activo && $daño > 0.0) {
            $this->activo = false;
        }
    }
}
