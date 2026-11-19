<?php

declare(strict_types=1);

namespace App\Support\Qa;

use Illuminate\Support\Collection;

/**
 * Read model del panel /debug.
 */
final readonly class ContenidoPanelQa
{
    /**
     * @param  Collection<int, FilaAjusteQa>  $caramelosTipo
     * @param  Collection<int, FilaAjusteQa>  $caramelosEv
     */
    public function __construct(
        public int $experiencia,
        public int $nivel,
        public int $experienciaSiguienteNivel,
        public Collection $caramelosTipo,
        public Collection $caramelosEv,
    ) {
    }
}
