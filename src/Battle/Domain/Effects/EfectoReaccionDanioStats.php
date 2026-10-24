<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AccionBatalla;
use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlDanioRecibido;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;
use Src\Battle\Domain\ValueObjects\ResultadoDanio;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Reacción al recibir daño: sube o baja estadísticas según el tipo o categoría
 * del ataque. Cubre Justiciero, Cobardía, Firmeza, Armadura Frágil y Combustible.
 */
class EfectoReaccionDanioStats implements InterfazEfecto, ReaccionaAlDanioRecibido
{
    use ComportamientosPorDefecto;

    /**
     * @param  list<TipoPokemon>  $tipos
     * @param  list<array{StatClave, int}>  $cambios
     */
    public function __construct(
        private readonly string $clave,
        private readonly array $cambios,
        private readonly array $tipos = [],
        private readonly bool $soloFisico = false,
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
        if (! $resultado->huboDano() || ! $portador->estaVivo()) {
            return;
        }

        if ($this->soloFisico && ! $accion->move->esFisico()) {
            return;
        }

        if ($this->tipos !== [] && ! in_array($accion->move->tipo, $this->tipos, true)) {
            return;
        }

        foreach ($this->cambios as [$stat, $etapas]) {
            $cambio = CambioStat::desdeEtapas($stat, $etapas);
            $portador->setMultiplicadores($cambio->aplicadoTemporalEn($portador->multiplicadores()));
        }

        $battle->agregarLog("{$portador->nombre()} reacciona al daño y cambia sus estadísticas");
    }
}
