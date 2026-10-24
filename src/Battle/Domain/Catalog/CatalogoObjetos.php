<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Catalog;

use Src\Battle\Domain\Enums\ClaveItem;
use Src\Shared\Collections\StringCollection;

/**
 * Catálogo de objetos equipables de combate.
 *
 * Las claves coinciden con `ClaveItem` y con lo que guarda
 * `reclutados.obj_equipados`. `cargas` = -1 significa objeto pasivo sin
 * consumo; un valor positivo indica usos consumibles (bayas, banda focus…).
 */
final class CatalogoObjetos
{
    /** @var array<string, array{nombre: string, cargas: int, icono: string}> */
    private const OBJETOS = [
        'life_orb' => ['nombre' => 'Orbe Vida', 'cargas' => -1, 'icono' => '🔮'],
        'leftovers' => ['nombre' => 'Restos', 'cargas' => -1, 'icono' => '🧴'],
        'focus_sash' => ['nombre' => 'Banda Focus', 'cargas' => 1, 'icono' => '🎗️'],
        'sitrus_berry' => ['nombre' => 'Baya Sitrus', 'cargas' => 1, 'icono' => '🍊'],
        'lum_berry' => ['nombre' => 'Baya Lum', 'cargas' => 1, 'icono' => '🍒'],
        'choice_band' => ['nombre' => 'Cinta Elección', 'cargas' => -1, 'icono' => '🎀'],
        'choice_specs' => ['nombre' => 'Gafas Elección', 'cargas' => -1, 'icono' => '🕶️'],
        'assault_vest' => ['nombre' => 'Chaleco Asalto', 'cargas' => -1, 'icono' => '🦺'],
    ];

    public static function existe(string $clave): bool
    {
        return isset(self::OBJETOS[$clave]);
    }

    public static function nombreDe(string $clave): string
    {
        return self::OBJETOS[$clave]['nombre'] ?? $clave;
    }

    public static function iconoDe(string $clave): string
    {
        return self::OBJETOS[$clave]['icono'] ?? '📦';
    }

    /**
     * -1 = pasivo; 1+ = consumible.
     */
    public static function cargasDe(string $clave): int
    {
        return self::OBJETOS[$clave]['cargas'] ?? -1;
    }

    public static function esConsumible(string $clave): bool
    {
        return self::cargasDe($clave) > 0;
    }

    public static function claves(): StringCollection
    {
        return new StringCollection(array_keys(self::OBJETOS));
    }

    public static function clavesDeEfecto(): StringCollection
    {
        return new StringCollection(array_map(
            static fn (ClaveItem $item): string => $item->value,
            ClaveItem::cases(),
        ));
    }
}
