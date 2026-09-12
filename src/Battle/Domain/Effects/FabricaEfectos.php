<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

use Src\Battle\Domain\Enums\ClaveEfecto;
use Src\Battle\Domain\Enums\ClaveItem;

/**
 * Fábrica que registra y crea efectos (habilidades e items).
 * Para añadir un nuevo efecto/item solo se necesita registrarlo aquí
 * con su clave y clase asociada.
 */
class FabricaEfectos
{
    /** @var array<string, array{clase: class-string<InterfazEfecto>, args: array}> */
    private array $efectosRegistrados = [];

    /** @var array<string, class-string<InterfazEfecto>> */
    private array $itemsRegistrados = [];

    /**
     * Registra un efecto de habilidad.
     *
     * @param  string|ClaveEfecto  $clave  Identificador del efecto
     * @param  string  $clase  Clase que implementa InterfazEfecto
     * @param  mixed  ...$args  Argumentos extra para el constructor (además de $clave)
     */
    public function registrarEfecto(string|ClaveEfecto $clave, string $clase, mixed ...$args): void
    {
        $key = $clave instanceof ClaveEfecto ? $clave->value : $clave;
        $this->efectosRegistrados[$key] = ['clase' => $clase, 'args' => $args];
    }

    /**
     * Registra un efecto de objeto equipado.
     *
     * @param  string|ClaveItem  $clave
     */
    public function registrarItem(string|ClaveItem $clave, string $clase): void
    {
        $key = $clave instanceof ClaveItem ? $clave->value : $clave;
        $this->itemsRegistrados[$key] = $clase;
    }

    /**
     * Crea un efecto de habilidad a partir de su clave.
     *
     * @param  string|ClaveEfecto  $clave
     */
    public function crearEfecto(string|ClaveEfecto $clave): ?InterfazEfecto
    {
        $key = $clave instanceof ClaveEfecto ? $clave->value : $clave;
        $registro = $this->efectosRegistrados[$key] ?? null;
        if ($registro === null) {
            return null;
        }
        $clase = $registro['clase'];
        $args = $registro['args'];
        // El primer argumento siempre es la clave
        array_unshift($args, $key);

        return new $clase(...$args);
    }

    /**
     * Crea un efecto de objeto a partir de su clave.
     *
     * @param  string|ClaveItem  $clave
     */
    public function crearItem(string|ClaveItem $clave): ?InterfazEfecto
    {
        $key = $clave instanceof ClaveItem ? $clave->value : $clave;
        $clase = $this->itemsRegistrados[$key] ?? null;
        if ($clase === null) {
            return null;
        }

        return new $clase($key);
    }

    /**
     * Retorna todas las claves de efectos registrados.
     *
     * @return string[]
     */
    public function clavesEfectos(): array
    {
        return array_keys($this->efectosRegistrados);
    }

    /**
     * Retorna todas las claves de items registrados.
     *
     * @return string[]
     */
    public function clavesItems(): array
    {
        return array_keys($this->itemsRegistrados);
    }
}
