<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain;

use Src\Shared\Domain\MultiplicadoresDificultad;
use Src\Shared\Domain\PoliticaIvsEvs;

/**
 * Traduce el nivel de hábitat (1-3) a sus multiplicadores de dificultad.
 *
 * El perfil del hábitat es el de RUTA (D3 del plan v5):
 * 1 → `Nulo`, 2 → `TopDos`, 3 → `Completo`. `PoliticaIvsEvs::perfilRuta()`
 * aplica el clamp a [1, 3], de modo que los niveles fuera de rango se saturan
 * y no lanzan excepción.
 */
final class DificultadHabitat
{
    public static function para(int $nivelHabitat): MultiplicadoresDificultad
    {
        return MultiplicadoresDificultad::para(PoliticaIvsEvs::perfilRuta($nivelHabitat));
    }
}
