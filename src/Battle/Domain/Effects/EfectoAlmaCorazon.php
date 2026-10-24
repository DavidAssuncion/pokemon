<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Enums\StatClave;
use Src\Battle\Domain\ValueObjects\CambioStat;

/** Alma Corazón: sube At. Especial cuando un aliado cae. */
class EfectoAlmaCorazon implements InterfazEfecto
{
    use ComportamientosPorDefecto;

    private int $aliadosCaidos = 0;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function onRoundStart(Combatiente $portador, AgregadoBatalla $battle): void
    {
        $equipo = $battle->equipoDe($portador);
        if ($equipo === null) {
            return;
        }

        $caidos = 0;
        foreach ($equipo->combatientesCollection()->all() as $aliado) {
            if ($aliado !== $portador && ! $aliado->estaVivo()) {
                $caidos++;
            }
        }

        $nuevos = $caidos - $this->aliadosCaidos;
        if ($nuevos <= 0) {
            return;
        }

        $this->aliadosCaidos = $caidos;
        for ($i = 0; $i < $nuevos; $i++) {
            $portador->setMultiplicadores(CambioStat::desdeEtapas(StatClave::ATAQUE_ESPECIAL, 1)->aplicadoTemporalEn($portador->multiplicadores()));
        }
        $battle->agregarLog("¡{$portador->nombre()} se fortalece por Alma Corazón!");
    }
}
