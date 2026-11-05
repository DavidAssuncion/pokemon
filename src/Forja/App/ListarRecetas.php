<?php

declare(strict_types=1);

namespace Src\Forja\App;

use Src\Forja\App\Collections\IngredientesDetalleCollection;
use Src\Forja\App\Collections\RecetaDetalleCollection;
use Src\Forja\App\DataTransferObjects\IngredienteDetalle;
use Src\Forja\App\DataTransferObjects\RecetaDetalle;
use Src\Forja\Domain\CatalogoRecetas;
use Src\Forja\Domain\RecetaFabricacion;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Items\CategoriaItem;
use Src\Shared\Domain\Items\ClaveBonguri;
use Src\Shared\Domain\Ports\InventarioJugador;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Devuelve todas las recetas con el stock de cada ingrediente resuelto en
 * una sola lectura del inventario.
 */
final readonly class ListarRecetas
{
    public function __construct(
        private InventarioJugador $inventario,
    ) {
    }

    public function handle(int $userId): RecetaDetalleCollection
    {
        $recetas = CatalogoRecetas::todas();
        $claves = [];

        foreach ($recetas as $receta) {
            foreach (array_keys($receta->ingredientes->toArray()) as $clave) {
                $claves[] = $clave;
            }

            $claves[] = $receta->resultadoClave;
        }

        $saldos = $this->inventario->saldos(
            $userId,
            new StringCollection(array_values(array_unique($claves))),
        );

        $detalles = new RecetaDetalleCollection();

        foreach ($recetas as $receta) {
            $detalles->add($this->detalle($receta, $saldos));
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
            motivo: $puedeFabricar ? null : 'faltan_materiales',
        );
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
