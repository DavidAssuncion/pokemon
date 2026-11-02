<?php

declare(strict_types=1);

namespace Src\Exploraciones\Domain\DataTransferObjects;

use Illuminate\Support\Collection;
use Src\Exploraciones\Domain\Recompensas\RecompensaEv;
use Src\Exploraciones\Domain\Recompensas\RecompensaFamilia;
use Src\Exploraciones\Domain\Recompensas\RecompensaTipo;

/**
 * Caramelos calculados de los eventos hallazgo (D8). Sustituye el array
 * {caramelosFamilia, caramelosEv, caramelosTipo} que devolvía el dominio.
 *
 * Deuda: las colecciones internas siguen siendo Illuminate\Collection; migrar
 * a colecciones tipadas del módulo cuando el pipeline de eventos deje de ser
 * array-based.
 */
final readonly class HallazgosCalculados
{
    /**
     * @param  Collection<int, RecompensaFamilia>  $caramelosFamilia
     * @param  Collection<int, RecompensaEv>  $caramelosEv
     * @param  Collection<int, RecompensaTipo>  $caramelosTipo
     */
    public function __construct(
        public Collection $caramelosFamilia,
        public Collection $caramelosEv,
        public Collection $caramelosTipo,
    ) {
    }
}
