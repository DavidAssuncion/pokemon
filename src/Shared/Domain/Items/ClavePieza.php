<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Items;

use Src\Shared\Domain\Exceptions\ItemDesconocido;

/**
 * Clave de inventario de una pieza de forja: `{tipo}:{species_id}`.
 *
 * Vive en Shared porque la consumen Forja, Equipos e Inventario, y Shared no
 * depende de ningún módulo. El tipo se guarda como cadena para no acoplar
 * Shared al enum `TipoPieza` de Forja; la lista cerrada de tipos vive aquí.
 */
final readonly class ClavePieza
{
    public const TIPOS = ['arma', 'armadura', 'amuleto'];

    private const PATRON = '/^(arma|armadura|amuleto):[1-9][0-9]*\z/';

    private function __construct(
        public string $tipo,
        public int $speciesId,
    ) {
    }

    /**
     * @throws ItemDesconocido
     */
    public static function de(string $tipo, int $speciesId): self
    {
        if (! in_array($tipo, self::TIPOS, true) || $speciesId <= 0) {
            throw ItemDesconocido::paraClave($tipo.':'.$speciesId);
        }

        return new self($tipo, $speciesId);
    }

    /**
     * @throws ItemDesconocido
     */
    public static function desde(string $clave): self
    {
        if (preg_match(self::PATRON, $clave, $coincidencias) !== 1) {
            throw ItemDesconocido::paraClave($clave);
        }

        $tipo = $coincidencias[1];

        return new self($tipo, (int) substr($clave, strlen($tipo) + 1));
    }

    public static function esClaveValida(string $clave): bool
    {
        return preg_match(self::PATRON, $clave) === 1;
    }

    public function clave(): string
    {
        return $this->tipo.':'.$this->speciesId;
    }
}
