<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Procesamiento;

use App\Models\ExploracionActiva;
use Illuminate\Support\Collection;
use Src\Exploraciones\App\EsDelHabitat;

/**
 * Asegura el bonus de hábitat en `eventos['bonos']['del_habitat']`.
 *
 * El bonus se calcula una sola vez y viaja en el JSON de la exploración; la
 * finalización lo reutiliza sin repetir consulta.
 */
final class GestorBonosHabitat
{
    public function __construct(
        private readonly ?EsDelHabitat $esDelHabitat = null,
    ) {
    }

    /**
     * @param  Collection<string, mixed>  $eventos
     */
    public function asegurar(ExploracionActiva $exploracion, Collection $eventos): void
    {
        if ($eventos->has('bonos')) {
            return;
        }

        $eventos->put('bonos', [
            'del_habitat' => ($this->esDelHabitat ?? new EsDelHabitat())
                ->handle($exploracion->reclutado->pokemon_id, $exploracion->habitat_id),
        ]);
    }
}
