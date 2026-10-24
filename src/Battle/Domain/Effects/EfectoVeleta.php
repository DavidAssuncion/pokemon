<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\StatClave;

/**
 * Veleta / Actualización Software: al inicio de cada turno sube un 25 % una
 * estadística aleatoria (Ataque, Defensa, Ataque Especial, Defensa Especial
 * o Velocidad).
 */
class EfectoVeleta implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    /** @var list<StatClave> */
    private const ESTADISTICAS = [
        StatClave::ATAQUE,
        StatClave::DEFENSA,
        StatClave::ATAQUE_ESPECIAL,
        StatClave::DEFENSA_ESPECIAL,
        StatClave::VELOCIDAD,
    ];

    public function __construct(
        private readonly string $clave,
        private readonly float $factor = 1.25,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onTurnStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        if (! $portador->estaVivo()) {
            return;
        }

        $estadistica = self::ESTADISTICAS[mt_rand(0, count(self::ESTADISTICAS) - 1)];
        $portador->setMultiplicadores($portador->multiplicadores()->aplicarTemporal($estadistica, $this->factor));
        $battle->agregarLog("{$portador->nombre()} sube su {$estadistica->label()} por Veleta");
    }
}
