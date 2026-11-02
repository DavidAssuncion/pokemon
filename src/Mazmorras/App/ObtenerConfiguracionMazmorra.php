<?php

declare(strict_types=1);

namespace Src\Mazmorras\App;

use App\Models\Habitat;
use Src\Mazmorras\Domain\ConfiguracionMazmorra;

/**
 * Lee la configuración de mazmorra de un hábitat (columna `habitats.mazmorra`).
 * null si el hábitat no existe o no tiene mazmorra configurada.
 *
 * Punto único de lectura para el módulo y el presentador de resultado de
 * batalla (evita duplicar la consulta Eloquent en app/).
 */
final class ObtenerConfiguracionMazmorra
{
    public function deHabitat(int $habitatId): ?ConfiguracionMazmorra
    {
        $habitat = Habitat::query()->find($habitatId);

        return $habitat !== null
            ? ConfiguracionMazmorra::desdeJson($habitat->mazmorra)
            : null;
    }
}
