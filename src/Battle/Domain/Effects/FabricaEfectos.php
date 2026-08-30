<?php

declare(strict_types=1);

namespace Src\Battle\Domain\Effects;

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
     * @param  string  $clave  Identificador del efecto
     * @param  string  $clase  Clase que implementa InterfazEfecto
     * @param  mixed  ...$args  Argumentos extra para el constructor (además de $clave)
     */
    public function registrarEfecto(string $clave, string $clase, mixed ...$args): void
    {
        $this->efectosRegistrados[$clave] = ['clase' => $clase, 'args' => $args];
    }

    /**
     * Registra un efecto de objeto equipado.
     */
    public function registrarItem(string $clave, string $clase): void
    {
        $this->itemsRegistrados[$clave] = $clase;
    }

    /**
     * Crea un efecto de habilidad a partir de su clave.
     */
    public function crearEfecto(string $clave): ?InterfazEfecto
    {
        $registro = $this->efectosRegistrados[$clave] ?? null;
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
    public function crearItem(string $clave): ?InterfazEfecto
    {
        $clase = $this->itemsRegistrados[$clave] ?? null;
        if ($clase === null) {
            return null;
        }

        return new $clase($clave);
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
