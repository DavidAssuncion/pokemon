<?php

declare(strict_types=1);

namespace App\Support\Piezas;

use Src\Equipos\Domain\ValueObjects\EquipamientoPiezas;
use Src\Forja\Domain\Piezas\Collections\DefinicionesPiezaCollection;
use Src\Forja\Domain\Piezas\DataTransferObjects\DefinicionPieza;
use Src\Shared\Collections\IntCollection;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Exceptions\ItemDesconocido;
use Src\Shared\Domain\Items\ClavePieza;
use stdClass;

/**
 * Construye las 3 filas del contrato `piezas[]` que comparten el endpoint de
 * piezas y los listados de reclutados.
 *
 * Siempre devuelve 3 entradas (slots 1..3): los slots vacíos o con una pieza
 * cuya especie ya no existe llevan metadatos nulos y `stats` vacío.
 */
final class SlotsPiezas
{
    /**
     * @return list<array{
     *     slot: int,
     *     clave: string|null,
     *     tipo: string|null,
     *     nombre: string|null,
     *     icono: string|null,
     *     stats: array<string, int>|stdClass,
     *     habilidad: array{clave: string, nombre: string, descripcion: string}|null
     * }>
     */
    public static function construir(
        EquipamientoPiezas $equipamiento,
        DefinicionesPiezaCollection $definiciones,
    ): array {
        $porClave = self::indexarPorClave($definiciones);
        $filas = [];

        foreach ($equipamiento->toArray() as $pieza) {
            $filas[] = self::fila($pieza['slot'], $pieza['clave'], $porClave);
        }

        return $filas;
    }

    /**
     * Tres slots vacíos, para listados serializados sin presentador.
     *
     * @return list<array{
     *     slot: int,
     *     clave: string|null,
     *     tipo: string|null,
     *     nombre: string|null,
     *     icono: string|null,
     *     stats: array<string, int>|stdClass,
     *     habilidad: array{clave: string, nombre: string, descripcion: string}|null
     * }>
     */
    public static function vacios(): array
    {
        return [
            self::entrada(1, null, null),
            self::entrada(2, null, null),
            self::entrada(3, null, null),
        ];
    }

    /**
     * Especies implicadas en las claves; las claves corruptas se omiten en vez
     * de romper el listado con un 400.
     */
    public static function especiesDe(StringCollection $claves): IntCollection
    {
        $especies = new IntCollection();

        foreach ($claves as $clave) {
            try {
                $especies->add(ClavePieza::desde($clave)->speciesId);
            } catch (ItemDesconocido) {
                continue;
            }
        }

        return $especies;
    }

    /**
     * @param  array<string, DefinicionPieza>  $porClave
     * @return array{
     *     slot: int,
     *     clave: string|null,
     *     tipo: string|null,
     *     nombre: string|null,
     *     icono: string|null,
     *     stats: array<string, int>|stdClass,
     *     habilidad: array{clave: string, nombre: string, descripcion: string}|null
     * }
     */
    private static function fila(int $slot, ?string $clave, array $porClave): array
    {
        if ($clave === null) {
            return self::entrada($slot, null, null);
        }

        try {
            $tipo = ClavePieza::desde($clave)->tipo;
        } catch (ItemDesconocido) {
            // Clave corrupta en `recruit_gear`: se muestra el slot sin
            // metadatos en vez de romper el listado.
            return self::entrada($slot, $clave, null);
        }

        $definicion = $porClave[$clave] ?? null;

        return self::entrada($slot, $clave, $tipo, $definicion);
    }

    /**
     * @return array{
     *     slot: int,
     *     clave: string|null,
     *     tipo: string|null,
     *     nombre: string|null,
     *     icono: string|null,
     *     stats: array<string, int>|stdClass,
     *     habilidad: array{clave: string, nombre: string, descripcion: string}|null
     * }
     */
    private static function entrada(
        int $slot,
        ?string $clave,
        ?string $tipo,
        ?DefinicionPieza $definicion = null,
    ): array {
        return [
            'slot' => $slot,
            'clave' => $clave,
            'tipo' => $tipo,
            'nombre' => $definicion?->nombre(),
            'icono' => $definicion?->icono,
            'stats' => $definicion === null ? (object) [] : $definicion->bonos->toArray(),
            'habilidad' => self::habilidadDe($definicion),
        ];
    }

    /**
     * @return array<string, DefinicionPieza>
     */
    private static function indexarPorClave(DefinicionesPiezaCollection $definiciones): array
    {
        $porClave = [];

        foreach ($definiciones as $definicion) {
            $porClave[$definicion->clave()] = $definicion;
        }

        return $porClave;
    }

    /**
     * @return array{clave: string, nombre: string, descripcion: string}|null
     */
    private static function habilidadDe(?DefinicionPieza $definicion): ?array
    {
        if ($definicion === null || $definicion->habilidadClave === null) {
            return null;
        }

        return [
            'clave' => $definicion->habilidadClave,
            'nombre' => $definicion->habilidadNombre ?? $definicion->habilidadClave,
            'descripcion' => $definicion->habilidadDescripcion ?? '',
        ];
    }
}
