<?php

declare(strict_types=1);

namespace Src\Forja\Domain;

use Src\Forja\Domain\Exceptions\RecetaNoExiste;
use Src\Shared\Collections\StringCollection;
use Src\Shared\Domain\Items\CatalogoItems;
use Src\Shared\Domain\Items\ClaveBaya;
use Src\Shared\Domain\Items\ClaveBonguri;
use Src\Shared\Domain\Items\ClaveMaterial;
use Src\Shared\Domain\Items\ClaveMejora;
use Src\Shared\Domain\Items\ClavePocion;
use Src\Shared\Domain\Items\ClaveReductor;
use Src\Shared\Domain\Items\ClaveResistencia;
use Src\Shared\Domain\ValueObjects\MapaConteo;

/**
 * Catálogo estático de recetas de forja. No depende de base de datos,
 * HTTP ni Eloquent.
 */
final class CatalogoRecetas
{
    /**
     * @var list<RecetaFabricacion>|null
     */
    private static ?array $lista = null;

    /**
     * @var array<string, RecetaFabricacion>|null
     */
    private static ?array $indice = null;

    public static function existe(string $clave): bool
    {
        return isset(self::indice()[$clave]);
    }

    /**
     * @throws RecetaNoExiste
     */
    public static function receta(string $clave): RecetaFabricacion
    {
        $recetas = self::indice();

        if (! isset($recetas[$clave])) {
            throw RecetaNoExiste::paraClave();
        }

        return $recetas[$clave];
    }

    public static function todas(): RecetaFabricacionCollection
    {
        return new RecetaFabricacionCollection(self::lista());
    }

    public static function paraResultado(string $resultadoClave): RecetaFabricacionCollection
    {
        return (new RecetaFabricacionCollection(self::lista()))->filter(
            static fn (RecetaFabricacion $receta): bool => $receta->resultadoClave === $resultadoClave,
        );
    }

    /**
     * Claves de resultado únicas, en orden de aparición del catálogo.
     */
    public static function resultados(): StringCollection
    {
        $resultados = array_map(
            static fn (RecetaFabricacion $receta): string => $receta->resultadoClave,
            self::lista(),
        );

        return new StringCollection(array_values(array_unique($resultados)));
    }

    /**
     * @return list<RecetaFabricacion>
     */
    private static function lista(): array
    {
        return self::$lista ??= self::construir();
    }

    /**
     * @return array<string, RecetaFabricacion>
     */
    private static function indice(): array
    {
        if (self::$indice !== null) {
            return self::$indice;
        }

        $indice = [];

        foreach (self::lista() as $receta) {
            $indice[$receta->clave] = $receta;
        }

        return self::$indice = $indice;
    }

    /**
     * @return list<RecetaFabricacion>
     */
    private static function construir(): array
    {
        $recetas = [
            self::crear(
                'receta:pocion_oran',
                ClavePocion::POCION->clave(),
                ['baya:oran' => 3],
                'Desde Baya Aranja',
            ),
            self::crear(
                'receta:pocion_sitrus',
                ClavePocion::POCION->clave(),
                ['baya:sitrus' => 1],
                'Desde Baya Zidra',
            ),
            self::crear(
                'receta:super',
                ClavePocion::SUPER->clave(),
                ['pocion:pocion' => 2],
                'Desde Poción',
            ),
            self::crear(
                'receta:hiper',
                ClavePocion::HIPER->clave(),
                ['pocion:super' => 3],
                'Desde Superpoción',
            ),
            self::crear(
                'receta:maxima',
                ClavePocion::MAXIMA->clave(),
                ['pocion:hiper' => 2],
                'Desde Hiperpoción',
            ),
            self::crear(
                'receta:restaurar_todo',
                ClavePocion::RESTAURAR_TODO->clave(),
                ['pocion:maxima' => 1, 'pocion:curatotal' => 1],
                'Desde Poción Máxima + Cura Total',
            ),
            self::crear(
                'receta:curatotal',
                ClavePocion::CURATOTAL->clave(),
                ['baya:cheri' => 1, 'baya:pecha' => 1, 'baya:rawst' => 1, 'baya:aspear' => 1],
                'Desde bayas de estado',
            ),
            self::crear(
                'receta:revivir',
                ClavePocion::REVIVIR->clave(),
                ['pocion:curatotal' => 1, 'pocion:super' => 1],
                'Desde Cura Total + Superpoción',
            ),
            self::crear(
                'receta:max_revivir',
                ClavePocion::MAX_REVIVIR->clave(),
                ['pocion:revivir' => 1, 'pocion:restaurar_todo' => 1],
                'Desde Revivir + Restaura Todo',
            ),
        ];

        foreach (ClaveBonguri::cases() as $bonguri) {
            $recetas[] = self::crear(
                'receta:recipiente:'.$bonguri->value,
                ClaveMaterial::RECIPIENTE->clave(),
                [$bonguri->clave() => 1],
                'Bonguri '.$bonguri->nombre(),
            );
        }

        foreach (ClaveMejora::cases() as $mejora) {
            $baya = $mejora->bayaOrigen();

            $recetas[] = self::crear(
                'receta:mejora:'.$mejora->value,
                $mejora->clave(),
                [$baya->clave() => 1, ClaveMaterial::RECIPIENTE->clave() => 1],
                'Desde Baya '.$baya->nombre(),
            );
        }

        foreach (ClaveReductor::cases() as $reductor) {
            $baya = $reductor->bayaOrigen();

            $recetas[] = self::crear(
                'receta:reductor:'.$reductor->value,
                $reductor->clave(),
                [$baya->clave() => 1, ClaveMaterial::RECIPIENTE->clave() => 1],
                'Desde Baya '.$baya->nombre(),
            );
        }

        foreach (ClaveResistencia::cases() as $resistencia) {
            $baya = self::bayaDeResistencia($resistencia);

            $recetas[] = self::crear(
                'receta:resistencia:'.$resistencia->value,
                $resistencia->clave(),
                [$baya->clave() => 1, ClaveMaterial::RECIPIENTE->clave() => 1],
                'Desde Baya '.$baya->nombre(),
            );
        }

        return $recetas;
    }

    /**
     * @param  array<string, int>  $ingredientes
     */
    private static function crear(
        string $clave,
        string $resultadoClave,
        array $ingredientes,
        string $etiqueta,
    ): RecetaFabricacion {
        return new RecetaFabricacion(
            clave: $clave,
            resultadoClave: $resultadoClave,
            resultadoCantidad: 1,
            ingredientes: MapaConteo::desdeArray($ingredientes),
            categoriaResultado: CatalogoItems::definicion($resultadoClave)->categoria,
            etiqueta: $etiqueta,
        );
    }

    private static function bayaDeResistencia(ClaveResistencia $resistencia): ClaveBaya
    {
        return match ($resistencia) {
            ClaveResistencia::NORMAL => ClaveBaya::CHILAN,
            ClaveResistencia::LUCHA => ClaveBaya::CHOPLE,
            ClaveResistencia::VOLADOR => ClaveBaya::COBA,
            ClaveResistencia::VENENO => ClaveBaya::KEBIA,
            ClaveResistencia::TIERRA => ClaveBaya::SHUCA,
            ClaveResistencia::ROCA => ClaveBaya::CHARTI,
            ClaveResistencia::BICHO => ClaveBaya::TANGA,
            ClaveResistencia::FANTASMA => ClaveBaya::KASIB,
            ClaveResistencia::ACERO => ClaveBaya::BABIRI,
            ClaveResistencia::FUEGO => ClaveBaya::OCCA,
            ClaveResistencia::AGUA => ClaveBaya::PASSHO,
            ClaveResistencia::PLANTA => ClaveBaya::RINDO,
            ClaveResistencia::ELECTRICO => ClaveBaya::WACAN,
            ClaveResistencia::PSIQUICO => ClaveBaya::PAYAPA,
            ClaveResistencia::HIELO => ClaveBaya::YACHE,
            ClaveResistencia::DRAGON => ClaveBaya::HABAN,
            ClaveResistencia::SINIESTRO => ClaveBaya::COLBUR,
            ClaveResistencia::HADA => ClaveBaya::ROSELI,
        };
    }
}
