<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\AmbitoDanio;
use Src\Battle\Domain\Enums\CondicionDanio;
use Src\Shared\Tipos\TipoPokemon;

/** Batería, Punto de Energía y Espíritu Acerado: aura de daño para aliados. */
class EfectoAuraAliada implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    public function __construct(
        private readonly string $clave,
        private readonly float $multiplicador,
        private readonly CondicionDanio $condicion = CondicionDanio::SIEMPRE,
        private readonly ?TipoPokemon $tipo = null,
    ) {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onBattleStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($portador);
        if ($equipo === null) {
            return;
        }

        foreach ($equipo->combatientesCollection()->vivos() as $aliado) {
            if ($aliado === $portador) {
                continue;
            }

            $aliado->effects()->add(new EfectoModificadorDanio(
                clave: $this->clave,
                ambito: AmbitoDanio::INFLIGIDO,
                multiplicador: $this->multiplicador,
                condicion: $this->condicion,
                tipo: $this->tipo,
            ));
        }
    }
}
