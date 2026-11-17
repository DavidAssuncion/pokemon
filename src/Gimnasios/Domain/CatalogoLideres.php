<?php

declare(strict_types=1);

namespace Src\Gimnasios\Domain;

/**
 * Catálogo canónico de los líderes de gimnasio. La clave es el slug del
 * gimnasio y el valor el nombre visible del líder. No es editable: el dato
 * vive en código, no en BD.
 */
final class CatalogoLideres
{
    /** @var array<string, non-empty-string> */
    private const NOMBRES = [
        'bug' => 'Bugsy',
        'poison' => 'Koga',
        'normal' => 'Whitney',
        'grass' => 'Erika',
        'flying' => 'Falkner',
        'rock' => 'Brock',
        'electric' => 'Teniente Surge',
        'ice' => 'Pryce',
        'fire' => 'Blaine',
        'water' => 'Misty',
        'ground' => 'Giovanni',
        'psychic' => 'Sabrina',
        'dark' => 'Piers',
        'ghost' => 'Morty',
        'fighting' => 'Chuck',
        'fairy' => 'Valerie',
        'steel' => 'Jasmine',
        'dragon' => 'Clair',
    ];

    public const POR_DEFECTO = 'Líder';

    /**
     * Devuelve el nombre canónico del líder para el slug indicado. Si el slug
     * no está en el catálogo (gimnasio creado por admin) devuelve el fallback.
     */
    public static function nombreDe(string $slug): string
    {
        return self::NOMBRES[$slug] ?? self::POR_DEFECTO;
    }
}
