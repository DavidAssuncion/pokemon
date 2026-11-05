<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

use Src\Shared\Domain\Exceptions\ItemDesconocido;

/**
 * Catálogo estático de items compartidos. No depende de base de datos,
 * HTTP ni Eloquent; es la única fuente de verdad de claves, nombres,
 * categorías, iconos y banderas de uso.
 */
final class CatalogoItems
{
    /**
     * Bayas base: equipables, usables en combate y consumibles.
     *
     * @var list<ClaveBaya>
     */
    private const BAYAS_BASE = [
        ClaveBaya::ORAN,
        ClaveBaya::SITRUS,
        ClaveBaya::CHERI,
        ClaveBaya::PECHA,
        ClaveBaya::RAWST,
        ClaveBaya::ASPEAR,
    ];

    /**
     * Bayas de estadística: materiales.
     *
     * @var list<ClaveBaya>
     */
    private const BAYAS_ESTADISTICA = [
        ClaveBaya::KELPSY,
        ClaveBaya::QUALOT,
        ClaveBaya::HONDEW,
        ClaveBaya::GREPA,
        ClaveBaya::TAMATO,
    ];

    /**
     * Bayas raras: materiales.
     *
     * @var list<ClaveBaya>
     */
    private const BAYAS_RARAS = [
        ClaveBaya::LIECHI,
        ClaveBaya::GANLON,
        ClaveBaya::SALAC,
        ClaveBaya::PETAYA,
        ClaveBaya::APICOT,
        ClaveBaya::LANSAT,
        ClaveBaya::MICLE,
    ];

    /**
     * @var array<string, DefinicionItem>|null
     */
    private static ?array $definiciones = null;

    public static function existe(string $clave): bool
    {
        return isset(self::mapa()[$clave]);
    }

    /**
     * @throws ItemDesconocido
     */
    public static function definicion(string $clave): DefinicionItem
    {
        $definiciones = self::mapa();

        if (! isset($definiciones[$clave])) {
            throw ItemDesconocido::paraClave($clave);
        }

        return $definiciones[$clave];
    }

    public static function definiciones(): DefinicionItemCollection
    {
        return new DefinicionItemCollection(array_values(self::mapa()));
    }

    public static function porCategoria(CategoriaItem $categoria): DefinicionItemCollection
    {
        return new DefinicionItemCollection(array_values(array_filter(
            self::mapa(),
            fn (DefinicionItem $definicion): bool => $definicion->categoria === $categoria,
        )));
    }

    /**
     * Grupo funcional de una baya para el catálogo de plantación. Es la única
     * fuente de verdad de la clasificación: medicinas, mejoras, reductores y
     * resistencias.
     */
    public static function grupoDeBaya(ClaveBaya $baya): GrupoBaya
    {
        if (in_array($baya, self::BAYAS_BASE, true)) {
            return GrupoBaya::MEDICINA;
        }

        if (in_array($baya, self::BAYAS_RARAS, true)) {
            return GrupoBaya::MEJORA;
        }

        if (in_array($baya, self::BAYAS_ESTADISTICA, true)) {
            return GrupoBaya::REDUCTOR;
        }

        return GrupoBaya::RESISTENCIA;
    }

    /**
     * Items disponibles en el panel de uso de objetos en combate.
     *
     * Incluye las 6 bayas base y los 38 objetos fabricados usables
     * (8 pociones + 7 mejoras + 5 reductores + 18 resistencias).
     */
    public static function objetosDeCombate(): DefinicionItemCollection
    {
        return new DefinicionItemCollection(array_values(array_filter(
            self::mapa(),
            static fn (DefinicionItem $definicion): bool => $definicion->usableEnCombate,
        )));
    }

    /**
     * Resuelve una clave de inventario con prefijo (`baya:`, `bonguri:`,
     * `pocion:`, `material:`, `mejora:`, `reductor:` o `resistencia:`).
     * Devuelve null para prefijos o slugs desconocidos.
     */
    public static function aPartirDeItemKey(string $itemKey): ?DefinicionItem
    {
        $prefijoBaya = CategoriaItem::BAYA->prefijo();

        if (str_starts_with($itemKey, $prefijoBaya)) {
            $baya = ClaveBaya::tryFrom(substr($itemKey, strlen($prefijoBaya)));

            return $baya === null ? null : self::definicion($baya->clave());
        }

        $prefijoBonguri = CategoriaItem::BONGURI->prefijo();

        if (str_starts_with($itemKey, $prefijoBonguri)) {
            $bonguri = ClaveBonguri::tryFrom(substr($itemKey, strlen($prefijoBonguri)));

            return $bonguri === null ? null : self::definicion($bonguri->clave());
        }

        $prefijoPocion = CategoriaItem::POCION->prefijo();

        if (str_starts_with($itemKey, $prefijoPocion)) {
            $pocion = ClavePocion::tryFrom(substr($itemKey, strlen($prefijoPocion)));

            return $pocion === null ? null : self::definicion($pocion->clave());
        }

        $prefijoMaterial = CategoriaItem::MATERIAL->prefijo();

        if (str_starts_with($itemKey, $prefijoMaterial)) {
            $material = ClaveMaterial::tryFrom(substr($itemKey, strlen($prefijoMaterial)));

            return $material === null ? null : self::definicion($material->clave());
        }

        $prefijoMejora = CategoriaItem::MEJORA->prefijo();

        if (str_starts_with($itemKey, $prefijoMejora)) {
            $mejora = ClaveMejora::tryFrom(substr($itemKey, strlen($prefijoMejora)));

            return $mejora === null ? null : self::definicion($mejora->clave());
        }

        $prefijoReductor = CategoriaItem::REDUCTOR->prefijo();

        if (str_starts_with($itemKey, $prefijoReductor)) {
            $reductor = ClaveReductor::tryFrom(substr($itemKey, strlen($prefijoReductor)));

            return $reductor === null ? null : self::definicion($reductor->clave());
        }

        $prefijoResistencia = CategoriaItem::RESISTENCIA->prefijo();

        if (str_starts_with($itemKey, $prefijoResistencia)) {
            $resistencia = ClaveResistencia::tryFrom(substr($itemKey, strlen($prefijoResistencia)));

            return $resistencia === null ? null : self::definicion($resistencia->clave());
        }

        return null;
    }

    /**
     * @return array<string, DefinicionItem>
     */
    private static function mapa(): array
    {
        return self::$definiciones ??= self::construir();
    }

    /**
     * @return array<string, DefinicionItem>
     */
    private static function construir(): array
    {
        $definiciones = [];

        foreach (ClaveBaya::cases() as $baya) {
            $esBase = in_array($baya, self::BAYAS_BASE, true);

            $definiciones[$baya->clave()] = new DefinicionItem(
                clave: $baya->clave(),
                nombre: $baya->nombre(),
                categoria: CategoriaItem::BAYA,
                equipable: $esBase,
                usableEnCombate: $esBase,
                consumible: $esBase,
                icono: $baya->archivoIcono(),
                descripcion: self::descripcionBaya($baya),
            );
        }

        foreach (ClaveBonguri::cases() as $bonguri) {
            $definiciones[$bonguri->clave()] = new DefinicionItem(
                clave: $bonguri->clave(),
                nombre: $bonguri->nombre(),
                categoria: CategoriaItem::BONGURI,
                equipable: false,
                usableEnCombate: false,
                consumible: false,
                icono: $bonguri->archivoIcono(),
                descripcion: "Bonguri {$bonguri->nombre()}. Material para fabricar Poké Balls.",
            );
        }

        foreach (ClavePocion::cases() as $pocion) {
            $definiciones[$pocion->clave()] = new DefinicionItem(
                clave: $pocion->clave(),
                nombre: $pocion->nombre(),
                categoria: CategoriaItem::POCION,
                equipable: false,
                usableEnCombate: true,
                consumible: true,
                icono: $pocion->archivoIcono(),
                descripcion: self::descripcionPocion($pocion),
            );
        }

        foreach (ClaveMaterial::cases() as $material) {
            $definiciones[$material->clave()] = new DefinicionItem(
                clave: $material->clave(),
                nombre: $material->nombre(),
                categoria: CategoriaItem::MATERIAL,
                equipable: false,
                usableEnCombate: false,
                consumible: false,
                icono: $material->archivoIcono(),
                descripcion: 'Recipiente para fabricar objetos',
            );
        }

        foreach (ClaveMejora::cases() as $mejora) {
            $definiciones[$mejora->clave()] = new DefinicionItem(
                clave: $mejora->clave(),
                nombre: $mejora->nombre(),
                categoria: CategoriaItem::MEJORA,
                equipable: false,
                usableEnCombate: true,
                consumible: true,
                icono: $mejora->archivoIcono(),
                descripcion: "Objeto de forja. {$mejora->nombre()} durante cinco rondas de combate.",
            );
        }

        foreach (ClaveReductor::cases() as $reductor) {
            $definiciones[$reductor->clave()] = new DefinicionItem(
                clave: $reductor->clave(),
                nombre: $reductor->nombre(),
                categoria: CategoriaItem::REDUCTOR,
                equipable: false,
                usableEnCombate: true,
                consumible: true,
                icono: $reductor->archivoIcono(),
                descripcion: "Objeto de forja. {$reductor->nombre()} durante cinco rondas de combate.",
            );
        }

        foreach (ClaveResistencia::cases() as $resistencia) {
            $definiciones[$resistencia->clave()] = new DefinicionItem(
                clave: $resistencia->clave(),
                nombre: $resistencia->nombre(),
                categoria: CategoriaItem::RESISTENCIA,
                equipable: false,
                usableEnCombate: true,
                consumible: true,
                icono: $resistencia->archivoIcono(),
                descripcion: 'Objeto de forja. Reduce el daño recibido del tipo '
                    .$resistencia->tipo()->label().' durante cinco rondas de combate.',
            );
        }

        return $definiciones;
    }

    private static function descripcionPocion(ClavePocion $pocion): string
    {
        return match ($pocion) {
            ClavePocion::POCION => 'Poción fabricada. Restaura una pequeña cantidad de PS.',
            ClavePocion::SUPER => 'Superpoción fabricada. Restaura una cantidad moderada de PS.',
            ClavePocion::HIPER => 'Hiperpoción fabricada. Restaura una gran cantidad de PS.',
            ClavePocion::MAXIMA => 'Poción Máxima fabricada. Restaura todos los PS.',
            ClavePocion::CURATOTAL => 'Cura Total fabricada. Cura todos los problemas de estado.',
            ClavePocion::RESTAURAR_TODO => 'Restaura Todo fabricado. Restaura PS, estado y usos.',
            ClavePocion::REVIVIR => 'Revivir fabricado. Reanima a un Pokémon debilitado con la mitad de sus PS.',
            ClavePocion::MAX_REVIVIR => 'Revivir Máximo fabricado. Reanima a un Pokémon debilitado con todos sus PS.',
        };
    }

    private static function descripcionBaya(ClaveBaya $baya): string
    {
        if (in_array($baya, self::BAYAS_BASE, true)) {
            return self::descripcionBayaBase($baya);
        }

        if (in_array($baya, self::BAYAS_ESTADISTICA, true)) {
            return 'Baya de estadística. Material para elaborar objetos que reducen una estadística.';
        }

        if (in_array($baya, self::BAYAS_RARAS, true)) {
            return 'Baya rara. Material de forja para objetos potentes.';
        }

        return self::descripcionBayaDeTipo($baya);
    }

    private static function descripcionBayaBase(ClaveBaya $baya): string
    {
        return match ($baya) {
            ClaveBaya::ORAN => 'Baya común. Restaura una pequeña cantidad de PS.',
            ClaveBaya::SITRUS => 'Baya poco común. Restaura una cantidad moderada de PS.',
            ClaveBaya::CHERI => 'Baya común. Cura la parálisis.',
            ClaveBaya::PECHA => 'Baya común. Cura el envenenamiento.',
            ClaveBaya::RAWST => 'Baya común. Cura las quemaduras.',
            ClaveBaya::ASPEAR => 'Baya común. Cura la congelación.',
            default => 'Baya común.',
        };
    }

    private static function descripcionBayaDeTipo(ClaveBaya $baya): string
    {
        $tipo = match ($baya) {
            ClaveBaya::OCCA => 'Fuego',
            ClaveBaya::PASSHO => 'Agua',
            ClaveBaya::WACAN => 'Eléctrico',
            ClaveBaya::RINDO => 'Planta',
            ClaveBaya::YACHE => 'Hielo',
            ClaveBaya::CHOPLE => 'Lucha',
            ClaveBaya::KEBIA => 'Veneno',
            ClaveBaya::SHUCA => 'Tierra',
            ClaveBaya::COBA => 'Volador',
            ClaveBaya::PAYAPA => 'Psíquico',
            ClaveBaya::TANGA => 'Bicho',
            ClaveBaya::CHARTI => 'Roca',
            ClaveBaya::KASIB => 'Fantasma',
            ClaveBaya::HABAN => 'Dragón',
            ClaveBaya::COLBUR => 'Siniestro',
            ClaveBaya::BABIRI => 'Acero',
            ClaveBaya::CHILAN => 'Normal',
            ClaveBaya::ROSELI => 'Hada',
            default => 'desconocido',
        };

        return "Baya de tipo. Material para elaborar una resistencia contra el tipo {$tipo}.";
    }
}
