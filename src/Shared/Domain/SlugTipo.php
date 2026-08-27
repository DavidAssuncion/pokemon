<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Slug ASCII (minúsculas, sin acentos) para nombres de tipo pokémon.
 * Dominio puro: no usa Str::ascii de Laravel. Usa iconv y, si no está
 * disponible, cae al mapa de los 18 tipos españoles.
 */
final class SlugTipo
{
    /** @var array<string, string> */
    private const MAPA_18_TIPOS = [
        'Normal' => 'normal',
        'Lucha' => 'lucha',
        'Volador' => 'volador',
        'Veneno' => 'veneno',
        'Tierra' => 'tierra',
        'Roca' => 'roca',
        'Bicho' => 'bicho',
        'Fantasma' => 'fantasma',
        'Acero' => 'acero',
        'Fuego' => 'fuego',
        'Agua' => 'agua',
        'Planta' => 'planta',
        'Eléctrico' => 'electrico',
        'Psíquico' => 'psiquico',
        'Hielo' => 'hielo',
        'Dragón' => 'dragon',
        'Siniestro' => 'siniestro',
        'Hada' => 'hada',
    ];

    /** 'Eléctrico' → 'electrico', 'Dragón' → 'dragon'. */
    public static function de(string $nombre): string
    {
        $slug = function_exists('iconv')
            ? @iconv('UTF-8', 'ASCII//TRANSLIT', $nombre)
            : false;

        if (is_string($slug) && $slug !== '') {
            return strtolower($slug);
        }

        return strtolower(self::MAPA_18_TIPOS[$nombre] ?? $nombre);
    }
}
