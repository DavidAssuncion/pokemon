<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TipoEnum;
use Src\Battle\Domain\Catalog\CatalogoObjetos;
use Src\Shared\Tipos\TipoPokemon;

/**
 * Opciones de catálogo que consume la vista de equipos.
 *
 * Antes se construían con `@php` dentro de `equipos/index.blade.php`.
 */
final class EquiposVistaPresenter
{
    /**
     * @return array{
     *     tiposVista: list<array{id: int, nombre: string, slug: string}>,
     *     objetos: array<string, string>
     * }
     */
    public static function toView(): array
    {
        $tipos = [];
        foreach (TipoEnum::options() as $id => $nombre) {
            $tipos[] = [
                'id' => (int) $id,
                'nombre' => (string) $nombre,
                'slug' => TipoPokemon::tryFrom((int) $id)?->slug() ?? 'normal',
            ];
        }

        $objetos = [];
        foreach (CatalogoObjetos::claves() as $clave) {
            $objetos[$clave] = CatalogoObjetos::nombreDe($clave);
        }

        return [
            'tiposVista' => $tipos,
            'objetos' => $objetos,
        ];
    }
}
