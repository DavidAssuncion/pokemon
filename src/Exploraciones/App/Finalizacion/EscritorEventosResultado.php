<?php

declare(strict_types=1);

namespace Src\Exploraciones\App\Finalizacion;

use App\Models\ExploracionActiva;
use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Src\Exploraciones\Domain\Perdidas\ObjetoPerdido;
use Src\Exploraciones\Domain\Recompensas\ResultadoRecompensas;
use Src\Exploraciones\Presentation\TransformadorResultadoExploracion;

/**
 * Único escritor de `eventos['derrotados']`, `eventos['resultado']` y
 * `eventos['objetos_perdidos']`. No persiste por sí mismo: modifica la
 * colección que el orquestador persistirá junto con el regreso.
 */
final class EscritorEventosResultado
{
    public function __construct(
        private readonly TransformadorResultadoExploracion $transformador,
    ) {
    }

    /**
     * @param  list<ObjetoPerdido>  $objetosPerdidos
     * @param  BaseCollection<string, mixed>  $eventos
     */
    public function escribirPerdidas(BaseCollection $eventos, array $objetosPerdidos): void
    {
        $eventos->put('objetos_perdidos', array_map(
            fn (ObjetoPerdido $objeto): array => $objeto->aArray(),
            $objetosPerdidos,
        ));
    }

    /**
     * Escribe `eventos['derrotados']` y `eventos['resultado']` (contrato
     * aditivo RF-10) y asigna la colección al modelo.
     *
     * @param  Collection<int, Pokemon>  $pokemons
     * @param  array<int, Collection<int, Pokemon>>  $miembrosPorCadena
     * @param  list<int>  $idsDerrotados
     * @param  array{encuentros: int, victorias: int, huidas: int, emboscadas: int, contratiempos: int}  $incidentes
     * @param  BaseCollection<string, mixed>  $eventos
     */
    public function registrar(
        ExploracionActiva $exploracion,
        ResultadoRecompensas $recompensas,
        Collection $pokemons,
        array $miembrosPorCadena,
        array $idsDerrotados,
        string $categoria,
        int $durationReal,
        int $tiempoPerdido,
        array $incidentes,
        BaseCollection $eventos,
        bool $delHabitat,
    ): void {
        $eventos->put('derrotados', $idsDerrotados);
        $eventos->put('resultado', $this->transformador->desde(
            $recompensas,
            $pokemons,
            $miembrosPorCadena,
            categoria: $categoria,
            durationReal: $durationReal,
            tiempoPerdido: $tiempoPerdido,
            incidentes: $incidentes,
            bonos: ['del_habitat' => $delHabitat],
        ));

        $exploracion->eventos = $eventos;
    }
}
