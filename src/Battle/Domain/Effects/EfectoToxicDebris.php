<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\AgregadoBatalla;
use Src\Battle\Domain\Combatiente;
use Src\Battle\Domain\Effects\Capacidades\ReaccionaAlContacto;
use Src\Battle\Domain\Effects\Concerns\LocalizaEquipo;
use Src\Battle\Domain\Effects\Equipo\EfectoEquipoTrampa;

/** Capa Tóxica: al recibir contacto coloca Púas Tóxicas en el campo rival. */
class EfectoToxicDebris implements InterfazEfecto, ReaccionaAlContacto
{
    use ComportamientosPorDefecto;
    use LocalizaEquipo;

    public function __construct(private readonly string $clave)
    {
    }

    public function obtenerClave(): string
    {
        return $this->clave;
    }

    public function alRecibirContacto(Combatiente $portador, Combatiente $atacante, AgregadoBatalla $battle): void
    {
        $rival = $this->equipoRivalDe($battle, $portador);
        if ($rival === null) {
            return;
        }

        foreach ($rival->efectos()->all() as $efecto) {
            if ($efecto instanceof EfectoEquipoTrampa && $efecto->obtenerClave() === 'puas_toxicas') {
                $efecto->sumar();

                return;
            }
        }

        $rival->agregarEfecto(new EfectoEquipoTrampa('puas_toxicas', 0.0625, 0.125, 3));
        $battle->agregarLog("{$portador->nombre()} esparce Púas Tóxicas");
    }
}
