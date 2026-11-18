<?php

declare(strict_types=1);

namespace Src\Forja\App;

use Illuminate\Support\Facades\DB;
use Src\Equipos\Domain\Ports\PiezasEquipadas;
use Src\Forja\App\DataTransferObjects\ResultadoFabricacion;
use Src\Forja\Domain\Exceptions\RecetaNoExiste;
use Src\Forja\Domain\Piezas\CatalogoPiezas;
use Src\Forja\Domain\Piezas\DataTransferObjects\RecetaPieza;
use Src\Forja\Domain\Ports\DefinicionesPieza;
use Src\Forja\Domain\Ports\NidosConstruidos;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Exceptions\ViolacionReglaNegocio;
use Src\Shared\Domain\Ports\InventarioJugador;

/**
 * Fabricación de una pieza de forja.
 *
 * Resuelve la receta contra los nidos del jugador (404 si no existe), bloquea
 * el nido y el inventario, comprueba el gate, la posesión (inventario o
 * equipada) y la unicidad de la pieza, y consume los ingredientes (incluida la
 * pieza anterior) en una transacción.
 */
final readonly class FabricarPieza
{
    public function __construct(
        private InventarioJugador $inventario,
        private NidosConstruidos $nidos,
        private DefinicionesPieza $lector,
        private PiezasEquipadas $piezasEquipadas,
    ) {
    }

    /**
     * @throws RecetaNoExiste
     * @throws ViolacionReglaNegocio
     */
    public function handle(int $userId, string $recetaClave, int $cantidad = 1): ResultadoFabricacion
    {
        $receta = $this->receta($userId, $recetaClave);

        if ($cantidad !== 1) {
            throw new ViolacionReglaNegocio('Solo se puede fabricar una unidad por acción.');
        }

        return DB::transaction(function () use ($userId, $receta): ResultadoFabricacion {
            $this->bloquearNido($userId, $receta);

            $resultadoClave = $receta->resultado()->clave();

            // Todas las claves de la receta (resultado + ingredientes) se
            // bloquean en orden ascendente: la unicidad de la pieza es por
            // clave y el bloqueo del nido no serializa dos recetas de la misma
            // familia en nidos distintos. El orden global ascendente es el
            // mismo que usan `consumirVarios` y `EquiparPieza`, así que no hay
            // deadlock AB/BA entre fabricar y equipar.
            $clavesBloqueadas = [$resultadoClave, ...$receta->ingredientes->claves()->toList()];
            $clavesBloqueadas = array_values(array_unique($clavesBloqueadas));

            sort($clavesBloqueadas);

            foreach ($clavesBloqueadas as $clave) {
                $this->inventario->bloquearClave($userId, $clave);
            }

            // Una pieza equipada no tiene saldo en el inventario, pero sigue
            // siendo del jugador: fabricarla crearía un duplicado.
            if ($this->piezasEquipadas->clavesDe($userId)->contiene($resultadoClave)) {
                throw new ViolacionReglaNegocio('Ya tienes esa pieza equipada.');
            }

            if ($this->inventario->saldo($userId, $resultadoClave) >= 1) {
                throw new ViolacionReglaNegocio('Ya tienes esta pieza.');
            }

            if ($receta->piezaAnterior !== null
                && $this->inventario->saldo($userId, $receta->piezaAnterior->clave()) < 1) {
                throw new ViolacionReglaNegocio(
                    'Requiere '.($receta->piezaAnteriorNombre ?? 'la pieza anterior').'.',
                );
            }

            $this->inventario->consumirVarios(
                $userId,
                $receta->ingredientes,
                'Faltan materiales para fabricar',
            );

            $this->inventario->incrementar($userId, $resultadoClave, 1);

            return new ResultadoFabricacion(
                recetaClave: $receta->clave,
                resultadoClave: $resultadoClave,
                cantidad: 1,
                saldos: $this->inventario->saldos(
                    $userId,
                    new StringCollection($clavesBloqueadas),
                ),
                pieza: $receta->definicion,
            );
        }, 3);
    }

    /**
     * @throws RecetaNoExiste
     */
    private function receta(int $userId, string $recetaClave): RecetaPieza
    {
        $nidos = $this->nidos->paraJugador($userId);

        if ($nidos->isEmpty()) {
            throw RecetaNoExiste::paraClave();
        }

        $receta = CatalogoPiezas::recetas(
            $this->lector->definiciones($nidos),
            $nidos,
        )->porClave($recetaClave);

        if ($receta === null) {
            throw RecetaNoExiste::paraClave();
        }

        return $receta;
    }

    /**
     * @throws ViolacionReglaNegocio
     */
    private function bloquearNido(int $userId, RecetaPieza $receta): void
    {
        $nivel = $this->nidos->bloquear(
            $userId,
            $receta->nido->farmId,
            $receta->nido->evolutionChainId,
        ) ?? 0;

        if ($nivel < $receta->definicion->nivelNidoRequerido) {
            throw new ViolacionReglaNegocio(
                'Requiere nido nivel '.$receta->definicion->nivelNidoRequerido.'.',
            );
        }
    }
}
