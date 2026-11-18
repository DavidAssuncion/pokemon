<?php

declare(strict_types=1);

namespace Src\Forja\App;

use Src\Equipos\Domain\Ports\PiezasEquipadas;
use Src\Forja\App\Collections\IngredientesDetalleCollection;
use Src\Forja\App\Collections\RecetaDetalleCollection;
use Src\Forja\App\DataTransferObjects\IngredienteDetalle;
use Src\Forja\App\DataTransferObjects\RecetaDetalle;
use Src\Forja\Domain\CatalogoRecetas;
use Src\Forja\Domain\Piezas\CatalogoPiezas;
use Src\Forja\Domain\Piezas\DataTransferObjects\PiezaDesbloqueada;
use Src\Forja\Domain\Piezas\DataTransferObjects\RecetaPieza;
use Src\Forja\Domain\Piezas\ReglasPiezas;
use Src\Forja\Domain\RecetaFabricacion;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Items\CategoriaItem;
use Src\Shared\Domain\Items\ClaveBonguri;
use Src\Shared\Domain\Ports\InventarioJugador;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Devuelve todas las recetas (estáticas y de pieza) con el stock de cada
 * ingrediente resuelto en una sola lectura del inventario.
 *
 * Las piezas equipadas solo afectan al resultado de su propia receta
 * (`pieza_ya_poseida`): no son stock consumible, así que no cuentan como
 * ingrediente ni como pieza anterior.
 */
final readonly class ListarRecetas
{
    public function __construct(
        private InventarioJugador $inventario,
        private ListarPiezasForja $listarPiezasForja,
        private PiezasEquipadas $piezasEquipadas,
    ) {
    }

    public function handle(int $userId): RecetaDetalleCollection
    {
        $recetas = CatalogoRecetas::todas();
        $piezas = $this->listarPiezasForja->handle($userId);

        $claves = [];

        foreach ($recetas as $receta) {
            foreach (array_keys($receta->ingredientes->toArray()) as $clave) {
                $claves[] = $clave;
            }

            $claves[] = $receta->resultadoClave;
        }

        foreach ($piezas as $recetaPieza) {
            foreach (array_keys($recetaPieza->ingredientes->toArray()) as $clave) {
                $claves[] = $clave;
            }

            $claves[] = $recetaPieza->resultado()->clave();
        }

        $saldos = $this->inventario->saldos(
            $userId,
            new StringCollection(array_values(array_unique($claves))),
        );

        $equipadas = $this->piezasEquipadas->clavesDe($userId);

        $detalles = new RecetaDetalleCollection();

        foreach ($recetas as $receta) {
            $detalles->add($this->detalle($receta, $saldos));
        }

        foreach ($piezas as $recetaPieza) {
            $detalles->add($this->detallePieza(
                CatalogoPiezas::evaluar($recetaPieza, $saldos, $equipadas),
                $saldos,
            ));
        }

        return $detalles;
    }

    private function detalle(RecetaFabricacion $receta, MapaConteo $saldos): RecetaDetalle
    {
        $ingredientes = new IngredientesDetalleCollection();

        foreach ($receta->ingredientes->toArray() as $clave => $requerido) {
            $definicion = CatalogoItems::definicion($clave);

            $ingredientes->add(new IngredienteDetalle(
                clave: $clave,
                nombre: $definicion->nombre,
                icono: $definicion->icono,
                requerido: $requerido,
                disponible: $saldos->obtener($clave),
                origen: $this->origenIngrediente($clave),
            ));
        }

        $puedeFabricar = $ingredientes->every(
            static fn (IngredienteDetalle $ingrediente): bool => $ingrediente->disponible >= $ingrediente->requerido,
        );

        $resultado = CatalogoItems::definicion($receta->resultadoClave);

        return new RecetaDetalle(
            clave: $receta->clave,
            resultadoClave: $receta->resultadoClave,
            resultadoNombre: $resultado->nombre,
            resultadoIcono: $resultado->icono,
            resultadoCantidad: $receta->resultadoCantidad,
            etiqueta: $receta->etiqueta,
            ingredientes: $ingredientes,
            puedeFabricar: $puedeFabricar,
            motivo: $puedeFabricar ? null : ReglasPiezas::MOTIVO_FALTAN_MATERIALES,
            categoria: $this->seccionDeCategoria($receta->categoriaResultado),
            motivoTexto: $puedeFabricar
                ? null
                : ReglasPiezas::textoMotivo(ReglasPiezas::MOTIVO_FALTAN_MATERIALES),
        );
    }

    private function detallePieza(PiezaDesbloqueada $estado, MapaConteo $saldos): RecetaDetalle
    {
        $receta = $estado->receta;
        $ingredientes = new IngredientesDetalleCollection();

        foreach ($receta->ingredientes->toArray() as $clave => $requerido) {
            $ingredientes->add($this->ingredientePieza($receta, $clave, $requerido, $saldos));
        }

        return new RecetaDetalle(
            clave: $receta->clave,
            resultadoClave: $receta->resultado()->clave(),
            resultadoNombre: $receta->definicion->nombre(),
            resultadoIcono: $receta->definicion->icono,
            resultadoCantidad: 1,
            etiqueta: $receta->etiqueta,
            ingredientes: $ingredientes,
            puedeFabricar: $estado->puedeFabricar,
            motivo: $estado->motivo,
            categoria: 'piezas',
            motivoTexto: $estado->motivo === null
                ? null
                : ReglasPiezas::textoMotivo(
                    $estado->motivo,
                    $receta->definicion->nivelNidoRequerido,
                    $receta->piezaAnteriorNombre,
                ),
            definicionPieza: $receta->definicion,
            nido: $receta->nido,
            piezaAnterior: $receta->piezaAnterior,
            piezaAnteriorNombre: $receta->piezaAnteriorNombre,
            desbloqueada: $estado->desbloqueada,
        );
    }

    private function ingredientePieza(
        RecetaPieza $receta,
        string $clave,
        int $requerido,
        MapaConteo $saldos,
    ): IngredienteDetalle {
        if ($receta->piezaAnterior !== null && $clave === $receta->piezaAnterior->clave()) {
            return new IngredienteDetalle(
                clave: $clave,
                nombre: $receta->piezaAnteriorNombre ?? $clave,
                icono: '/images/iconos_webp/'.$receta->piezaAnterior->speciesId.'.webp',
                requerido: $requerido,
                disponible: $saldos->obtener($clave),
            );
        }

        $definicion = CatalogoItems::definicion($clave);

        return new IngredienteDetalle(
            clave: $clave,
            nombre: $definicion->nombre,
            icono: $definicion->icono,
            requerido: $requerido,
            disponible: $saldos->obtener($clave),
            origen: $this->origenIngrediente($clave),
        );
    }

    /**
     * Clave de la sección de la Forja que agrupa la categoría del resultado.
     * Las piezas tienen sección propia y el resto se agrupa como la vista
     * histórica (pociones y recipientes en medicinas).
     */
    private function seccionDeCategoria(CategoriaItem $categoria): string
    {
        return match ($categoria) {
            CategoriaItem::POCION, CategoriaItem::MATERIAL => 'medicinas',
            CategoriaItem::MEJORA => 'mejoras',
            CategoriaItem::REDUCTOR => 'reductores',
            CategoriaItem::RESISTENCIA => 'resistencias',
            CategoriaItem::ARMA, CategoriaItem::ARMADURA, CategoriaItem::AMULETO => 'piezas',
            CategoriaItem::BAYA, CategoriaItem::BONGURI => $categoria->value,
        };
    }

    /**
     * Origen del ingrediente cuando es un bonguri; null para el resto.
     */
    private function origenIngrediente(string $clave): ?string
    {
        $prefijo = CategoriaItem::BONGURI->prefijo();

        if (! str_starts_with($clave, $prefijo)) {
            return null;
        }

        return ClaveBonguri::tryFrom(substr($clave, strlen($prefijo)))?->origen();
    }
}
