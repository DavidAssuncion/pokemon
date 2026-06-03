<?php

namespace Src\Battle\Domain\Effects;

/**
 * Fábrica que registra y crea efectos (habilidades e items).
 * Para añadir un nuevo efecto/item solo se necesita registrarlo aquí
 * con su clave y clase asociada.
 */
class FabricaEfectos
{
    /** @var array<string, array{clase: class-string<InterfazEfecto>, args: array}> */
    private static array $efectosRegistrados = [];

    /** @var array<string, class-string<InterfazEfecto>> */
    private static array $itemsRegistrados = [];

    /**
     * Registra un efecto de habilidad.
     *
     * @param  string  $clave  Identificador del efecto
     * @param  string  $clase  Clase que implementa InterfazEfecto
     * @param  mixed   ...$args  Argumentos extra para el constructor (además de $clave)
     */
    public static function registrarEfecto(string $clave, string $clase, mixed ...$args): void
    {
        self::$efectosRegistrados[$clave] = ['clase' => $clase, 'args' => $args];
    }

    /**
     * Registra un efecto de objeto equipado.
     */
    public static function registrarItem(string $clave, string $clase): void
    {
        self::$itemsRegistrados[$clave] = $clase;
    }

    /**
     * Crea un efecto de habilidad a partir de su clave.
     */
    public static function crearEfecto(string $clave): ?InterfazEfecto
    {
        $registro = self::$efectosRegistrados[$clave] ?? null;
        if ($registro === null) {
            return null;
        }
        $clase = $registro['clase'];
        $args = $registro['args'];
        // El primer argumento siempre es la clave
        array_unshift($args, $clave);
        return new $clase(...$args);
    }

    /**
     * Crea un efecto de objeto a partir de su clave.
     */
    public static function crearItem(string $clave): ?InterfazEfecto
    {
        $clase = self::$itemsRegistrados[$clave] ?? null;
        if ($clase === null) {
            return null;
        }
        return new $clase($clave);
    }

    /**
     * Retorna todas las claves de efectos registrados.
     * @return string[]
     */
    public static function clavesEfectos(): array
    {
        return array_keys(self::$efectosRegistrados);
    }

    /**
     * Retorna todas las claves de items registrados.
     * @return string[]
     */
    public static function clavesItems(): array
    {
        return array_keys(self::$itemsRegistrados);
    }
}
