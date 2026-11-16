<?php

declare(strict_types=1);

namespace Src\Exploraciones\App;

use App\Models\ExploracionActiva;
use Illuminate\Database\Eloquent\Collection;

/**
 * Partición de las expediciones de un usuario en activas, terminadas y
 * archivadas, ya ordenadas por la aplicación.
 *
 * DTO interno de `App`: no cruza a dominio ni se serializa a JSON. Las tres
 * listas excluyen canceladas.
 */
final readonly class ExpedicionesAgrupadas
{
    /**
     * @param  Collection<int, ExploracionActiva>  $activas
     * @param  Collection<int, ExploracionActiva>  $terminadas
     * @param  Collection<int, ExploracionActiva>  $archivadas
     */
    public function __construct(
        public Collection $activas,
        public Collection $terminadas,
        public Collection $archivadas,
    ) {
    }

    /**
     * Todas las expediciones agrupadas en el mismo orden canónico.
     *
     * @return Collection<int, ExploracionActiva>
     */
    public function todas(): Collection
    {
        return new Collection([
            ...$this->activas->all(),
            ...$this->terminadas->all(),
            ...$this->archivadas->all(),
        ]);
    }
}
